<form wire:submit="register" class="space-y-4" novalidate>
    <div>
        <label for="name" class="label">Nombre completo</label>
        <input id="name" type="text" wire:model="name" autocomplete="name" required autofocus class="input @error('name') input-error @enderror" placeholder="Ej. María Fernanda Pérez">
        @error('name')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="label">Correo institucional o personal</label>
        <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" required class="input @error('email') input-error @enderror" placeholder="nombre@universidad.edu.ec">
        @error('email')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div x-data="{ show: false }">
        <label for="password" class="label">Contraseña</label>
        <div class="relative">
            <input id="password" :type="show ? 'text' : 'password'" wire:model="password" autocomplete="new-password" required class="input pr-12 @error('password') input-error @enderror" placeholder="Mínimo 12 caracteres">
            <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600" @click="show = !show" :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
            </button>
        </div>
        <p class="hint">Usa al menos 12 caracteres, con letras y números. No reutilices contraseñas de otros servicios.</p>
        @error('password')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="password_confirmation" class="label">Confirmar contraseña</label>
        <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="input" placeholder="Repite la contraseña">
    </div>

    <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50/60 p-3 text-xs leading-relaxed text-slate-600">
        <input type="checkbox" wire:model="accept_terms" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-aqua-600 focus:ring-aqua-500">
        <span>
            Acepto la política de tratamiento de datos personales y entiendo que los resultados son orientativos, no una prueba de autoría.
        </span>
    </label>
    @error('accept_terms')<p class="error-text">{{ $message }}</p>@enderror

    <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="register">
        <span wire:loading.remove wire:target="register">Crear cuenta</span>
        <span wire:loading wire:target="register">Creando cuenta…</span>
    </button>
</form>
