<x-layouts.guest title="Recuperar contraseña">
    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-aqua-600">Recuperación</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight text-navy-950">Restablece tu contraseña</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            Indica tu correo y te enviaremos un enlace de un solo uso. Por seguridad, no confirmamos si el correo está registrado.
        </p>
    </div>

    <livewire:auth.forgot-password />

    <p class="mt-8 text-center text-sm text-slate-600">
        <a href="{{ route('login') }}" class="font-semibold text-aqua-700 underline-offset-4 hover:underline">Volver al inicio de sesión</a>
    </p>
</x-layouts.guest>
