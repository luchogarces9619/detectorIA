<x-layouts.app :title="$document->original_name">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('documents.index') }}" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900" aria-label="Volver a documentos">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-semibold tracking-tight text-navy-950">{{ $document->original_name }}</h1></h1>
                <p class="hidden text-xs text-slate-500 sm:block">Detalle y extracción del documento</p>
            </div>
        </div>
    </x-slot>

    @livewire('documents.document-detail', ['document' => $document])
</x-layouts.app>
