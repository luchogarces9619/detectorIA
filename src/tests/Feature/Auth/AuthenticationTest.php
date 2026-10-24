<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('autentica a un usuario activo', function () {
    $user = User::factory()->create([
        'email' => 'investigadora@universidad.edu.ec',
        'password' => Hash::make('ClaveSegura2026'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'Investigadora@Universidad.edu.ec')
        ->set('password', 'ClaveSegura2026')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rechaza credenciales incorrectas sin revelar si el correo existe', function () {
    User::factory()->create([
        'email' => 'persona@universidad.edu.ec',
        'password' => Hash::make('ClaveSegura2026'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'persona@universidad.edu.ec')
        ->set('password', 'incorrecta')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('impide el acceso de una cuenta suspendida', function () {
    User::factory()->suspended()->create([
        'email' => 'suspendida@universidad.edu.ec',
        'password' => Hash::make('ClaveSegura2026'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'suspendida@universidad.edu.ec')
        ->set('password', 'ClaveSegura2026')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('limita los intentos fallidos de inicio de sesión', function () {
    User::factory()->create([
        'email' => 'objetivo@universidad.edu.ec',
        'password' => Hash::make('ClaveSegura2026'),
    ]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        Livewire::test(Login::class)
            ->set('email', 'objetivo@universidad.edu.ec')
            ->set('password', 'incorrecta')
            ->call('login');
    }

    Livewire::test(Login::class)
        ->set('email', 'objetivo@universidad.edu.ec')
        ->set('password', 'incorrecta')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee('Demasiados intentos');
});

it('registra un usuario con rol y estado seguros por defecto', function () {
    Livewire::test(Register::class)
        ->set('name', 'María Fernanda Pérez')
        ->set('email', 'Maria@Universidad.edu.ec')
        ->set('password', 'ClaveSegura2026')
        ->set('password_confirmation', 'ClaveSegura2026')
        ->set('accept_terms', true)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::query()->firstOrFail();

    expect($user->email)->toBe('maria@universidad.edu.ec')
        ->and($user->role)->toBe(UserRole::User)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and(Hash::check('ClaveSegura2026', $user->password))->toBeTrue();
});

it('rechaza un registro con correo duplicado', function () {
    User::factory()->create(['email' => 'existente@universidad.edu.ec']);

    Livewire::test(Register::class)
        ->set('name', 'Otra Persona')
        ->set('email', 'existente@universidad.edu.ec')
        ->set('password', 'ClaveSegura2026')
        ->set('password_confirmation', 'ClaveSegura2026')
        ->set('accept_terms', true)
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);

    expect(User::query()->count())->toBe(1);
});

it('exige aceptar los términos y una contraseña robusta', function () {
    Livewire::test(Register::class)
        ->set('name', 'Persona de Prueba')
        ->set('email', 'nueva@universidad.edu.ec')
        ->set('password', 'corta')
        ->set('password_confirmation', 'corta')
        ->set('accept_terms', false)
        ->call('register')
        ->assertHasErrors(['password', 'accept_terms']);

    expect(User::query()->count())->toBe(0);
});
