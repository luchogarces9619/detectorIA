<?php

namespace App\Livewire\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_terms = false;

    public function register(): void
    {
        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));

        $throttleKey = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('email', 'Demasiados intentos de registro. Espera unos minutos.');

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:72',
                'confirmed',
                Password::min(12)->letters()->numbers(),
            ],
            'accept_terms' => ['accepted'],
        ], attributes: [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'accept_terms' => 'aceptación de términos',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => UserRole::User,
            'status' => UserStatus::Active,
            'locale' => app()->getLocale(),
        ]);

        Auth::login($user);
        session()->regenerate();

        app(AuditLogger::class)->log('auth.registered', $user);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
