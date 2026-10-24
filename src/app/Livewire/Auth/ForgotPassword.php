<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public string $statusMessage = '';

    public function send(): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
        ], attributes: [
            'email' => 'correo electrónico',
        ]);

        $throttleKey = 'password-email|'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $this->statusMessage = 'Si el correo está registrado, recibirás un enlace en unos minutos.';

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        Password::sendResetLink(['email' => $this->email]);

        // Misma respuesta para correos existentes y no existentes.
        $this->statusMessage = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';
        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
