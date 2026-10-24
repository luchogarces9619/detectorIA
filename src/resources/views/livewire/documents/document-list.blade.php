<div class="space-y-4">
    @if (session('status'))
        <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
    @endif

    <section class="card">
        <div class="card-body border-b border-slate-100">
            <div class="grid gap-3 sm:grid-cols-[1fr_12rem]">
                <div>
                    <label for="document-search" class="label">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        </span>
                        <input id="document-search" type="search" wire:model.live.debounce.300ms="search" class="input pl-10" placeholder="Nombre del documento">
                    </div>
                </div>
                <div>
                    <label for="document-status" class="label">Estado</label>
                    <select id="document-status" wire:model.live="statusFilter" class="input">
                        <option value="">Todos</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($documents->isEmpty())
            <x-ui.empty-state
                title="{{ $search !== '' || $statusFilter !== '' ? 'Sin documentos que coincidan' : 'Aún no tienes documentos' }}"
                description="{{ $search !== '' || $statusFilter !== '' ? 'Ajusta la búsqueda o el filtro para ver otros documentos.' : 'Carga un DOCX o PDF para extraer su texto, secciones y fragmentos.' }}"
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
                            <th>Páginas</th>
                            <th>Palabras</th>
                            <th>Cargado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            @php
                                $tone = match ($document->status->value) {
                                    'ready' => 'success',
                                    'failed' => 'danger',
                                    'processing', 'validating', 'uploaded' => 'warning',
                                    default => 'neutral',
                                };
                            @endphp
                            <tr wire:key="document-{{ $document->id }}">
                                <td class="max-w-xs">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-[10px] font-bold uppercase tracking-wide text-navy-700">
                                            {{ $document->extension }}
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('documents.show', $document) }}" class="block truncate text-sm font-medium text-slate-900 hover:text-aqua-700">
                                                {{ $document->original_name }}
                                            </a>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ number_format($document->size_bytes / 1024, 1, ',', '.') }} KB
                                                @if ($document->failure_code)
                                                    · {{ $document->failure_code }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td><x-ui.badge :tone="$tone">{{ $document->status->label() }}</x-ui.badge></td>
                                <td class="tabular-nums">{{ $document->page_count ?? '—' }}</td>
                                <td class="tabular-nums">{{ number_format($document->word_count, 0, ',', '.') }}</td>
                                <td class="text-xs text-slate-500">{{ $document->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('documents.show', $document) }}" class="btn-secondary px-3 py-1.5 text-xs">Ver</a>
                                        <button
                                            type="button"
                                            class="btn-danger px-3 py-1.5 text-xs"
                                            wire:click="delete('{{ $document->id }}')"
                                            wire:confirm="¿Eliminar «{{ $document->original_name }}»? El archivo se borrará del almacenamiento privado."
                                            wire:loading.attr="disabled"
                                            wire:target="delete('{{ $document->id }}')"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($documents->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $documents->links() }}
                </div>
            @endif
        @endif
    </section>

    <p class="px-1 text-xs text-slate-500">
        Mostrando {{ $documents->count() }} de {{ $documents->total() }} documentos. Solo tú puedes acceder a estos archivos.
    </p>
</div>
