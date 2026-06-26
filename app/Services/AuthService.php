<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function resolveLoginField(string $login): string
    {
        return filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    }

    public function normalizeLogin(string $login, string $field): string
    {
        if ($field === 'phone') {
            return preg_replace('/\D/', '', $login);
        }

        return strtolower(trim($login));
    }

    public function attempt(string $login, string $password, bool $remember = false): bool
    {
        $field = $this->resolveLoginField($login);
        $value = $this->normalizeLogin($login, $field);

        return Auth::attempt([$field => $value, 'password' => $password], $remember);
    }

    public function findByLogin(string $login): ?User
    {
        $field = $this->resolveLoginField($login);
        $value = $this->normalizeLogin($login, $field);

        return User::where($field, $value)->first();
    }
}
