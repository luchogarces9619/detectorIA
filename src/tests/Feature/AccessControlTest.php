<?php

use App\Livewire\Admin\UsersTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirige a invitados desde el panel', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('permite el acceso a usuarios activos', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Panel principal');
});

it('expulsa a un usuario suspendido con sesión iniciada', function () {
    $user = User::factory()->suspended()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('bloquea el panel administrativo a usuarios normales', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('permite el panel administrativo a administradores', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Resumen administrativo');
});

it('impide suspender la propia cuenta desde la tabla de usuarios', function () {
    $admin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test(UsersTable::class)
        ->call('toggleStatus', $admin->id);

    expect($admin->fresh()->status->value)->toBe('active');
});

it('permite a un administrador suspender a otro usuario y audita el cambio', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    Livewire\Livewire::actingAs($admin)
        ->test(UsersTable::class)
        ->call('toggleStatus', $user->id);

    expect($user->fresh()->status->value)->toBe('suspended');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.user.status_changed',
        'subject_id' => $user->id,
    ]);
});
