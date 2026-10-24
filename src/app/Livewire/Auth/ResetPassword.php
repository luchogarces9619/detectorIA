<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Component;

class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = Str::lower((string) request()->query('email', ''));
    }

    public function resetPassword(): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed'],
        ], attributes: [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        $status = Password::reset([
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'token' => $this->token,
        ], function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));

            app(AuditLogger::class)->log('auth.password_reset', $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', 'El enlace no es válido o ha expirado. Solicita uno nuevo.');

            return;
        }

        $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
