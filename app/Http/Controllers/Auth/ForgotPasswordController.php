<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function findAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
        ]);

        $user = $this->authService->findByLogin($validated['login']);

        if ($user) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors([
                    'login' => 'Failed to send reset email. Please check mail configuration.',
                ]);
            }
        }

        return redirect()->route('password.sent')
            ->with('login', $validated['login'])
            ->with('account_found', (bool) $user);
    }

    public function showSentForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login')) {
            return redirect()->route('password.request');
        }

        $user = $this->authService->findByLogin($request->session()->get('login', ''));

        return view('auth.password-sent', [
            'maskedEmail' => $user ? $this->maskEmail($user->email) : null,
            'accountFound' => $request->session()->get('account_found', false),
        ]);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', old('email')),
        ]);
    }

    public function reset(Request $request): RedirectResponse
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
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password reset successfully. Please log in with your new password.');
        }

        return back()->withInput()->withErrors(['email' => __($status)]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = substr($local, 0, min(3, strlen($local)));

        return $visible.str_repeat('*', max(strlen($local) - strlen($visible), 2)).'@'.$domain;
    }
}
