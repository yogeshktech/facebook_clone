<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
        ]);

        try {
            $this->otpService->generateAndSend($validated['email'], $validated['name']);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'email' => 'Failed to send OTP. Please check mail configuration. '.$e->getMessage(),
            ]);
        }

        $request->session()->put('registration', [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('register.verify')
            ->with('success', 'OTP sent to '.$validated['email']);
    }

    public function showVerifyForm(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has('registration')) {
            return redirect()->route('register');
        }

        return view('auth.register-verify', [
            'email' => $request->session()->get('registration.email'),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        if (! $request->session()->has('registration')) {
            return redirect()->route('register');
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $data = $request->session()->get('registration');

        if (! $this->otpService->verify($data['email'], $validated['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please try again.']);
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => Str::slug($data['name']).'-'.Str::random(4),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $request->session()->forget('registration');
        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('feed.index')->with('success', 'Account created successfully!');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        if (! $request->session()->has('registration')) {
            return redirect()->route('register');
        }

        $data = $request->session()->get('registration');

        try {
            $this->otpService->generateAndSend($data['email'], $data['name']);
        } catch (\Throwable $e) {
            return back()->withErrors(['otp' => 'Failed to resend OTP. '.$e->getMessage()]);
        }

        return back()->with('success', 'New OTP sent to '.$data['email']);
    }
}
