<?php

namespace App\Livewire\Profile;

use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class EditProfile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfile(): void
    {
        $user = Auth::user();
        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
        ], attributes: [
            'name' => 'nombre',
            'email' => 'correo electrónico',
        ]);

        $emailChanged = $user->email !== $validated['email'];

        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        app(AuditLogger::class)->log('profile.updated', $user, $user, [
            'email_changed' => $emailChanged,
        ]);

        session()->flash('profile_status', 'Perfil actualizado correctamente.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:72',
                'confirmed',
                Password::min(12)->letters()->numbers(),
            ],
        ], attributes: [
            'current_password' => 'contraseña actual',
            'password' => 'nueva contraseña',
        ]);

        $user = Auth::user();
        $user->forceFill([
            'password' => Hash::make($this->password),
            'remember_token' => Str::random(60),
        ])->save();

        $this->reset('current_password', 'password', 'password_confirmation');

        app(AuditLogger::class)->log('profile.password_changed', $user, $user);

        session()->flash('password_status', 'Contraseña actualizada. Las demás sesiones se cerrarán al expirar.');
    }

    public function render()
    {
        return view('livewire.profile.edit-profile');
    }
}
