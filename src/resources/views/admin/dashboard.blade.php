<x-layouts.app title="Administración">
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-navy-950">Resumen administrativo</h1>
            <p class="hidden text-xs text-slate-500 sm:block">Usuarios, actividad y estado de la plataforma</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-ui.alert type="success" title="Sistema operativo">
            La plataforma está habilitada para carga de documentos, extracción, detección de IA y plagio.
        </x-ui.alert>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores administrativos">
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Usuarios</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($stats['users'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ number_format($stats['active_users'], 0, ',', '.') }} activos</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documentos</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($stats['documents'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Carga disponible en Fase 2</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Análisis</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($stats['analyses'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ number_format($stats['pending_analyses'], 0, ',', '.') }} en cola o ejecución</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Servicios</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-navy-950">4</p>
                <p class="mt-1 text-xs text-slate-500">Web, worker, PostgreSQL y Redis</p>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="card xl:col-span-2">
                <div class="card-header flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-950">Eventos de seguridad recientes</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Registro de auditoría sin contenido de documentos</p>
                    </div>
                    <x-ui.badge tone="neutral">Últimos 8</x-ui.badge>
                </div>

                @if ($recentEvents->isEmpty())
                    <x-ui.empty-state title="Sin eventos registrados" description="Los inicios de sesión, cambios de rol y demás eventos de seguridad aparecerán aquí." />
                @else
                    <div class="table-shell">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Evento</th>
                                    <th>Usuario</th>
                                    <th>IP</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentEvents as $event)
                                    <tr>
                                        <td><x-ui.badge :tone="$event->actionTone()">{{ $event->actionLabel() }}</x-ui.badge></td>
                                        <td class="max-w-[14rem] truncate text-xs text-slate-600">{{ $event->user?->email ?? 'Sistema / anónimo' }}</td>
                                        <td class="text-xs text-slate-500">{{ $event->ip_address ?? '—' }}</td>
                                        <td class="text-xs text-slate-500">{{ $event->created_at?->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="space-y-4">
                <a href="{{ route('admin.users') }}" class="card block p-5 transition hover:border-aqua-300 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-navy-950">Gestión de usuarios</h2>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500">Consulta cuentas, cambia roles y suspende accesos con registro de auditoría.</p>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-aqua-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </div>
                </a>

                <a href="{{ route('admin.system') }}" class="card block p-5 transition hover:border-aqua-300 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-navy-950">Estado del sistema</h2>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500">Comprueba PostgreSQL, Redis y la comunicación autenticada con FastAPI.</p>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-aqua-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </div>
                </a>

                <div class="card p-5">
                    <h2 class="text-sm font-semibold text-navy-950">Principio de minimización</h2>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-500">
                        La auditoría guarda identificadores, acción, IP y agente de usuario. No guarda texto de documentos ni contraseñas. Los correos fallidos se registran mediante hash.
                    </p>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
