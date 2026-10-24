<?php

namespace App\Livewire\Auth;

use App\Enums\UserStatus;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:72'],
            'remember' => ['boolean'],
        ], attributes: [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        $throttleKey = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.");

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        if (! Auth::attempt([
            'email' => $this->email,
            'password' => $this->password,
            'status' => UserStatus::Active->value,
        ], $this->remember)) {
            $this->addError('email', 'Las credenciales no coinciden o la cuenta no está activa.');

            app(AuditLogger::class)->log('auth.login_failed', metadata: [
                'email_hash' => hash('sha256', $this->email),
            ]);

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        app(AuditLogger::class)->log('auth.login', $user);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }

    private function throttleKey(): string
    {
        return 'login|'.Str::lower($this->email).'|'.request()->ip();
    }
}
