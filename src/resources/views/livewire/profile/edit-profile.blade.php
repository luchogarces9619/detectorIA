<div class="space-y-6">
    @if (session('profile_status'))
        <x-ui.alert type="success">{{ session('profile_status') }}</x-ui.alert>
    @endif
    @if (session('password_status'))
        <x-ui.alert type="success">{{ session('password_status') }}</x-ui.alert>
    @endif

    <section class="card">
        <div class="card-header">
            <h2 class="text-sm font-semibold text-navy-950">Información de la cuenta</h2>
            <p class="mt-0.5 text-xs text-slate-500">Tu nombre y correo se usan para identificar tus documentos.</p>
        </div>
        <form wire:submit="updateProfile" class="card-body space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="label">Nombre completo</label>
                    <input id="name" type="text" wire:model="name" autocomplete="name" required class="input @error('name') input-error @enderror">
                    @error('name')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="label">Correo electrónico</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" required class="input @error('email') input-error @enderror">
                    @error('email')<p class="error-text">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs text-slate-500">
                <x-ui.badge :tone="auth()->user()->isAdmin() ? 'aqua' : 'neutral'">{{ auth()->user()->role->label() }}</x-ui.badge>
                <span>·</span>
                <span>Cuenta {{ auth()->user()->status->label() }}</span>
            </div>

            <div class="flex justify-end border-t border-slate-100 pt-4">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="updateProfile">
                    <span wire:loading.remove wire:target="updateProfile">Guardar cambios</span>
                    <span wire:loading wire:target="updateProfile">Guardando…</span>
                </button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="text-sm font-semibold text-navy-950">Cambiar contraseña</h2>
            <p class="mt-0.5 text-xs text-slate-500">Mínimo 12 caracteres, con letras y números.</p>
        </div>
        <form wire:submit="updatePassword" class="card-body space-y-4" novalidate>
            <div>
                <label for="current_password" class="label">Contraseña actual</label>
                <input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" required class="input @error('current_password') input-error @enderror">
                @error('current_password')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="label">Nueva contraseña</label>
                    <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="input @error('password') input-error @enderror">
                    @error('password')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirmar nueva contraseña</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="input">
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-100 pt-4">
                <button type="submit" class="btn-secondary" wire:loading.attr="disabled" wire:target="updatePassword">
                    <span wire:loading.remove wire:target="updatePassword">Actualizar contraseña</span>
                    <span wire:loading wire:target="updatePassword">Actualizando…</span>
                </button>
            </div>
        </form>
    </section>

    <x-ui.alert type="info" title="Privacidad">
        Tus documentos serán privados y solo tú podrás consultarlos. SciTrace AI no usará tus archivos para entrenar modelos sin tu autorización expresa.
    </x-ui.alert>
</div>
