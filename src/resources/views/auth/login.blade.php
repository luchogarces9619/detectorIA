<x-layouts.guest title="Iniciar sesión">
    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-aqua-600">Acceso seguro</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight text-navy-950">Inicia sesión</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            Ingresa con tu cuenta para revisar tus documentos y análisis.
        </p>
    </div>

    <livewire:auth.login />

    <p class="mt-8 text-center text-sm text-slate-600">
        ¿Aún no tienes una cuenta?
        <a href="{{ route('register') }}" class="font-semibold text-aqua-700 underline-offset-4 hover:underline">Solicita acceso</a>
    </p>

    <p class="mt-6 text-center text-[11px] leading-relaxed text-slate-400">
        Al continuar aceptas que SciTrace AI procesa documentos académicos con fines de revisión orientativa.
    </p>
</x-layouts.guest>
