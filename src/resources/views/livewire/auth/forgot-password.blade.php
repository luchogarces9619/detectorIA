<form wire:submit="send" class="space-y-5" novalidate>
    @if ($statusMessage !== '')
        <x-ui.alert type="success">{{ $statusMessage }}</x-ui.alert>
    @endif

    <div>
        <label for="email" class="label">Correo electrónico</label>
        <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" required autofocus class="input @error('email') input-error @enderror" placeholder="nombre@universidad.edu.ec">
        @error('email')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="send">
        <span wire:loading.remove wire:target="send">Enviar enlace de recuperación</span>
        <span wire:loading wire:target="send">Enviando…</span>
    </button>

    <p class="text-center text-xs leading-relaxed text-slate-400">
        En desarrollo, los correos se capturan en Mailpit (http://localhost:8025) y no se envían a Internet.
    </p>
</form>
