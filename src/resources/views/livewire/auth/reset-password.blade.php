<form wire:submit="resetPassword" class="space-y-4" novalidate>
    <div>
        <label for="email" class="label">Correo electrónico</label>
        <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" required class="input @error('email') input-error @enderror">
        @error('email')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div x-data="{ show: false }">
        <label for="password" class="label">Nueva contraseña</label>
        <div class="relative">
            <input id="password" :type="show ? 'text' : 'password'" wire:model="password" autocomplete="new-password" required class="input pr-12 @error('password') input-error @enderror" placeholder="Mínimo 12 caracteres">
            <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600" @click="show = !show" :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
            </button>
        </div>
        @error('password')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="password_confirmation" class="label">Confirmar nueva contraseña</label>
        <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="input" placeholder="Repite la contraseña">
    </div>

    <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="resetPassword">
        <span wire:loading.remove wire:target="resetPassword">Guardar nueva contraseña</span>
        <span wire:loading wire:target="resetPassword">Guardando…</span>
    </button>
</form>
