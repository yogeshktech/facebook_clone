<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if ($this->authService->attempt($validated['login'], $validated['password'], $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            if ($user && $user->isAdmin()) {
                return redirect()->intended(route('admin.ads.index'));
            }

            return redirect()->intended(route('feed.index'));
        }

        return back()->withErrors([
            'login' => 'Invalid email/mobile or password.',
        ])->onlyInput('login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
