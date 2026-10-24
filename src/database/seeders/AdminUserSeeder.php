<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower(trim((string) config('scitrace.admin.email', '')));
        $password = (string) config('scitrace.admin.password', '');

        if ($email === '' || $password === '') {
            $this->command?->warn(
                'Administrador no creado: define SCITRACE_ADMIN_EMAIL y SCITRACE_ADMIN_PASSWORD.'
            );

            return;
        }

        if (strlen($password) < 12) {
            $this->command?->error(
                'Administrador no creado: SCITRACE_ADMIN_PASSWORD debe tener al menos 12 caracteres.'
            );

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command?->error('Administrador no creado: SCITRACE_ADMIN_EMAIL no es válido.');

            return;
        }

        $admin = User::withTrashed()->firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => (string) config('scitrace.admin.name', 'Administrador SciTrace'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'locale' => 'es',
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ]);
        $admin->deleted_at = null;
        $admin->save();

        $this->command?->info('Administrador asegurado: '.$email);
    }
}
