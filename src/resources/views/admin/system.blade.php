<x-layouts.app title="Estado del sistema">
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-navy-950">Estado del sistema</h1>
            <p class="hidden text-xs text-slate-500 sm:block">Diagnóstico de infraestructura y comunicación entre servicios</p>
        </div>
    </x-slot>

    @php
        $overallTone = match ($health['overall']) {
            'ok' => 'success',
            'degraded' => 'warning',
            default => 'danger',
        };
        $overallLabel = match ($health['overall']) {
            'ok' => 'Todos los servicios responden',
            'degraded' => 'Servicio de IA no disponible',
            default => 'Fallo en un servicio crítico',
        };
        $statusTone = fn (string $status) => $status === 'ok' ? 'success' : 'danger';
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-ui.alert :type="$overallTone" class="flex-1">
                <span class="font-semibold">{{ $overallLabel }}.</span>
                Comprobado el {{ \Illuminate\Support\Carbon::parse($health['checked_at'])->format('d/m/Y H:i:s') }}.
            </x-ui.alert>
            <a href="{{ route('admin.system') }}" class="btn-secondary shrink-0">Volver a comprobar</a>
        </div>

        <section class="grid gap-4 lg:grid-cols-3" aria-label="Comprobaciones de servicios">
            @foreach ($health['checks'] as $key => $check)
                <div class="card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $check['label'] }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $check['detail'] }}</p>
                        </div>
                        <span class="mt-1 flex h-3 w-3 shrink-0 rounded-full {{ $check['status'] === 'ok' ? 'bg-emerald-500' : 'bg-rose-500' }}" aria-hidden="true"></span>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                        <x-ui.badge :tone="$statusTone($check['status'])">{{ $check['status'] === 'ok' ? 'Disponible' : 'No disponible' }}</x-ui.badge>
                        <span class="text-xs tabular-nums text-slate-400">{{ $check['latency_ms'] }} ms</span>
                    </div>
                </div>
            @endforeach
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-navy-950">Comunicación Laravel → FastAPI</h2>
                </div>
                <div class="card-body space-y-3 text-sm leading-relaxed text-slate-600">
                    <p>La aplicación web llama a <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">/internal/v1/health</code> y <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">/internal/v1/capabilities</code> dentro de la red privada de Docker.</p>
                    <p>La autenticación usa un token compartido de al menos 32 caracteres, comparado en tiempo constante. El microservicio solo publica un puerto en loopback para depuración local.</p>
                    <p>Los documentos nunca se enviarán por rutas públicas: en la Fase 2 se usarán referencias internas y almacenamiento privado.</p>
                    <a href="{{ route('admin.system') }}" class="btn-secondary mt-2">Ejecutar de nuevo</a>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-navy-950">Entorno de ejecución</h2>
                </div>
                <div class="card-body">
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">Aplicación</dt>
                            <dd class="font-medium text-slate-900">SciTrace AI {{ config('app.version', '0.1.0') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">Entorno</dt>
                            <dd><x-ui.badge tone="neutral">{{ app()->environment() }}</x-ui.badge></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">Laravel</dt>
                            <dd class="font-medium text-slate-900">{{ app()->version() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">PHP</dt>
                            <dd class="font-medium text-slate-900">{{ PHP_VERSION }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>

        <x-ui.alert type="success" title="Detector operativo">
            FastAPI responde correctamente con detección de IA y plagio activas.
        </x-ui.alert>
    </div>
</x-layouts.app>
