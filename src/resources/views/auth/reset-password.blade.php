<x-layouts.guest title="Nueva contraseña">
    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-aqua-600">Seguridad</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight text-navy-950">Define una nueva contraseña</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            El enlace es de un solo uso. Si expiró, solicita uno nuevo desde la pantalla de recuperación.
        </p>
    </div>

    <livewire:auth.reset-password :token="$token" />

    <p class="mt-8 text-center text-sm text-slate-600">
        <a href="{{ route('login') }}" class="font-semibold text-aqua-700 underline-offset-4 hover:underline">Volver al inicio de sesión</a>
    </p>
</x-layouts.guest>
