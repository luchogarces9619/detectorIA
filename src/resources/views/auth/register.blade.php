<x-layouts.guest title="Crear cuenta">
    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-aqua-600">Nueva cuenta</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight text-navy-950">Crea tu cuenta</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            Para investigadores, docentes y revisores académicos. Los documentos que cargues serán privados.
        </p>
    </div>

    <livewire:auth.register />

    <p class="mt-8 text-center text-sm text-slate-600">
        ¿Ya tienes una cuenta?
        <a href="{{ route('login') }}" class="font-semibold text-aqua-700 underline-offset-4 hover:underline">Inicia sesión</a>
    </p>
</x-layouts.guest>
