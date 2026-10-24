<?php

use App\Livewire\Profile\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('actualiza el nombre y el correo del perfil', function () {
    $user = User::factory()->create([
        'name' => 'Nombre Anterior',
        'email' => 'anterior@universidad.edu.ec',
    ]);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->set('name', 'Nombre Actualizado')
        ->set('email', 'actualizado@universidad.edu.ec')
        ->call('updateProfile')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Nombre Actualizado')
        ->and($user->email)->toBe('actualizado@universidad.edu.ec')
        ->and($user->email_verified_at)->toBeNull();
});

it('cambia la contraseña exigiendo la contraseña actual', function () {
    $user = User::factory()->create([
        'password' => Hash::make('ClaveActual2026'),
    ]);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->set('current_password', 'ClaveActual2026')
        ->set('password', 'ClaveNueva2026')
        ->set('password_confirmation', 'ClaveNueva2026')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('ClaveNueva2026', $user->fresh()->password))->toBeTrue();
});

it('rechaza un cambio de contraseña con la contraseña actual incorrecta', function () {
    $user = User::factory()->create([
        'password' => Hash::make('ClaveActual2026'),
    ]);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->set('current_password', 'incorrecta')
        ->set('password', 'ClaveNueva2026')
        ->set('password_confirmation', 'ClaveNueva2026')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('ClaveActual2026', $user->fresh()->password))->toBeTrue();
});
