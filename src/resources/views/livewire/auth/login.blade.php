<form wire:submit="login" class="space-y-5" novalidate>
    <div>
        <label for="email" class="label">Correo electrónico</label>
        <input
            id="email"
            type="email"
            wire:model="email"
            autocomplete="username"
            inputmode="email"
            required
            autofocus
            class="input @error('email') input-error @enderror"
            placeholder="nombre@universidad.edu.ec"
        >
        @error('email')
            <p class="error-text">{{ $message }}</p>
        @enderror
    </div>

    <div x-data="{ show: false }">
        <div class="flex items-center justify-between gap-3">
            <label for="password" class="label mb-1.5">Contraseña</label>
            <a href="{{ route('password.request') }}" class="text-xs font-semibold text-aqua-700 underline-offset-4 hover:underline">¿La olvidaste?</a>
        </div>
        <div class="relative">
            <input
                id="password"
                :type="show ? 'text' : 'password'"
                wire:model="password"
                autocomplete="current-password"
                required
                class="input pr-12 @error('password') input-error @enderror"
                placeholder="Tu contraseña"
            >
            <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600" @click="show = !show" :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
            </button>
        </div>
        @error('password')
            <p class="error-text">{{ $message }}</p>
        @enderror
    </div>

    <label class="flex items-center gap-2.5 text-sm text-slate-600">
        <input type="checkbox" wire:model="remember" class="h-4 w-4 rounded border-slate-300 text-aqua-600 focus:ring-aqua-500">
        Mantener la sesión en este dispositivo
    </label>

    <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="login">
        <svg wire:loading.remove wire:target="login" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg>
        <svg wire:loading wire:target="login" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 0-9-9v3a6 6 0 0 1 6 6h3Z"/></svg>
        <span wire:loading.remove wire:target="login">Entrar a SciTrace AI</span>
        <span wire:loading wire:target="login">Verificando…</span>
    </button>
</form>
