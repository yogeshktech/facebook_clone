<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private OtpService $otpService
    ) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->otpService->generateAndSend($validated['email'], $validated['name']);

        Cache::put('registration:'.$validated['email'], [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
        ], now()->addMinutes(15));

        return response()->json(['message' => 'OTP sent to email', 'email' => $validated['email']]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if (! $this->otpService->verify($validated['email'], $validated['otp'])) {
            throw ValidationException::withMessages(['otp' => ['Invalid or expired OTP.']]);
        }

        $data = Cache::get('registration:'.$validated['email']);
        if (! $data) {
            throw ValidationException::withMessages(['email' => ['Registration session expired. Please register again.']]);
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => Str::slug($data['name']).'-'.Str::random(4),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        Cache::forget('registration:'.$validated['email']);
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (! $this->authService->attempt($request->login, $request->password)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid email/mobile or password.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
        ]);

        $user = $this->authService->findByLogin($validated['login']);

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return response()->json([
            'message' => 'If an account exists, a reset link has been sent to the registered email.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password reset successfully']);
    }
}
