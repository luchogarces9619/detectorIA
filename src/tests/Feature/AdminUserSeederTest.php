<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea el administrador definido en la configuración', function () {
    config([
        'scitrace.admin.name' => 'Admin de Prueba',
        'scitrace.admin.email' => 'Admin@Universidad.edu.ec',
        'scitrace.admin.password' => 'ClaveAdmin2026',
    ]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@universidad.edu.ec')->firstOrFail();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->status)->toBe(UserStatus::Active)
        ->and($admin->name)->toBe('Admin de Prueba');
});

it('no crea un administrador sin credenciales completas', function () {
    config([
        'scitrace.admin.email' => 'admin@universidad.edu.ec',
        'scitrace.admin.password' => '',
    ]);

    $this->seed(AdminUserSeeder::class);

    expect(User::query()->count())->toBe(0);
});
