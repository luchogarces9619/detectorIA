<x-layouts.app title="Panel principal">
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-navy-950">Panel principal</h1>
            <p class="hidden text-xs text-slate-500 sm:block">Resumen de tu actividad en SciTrace AI</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="relative overflow-hidden rounded-2xl bg-navy-950 px-6 py-7 text-white shadow-sm sm:px-8">
            <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-aqua-500/15 blur-3xl"></div>
            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl">
                    <x-ui.badge tone="success" class="border border-emerald-400/20 bg-emerald-400/10 text-emerald-300">Fase 3 · Detector calibrado</x-ui.badge>
                    <h2 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Bienvenido, {{ auth()->user()->name }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-navy-200">
                        Detector calibrado scitrace-es-academic-detector v1.0.0 operativo. Detección de IA con umbrales calibrados y análisis cuantitativo de plagio disponibles.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('documents.upload') }}" class="btn-primary">Cargar documento</a>
                        <a href="{{ route('documents.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-aqua-400 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950">
                            Ver documentos
                        </a>
                    </div>
                </div>
                <div class="shrink-0 rounded-xl border border-white/10 bg-white/5 px-5 py-4 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-navy-300">Motor de análisis</p>
                    <p class="mt-1.5 flex items-center gap-2 font-semibold text-white">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        Operativo
                    </p>
                    <p class="mt-1 text-xs text-navy-300">Modelo calibrado v1.0.0</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores">
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documentos</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-navy-50 text-navy-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($stats['documents'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Documentos cargados</p>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Análisis</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-aqua-50 text-aqua-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($stats['analyses'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Análisis solicitados</p>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">En proceso</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($stats['pending'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">En cola o ejecución</p>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Por revisar</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 3.94l-7.5 13A1.5 1.5 0 0 0 4.14 19.5h15.72a1.5 1.5 0 0 0 1.3-2.25l-7.5-13a1.5 1.5 0 0 0-2.6 0Z"/></svg>
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($stats['review'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Fragmentos señalados</p>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="card xl:col-span-2">
                <div class="card-header flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-950">Documentos recientes</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Últimas cargas y su estado de extracción</p>
                    </div>
                    <a href="{{ route('documents.index') }}" class="text-xs font-semibold text-aqua-700 transition hover:text-aqua-800">Ver todos</a>
                </div>
                @if ($recentDocuments->isEmpty())
                    <x-ui.empty-state
                        title="Aún no hay documentos"
                        description="Carga un DOCX o PDF para extraer su texto, secciones y fragmentos. El archivo se guarda en almacenamiento privado."
                    >
                        <x-slot name="action">
                            <a href="{{ route('documents.upload') }}" class="btn-primary">Cargar documento</a>
                        </x-slot>
                    </x-ui.empty-state>
                @else
                    <div class="table-shell">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Estado</th>
                                    <th>Fragmentos</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentDocuments as $document)
                                    @php
                                        $tone = match ($document->status->value) {
                                            'ready' => 'success',
                                            'failed' => 'danger',
                                            'processing', 'validating', 'uploaded' => 'warning',
                                            default => 'neutral',
                                        };
                                    @endphp
                                    <tr wire:key="dashboard-document-{{ $document->id }}">
                                        <td class="max-w-xs truncate">
                                            <a href="{{ route('documents.show', $document) }}" class="font-medium text-slate-900 hover:text-aqua-700">{{ $document->original_name }}</a>
                                        </td>
                                        <td><x-ui.badge :tone="$tone">{{ $document->status->label() }}</x-ui.badge></td>
                                        <td class="tabular-nums">{{ number_format($document->segments_count, 0, ',', '.') }}</td>
                                        <td class="text-xs text-slate-500">{{ $document->created_at?->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-navy-950">Estado del proyecto</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Alcance implementado y siguiente etapa</p>
                </div>
                <div class="card-body space-y-4">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Fundamentos</p>
                            <p class="text-xs leading-relaxed text-slate-500">Laravel, PostgreSQL, Redis, FastAPI y autenticación con roles.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Gestión documental</p>
                            <p class="text-xs leading-relaxed text-slate-500">Carga privada, extracción DOCX/PDF, OCR opcional y segmentación.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Detector calibrado</p>
                            <p class="text-xs leading-relaxed text-slate-500">Entrenamiento, evaluación, umbrales y abstención. Operativo.</p>
                        </div>
                    </div>

                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.system') }}" class="btn-secondary w-full">Ver estado técnico</a>
                    @endif
                </div>
            </section>
        </div>

        <section class="grid gap-4 md:grid-cols-3" aria-label="Cómo funciona">
            <div class="card p-5">
                <span class="text-xs font-bold text-aqua-600">01</span>
                <h3 class="mt-2 text-sm font-semibold text-navy-950">Carga privada</h3>
                <p class="mt-1.5 text-xs leading-relaxed text-slate-500">El documento se valida y almacena fuera de la carpeta pública, con acceso restringido a su propietario.</p>
            </div>
            <div class="card p-5">
                <span class="text-xs font-bold text-aqua-600">02</span>
                <h3 class="mt-2 text-sm font-semibold text-navy-950">Análisis por fragmentos</h3>
                <p class="mt-1.5 text-xs leading-relaxed text-slate-500">El texto se segmenta y cada fragmento recibe indicadores explicables, no una sentencia de autoría.</p>
            </div>
            <div class="card p-5">
                <span class="text-xs font-bold text-aqua-600">03</span>
                <h3 class="mt-2 text-sm font-semibold text-navy-950">Revisión humana</h3>
                <p class="mt-1.5 text-xs leading-relaxed text-slate-500">La persona investigadora interpreta los indicios junto con el contexto disciplinar y las limitaciones del modelo.</p>
            </div>
        </section>

        <x-ui.alert type="warning" title="Limitación metodológica">
            SciTrace AI no afirma que un texto haya sido escrito por IA. Un fragmento señalado solo indica que ciertos patrones estadísticos merecen revisión. No uses este resultado como sanción, dictamen ni prueba pericial.
        </x-ui.alert>
    </div>
</x-layouts.app>
