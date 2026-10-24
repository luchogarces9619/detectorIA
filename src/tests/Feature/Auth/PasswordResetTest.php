<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('envía el enlace de recuperación sin revelar si el correo existe', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'recuperar@universidad.edu.ec']);

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('statusMessage', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('mantiene la misma respuesta para un correo inexistente', function () {
    Notification::fake();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'no-existe@universidad.edu.ec')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('statusMessage', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');

    Notification::assertNothingSent();
});

it('restablece la contraseña con un token válido', function () {
    $user = User::factory()->create([
        'email' => 'token@universidad.edu.ec',
        'password' => Hash::make('ClaveAnterior2026'),
    ]);

    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'ClaveNueva2026')
        ->set('password_confirmation', 'ClaveNueva2026')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('ClaveNueva2026', $user->fresh()->password))->toBeTrue();
});

it('rechaza un token inválido', function () {
    $user = User::factory()->create(['email' => 'invalido@universidad.edu.ec']);

    Livewire::test(ResetPassword::class, ['token' => 'token-invalido'])
        ->set('email', $user->email)
        ->set('password', 'ClaveNueva2026')
        ->set('password_confirmation', 'ClaveNueva2026')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect(Hash::check('ClaveNueva2026', $user->fresh()->password))->toBeFalse();
});
