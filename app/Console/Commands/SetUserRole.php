<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    protected $signature = 'user:set-role {login} {role : user, admin, or superadmin}';

    protected $description = 'Set a user role by email or phone';

    public function handle(AuthService $authService): int
    {
        $user = $authService->findByLogin($this->argument('login'));

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $role = $this->argument('role');
        if (! in_array($role, ['user', 'admin', 'superadmin'], true)) {
            $this->error('Role must be: user, admin, or superadmin');

            return self::FAILURE;
        }

        $user->update(['role' => $role]);
        $this->info("Updated {$user->email} → role: {$role}");

        return self::SUCCESS;
    }
}
