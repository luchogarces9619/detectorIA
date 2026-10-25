<div
    class="space-y-6"
    @if (in_array($document->status->value, ['uploaded', 'validating', 'processing'], true)) wire:poll.8s @endif
>
    @if (session('document_status'))
        <x-ui.alert type="success">{{ session('document_status') }}</x-ui.alert>
    @endif
    @if (session('document_error'))
        <x-ui.alert type="danger">{{ session('document_error') }}</x-ui.alert>
    @endif

    @php
        $tone = match ($document->status->value) {
            'ready' => 'success',
            'failed' => 'danger',
            'processing', 'validating', 'uploaded' => 'warning',
            default => 'neutral',
        };
        $isProcessing = in_array($document->status->value, ['uploaded', 'validating', 'processing'], true);
        $extraction = is_array($document->metadata) ? ($document->metadata['extraction'] ?? []) : [];
        $metadataDoc = is_array($document->metadata) ? $document->metadata : [];
        $analysis = $metadataDoc['analysis'] ?? null;
        $segmentsBySection = $document->segments->groupBy('section_id');
        $predictionsBySegment = [];
        if ($document->relationLoaded('latestAnalysis') && $document->latestAnalysis) {
            foreach ($document->latestAnalysis->predictions as $p) {
                $predictionsBySegment[$p->segment_id] = $p;
            }
        }
    @endphp

    @php
        $showProgress = in_array($document->status, [\App\Enums\DocumentStatus::Uploaded, \App\Enums\DocumentStatus::Validating, \App\Enums\DocumentStatus::Processing], true)
            || ($document->status === \App\Enums\DocumentStatus::Ready && isset($metadataDoc['extraction']) && (!isset($metadataDoc['analysis']) || !isset($metadataDoc['plagiarism'])));
    @endphp
    @if ($showProgress)
    <section class="card" wire:poll.2s>
        <div class="card-header">
            <div>
                <h2 class="text-sm font-semibold text-navy-950">Progreso del análisis</h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ $this->processingProgress['details'] }}</p>
            </div>
        </div>
        <div class="card-body">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-700">{{ $this->processingProgress['stage'] }}</span>
                <span class="text-xs text-slate-500">{{ $this->processingProgress['percent'] }}%</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-2.5">
                <div class="bg-aqua-600 h-2.5 rounded-full transition-all duration-500" style="width: {{ $this->processingProgress['percent'] }}%"></div>
            </div>
            <p class="mt-2 text-xs text-slate-500">Actualización automática cada 2 segundos.</p>
        </div>
    </section>
    @endif

    <section class="card">
        <div class="card-body flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge :tone="$tone">{{ $document->status->label() }}</x-ui.badge>
                    <x-ui.badge tone="neutral">{{ strtoupper($document->extension) }}</x-ui.badge>
                    @if ($extraction['ocr_used'] ?? false)
                        <x-ui.badge tone="info">OCR aplicado</x-ui.badge>
                    @endif
                </div>

                <h2 class="mt-3 break-words text-xl font-bold tracking-tight text-navy-950">{{ $document->original_name }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                    {{ number_format($document->size_bytes / 1024, 1, ',', '.') }} KB ·
                    Cargado el {{ $document->uploaded_at?->format('d/m/Y H:i') ?? $document->created_at?->format('d/m/Y H:i') }}
                    @if ($document->processed_at)
                        · Procesado el {{ $document->processed_at->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('documents.download', $document) }}" class="btn-secondary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Descargar
                </a>
                <button
                    type="button"
                    class="btn-secondary"
                    wire:click="reprocess"
                    wire:loading.attr="disabled"
                    wire:target="reprocess"
                    @disabled($isProcessing)
                >
                    Reprocesar
                </button>
                <button
                    type="button"
                    class="btn-danger"
                    wire:click="delete"
                    wire:confirm="¿Eliminar este documento? El archivo se borrará del almacenamiento privado y no podrá recuperarse."
                    wire:loading.attr="disabled"
                    wire:target="delete"
                >
                    Eliminar
                </button>
            </div>
        </div>
    </section>

    @if ($document->status->value === 'failed')
        <x-ui.alert type="danger" title="No se pudo procesar el documento">
            <p>{{ $document->failure_message ?? 'El procesamiento falló sin un detalle adicional.' }}</p>
            @if ($document->failure_code)
                <p class="mt-1.5 text-xs opacity-80">Código: {{ $document->failure_code }}</p>
            @endif
            <p class="mt-2 text-xs opacity-80">Puedes intentar reprocesarlo cuando el servicio de extracción esté disponible.</p>
        </x-ui.alert>
    @endif

    @if ($isProcessing)
        <x-ui.alert type="info" title="Procesamiento en curso">
            La extracción se ejecuta en segundo plano. Esta página se actualiza automáticamente cada pocos segundos.
        </x-ui.alert>
    @endif

    @if (! empty($extraction['warnings']))
        <x-ui.alert type="warning" title="Advertencias de extracción">
            <ul class="list-inside list-disc space-y-1 text-xs">
                @foreach ($extraction['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @if ($document->status->value === 'ready')
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Métricas de extracción">
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Páginas</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-navy-950">{{ $document->page_count ?? '—' }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $document->page_count ? 'Páginas detectadas' : 'No aplica a DOCX' }}</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Palabras</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($document->word_count, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">En todos los fragmentos</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Párrafos</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($document->paragraph_count, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Párrafos y elementos de lista</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fragmentos</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-navy-950">{{ number_format($document->segments->count(), 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $document->segments->where('is_excluded', true)->count() }} excluidos del análisis</p>
            </div>
        </section>

        @php $plagiarism = is_array($document->metadata) ? ($document->metadata['plagiarism'] ?? null) : null; @endphp
        @if ($plagiarism)
        <section class="card">
            <div class="card-header flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-navy-950">Detección de plagio</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Análisis cuantitativo con porcentaje ponderado del documento</p>
                </div>
                <x-ui.badge :tone="($plagiarism['percentage'] ?? 0) >= 40 ? 'danger' : (($plagiarism['percentage'] ?? 0) >= 20 ? 'warning' : (($plagiarism['percentage'] ?? 0) >= 10 ? 'info' : 'success'))">{{ number_format($plagiarism['percentage'] ?? 0, 2, ',', '.') }} % del documento</x-ui.badge>
            </div>
            <div class="card-body">
                <div class="grid gap-3 sm:grid-cols-3 mb-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Porcentaje ponderado</p>
                        <p class="mt-1 text-lg font-bold text-navy-950">{{ number_format($plagiarism['percentage'] ?? 0, 2, ',', '.') }} %</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Coincidencias</p>
                        <p class="mt-1 text-lg font-bold text-navy-950">{{ $plagiarism['total_matches'] ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fragmentos analizados</p>
                        <p class="mt-1 text-lg font-bold text-navy-950">{{ $plagiarism['fragments_analyzed'] ?? 0 }}</p>
                    </div>
                </div>
                <p class="text-sm text-slate-700">{{ $plagiarism['summary'] ?? '' }}</p>
                @if (! empty($plagiarism['matches']))
                    <div class="mt-3 space-y-2">
                        @foreach ($plagiarism['matches'] as $match)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium text-slate-700">{{ $match['type'] === 'internal' ? 'Coincidencia interna' : 'Coincidencia externa/sospechosa' }}</span>
                                    <span class="text-xs text-slate-500">{{ number_format(($match['score'] ?? 0) * 100, 1, ',', '.') }} % similitud</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-600">{{ $match['text'] ?? '' }}</p>
                                @if (! empty($match['sources']))
                                    <div class="mt-2 space-y-1">
                                        <p class="text-xs font-semibold text-slate-700">Fuentes sugeridas (validadas):</p>
                                        @foreach ($match['sources'] as $source)
                                            <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 text-xs text-aqua-700 hover:underline break-all">
                                                <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                                {{ $source['title'] ?? $source['url'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('documents.report.pdf', $document) }}" class="btn-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Exportar ficha como PDF
            </a>
        </div>

        @if ($analysis)
        <section class="card">
            <div class="card-header flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-navy-950">Análisis de escritura</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Detector calibrado ({{ $analysis['model_version'] ?? 'v1.0.0' }})</p>
                </div>
                @php
                    $confidence = $analysis['confidence'];
                    $tone = $confidence === 'high' ? 'success' : ($confidence === 'medium' ? 'warning' : ($confidence === 'low' ? 'info' : 'neutral'));
                    $confidenceLabel = $analysis['confidence'] === 'insufficient' ? 'Evidencia insuficiente' : ucfirst($analysis['confidence']);
                @endphp
                <x-ui.badge tone="{{ $tone }}">{{ $confidenceLabel }}</x-ui.badge>
            </div>

            <dl class="card-body grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conclusión</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">
                        @php $conc = $analysis['conclusion']; @endphp
                        @if ($conc === 'not_conclusive')
                            No concluyente
                        @elseif ($conc === 'no_signals')
                            Sin señales relevantes
                        @elseif ($conc === 'review_suggested')
                            Revisión humana sugerida
                        @elseif ($conc === 'mixed_signals')
                            Señales mixtas
                        @else
                            {{ $conc }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Puntuación agregada</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">
                        @if ($analysis['aggregate_score'] !== null)
                            {{ number_format($analysis['aggregate_score'], 3, ',', '.') }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cobertura</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ number_format(($analysis['coverage'] ?? 0) * 100, 1, ',', '.') }} %</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Decididos / Abstenidos</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $analysis['decided'] ?? 0 }} / {{ $analysis['abstained'] ?? 0 }}</dd>
                </div>
            </dl>

            <x-ui.alert type="info" title="Información" class="mt-4">
                <p class="text-xs">Los resultados son cuantitativos. Deben ser validados por revisión humana.</p>
            </x-ui.alert>
        </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="card xl:col-span-2">
                <div class="card-header flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-950">Contenido extraído</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $document->sections->count() }} secciones · {{ $document->segments->count() }} fragmentos</p>
                    </div>
                    <x-ui.badge tone="neutral">{{ $extraction['engine'] ?? 'motor desconocido' }}</x-ui.badge>
                </div>

                @if ($document->sections->isEmpty() && $document->segments->isEmpty())
                    <x-ui.empty-state title="Sin contenido extraído" description="El documento se procesó, pero no se encontraron secciones ni fragmentos de texto." />
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($document->sections as $section)
                            @php $sectionSegments = $segmentsBySection->get($section->id, collect()); @endphp
                            <div class="px-5 py-4" wire:key="section-{{ $section->id }}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-aqua-700">{{ $section->type->label() }}</p>
                                        <h3 class="mt-0.5 text-sm font-semibold text-slate-900">{{ $section->title ?? 'Sección sin título' }}</h3>
                                    </div>
                                    <x-ui.badge tone="neutral">{{ $sectionSegments->count() }} fragmentos</x-ui.badge>
                                </div>

                                <div class="mt-3 space-y-3">
                                    @forelse ($sectionSegments as $segment)
                                        @php $prediction = $predictionsBySegment[$segment->id] ?? null; @endphp
                                        <div class="rounded-lg border {{ $segment->is_excluded ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200 bg-white' }} px-4 py-3" wire:key="segment-{{ $segment->id }}">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <x-ui.badge :tone="$segment->is_excluded ? 'warning' : 'neutral'">{{ $segment->kind->label() }}</x-ui.badge>
                                                @if ($segment->is_excluded)
                                                    <span class="text-xs font-medium text-amber-800">Excluido: {{ $segment->exclusion_reason ?? 'sin motivo' }}</span>
                                                @elseif ($prediction)
                                                    <x-ui.badge :tone="$prediction->badgeTone()">{{ $prediction->label->label() }}</x-ui.badge>
                                                    <x-ui.badge tone="neutral">{{ ucfirst($prediction->confidence->value) }}</x-ui.badge>
                                                    <span class="text-xs text-slate-500">Score: {{ number_format($prediction->score, 3, ',', '.') }}</span>
                                                    @if ($prediction->requires_review)
                                                        <x-ui.badge tone="info">Requiere revisión</x-ui.badge>
                                                    @endif
                                                @endif
                                                <span class="ml-auto text-xs text-slate-400">{{ $segment->word_count }} palabras</span>
                                            </div>
                                            <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed {{ $segment->is_excluded ? 'text-slate-500' : 'text-slate-700' }}">{{ $segment->text }}</p>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400">Esta sección no tiene fragmentos asociados.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach

                        @php $unsectioned = $document->segments->whereNull('section_id'); @endphp
                        @if ($unsectioned->isNotEmpty())
                            <div class="px-5 py-4">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Sin sección</p>
                                <div class="mt-3 space-y-3">
                                    @foreach ($unsectioned as $segment)
                                        @php $prediction = $predictionsBySegment[$segment->id] ?? null; @endphp
                                        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3" wire:key="segment-unsectioned-{{ $segment->id }}">
                                            <div class="flex items-center gap-2">
                                                <x-ui.badge tone="neutral">{{ $segment->kind->label() }}</x-ui.badge>
                                                @if ($prediction)
                                                    <x-ui.badge :tone="$prediction->badgeTone()">{{ $prediction->label->label() }}</x-ui.badge>
                                                    <x-ui.badge tone="neutral">{{ ucfirst($prediction->confidence->value) }}</x-ui.badge>
                                                    <span class="text-xs text-slate-500">Score: {{ number_format($prediction->score, 3, ',', '.') }}</span>
                                                    @if ($prediction->requires_review)
                                                        <x-ui.badge tone="info">Requiere revisión</x-ui.badge>
                                                    @endif
                                                @endif
                                                <span class="ml-auto text-xs text-slate-400">{{ $segment->word_count }} palabras</span>
                                            </div>
                                            <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-700">{{ $segment->text }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-navy-950">Ficha técnica</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Trazabilidad del archivo y de la extracción</p>
                </div>
                <dl class="card-body space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo MIME</dt>
                        <dd class="mt-0.5 break-all text-xs text-slate-700">{{ $document->mime_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">SHA-256</dt>
                        <dd class="mt-0.5 break-all font-mono text-[11px] leading-relaxed text-slate-600">{{ $document->sha256 }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Almacenamiento</dt>
                        <dd class="mt-0.5 break-all text-xs text-slate-700">{{ $document->storage_disk }} · {{ $document->storage_path }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Extracción</dt>
                        <dd class="mt-0.5 text-xs text-slate-700">
                            {{ $extraction['engine'] ?? '—' }}
                            @if (! empty($extraction['ocr_pages']))
                                · OCR en páginas {{ implode(', ', $extraction['ocr_pages']) }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Consentimiento</dt>
                        <dd class="mt-0.5 text-xs text-slate-700">
                            {{ ($document->metadata['consent'] ?? false) ? 'Registrado al cargar el archivo' : 'No registrado' }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    @endif

    <x-ui.alert type="info" title="Análisis automatizado">
        Los resultados son cuantitativos y deben ser validados por revisión humana.
    </x-ui.alert>
</div>
