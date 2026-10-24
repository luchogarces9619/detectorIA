<x-layouts.app title="Documentos">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="truncate text-lg font-semibold tracking-tight text-navy-950">Documentos</h1>
                <p class="hidden text-xs text-slate-500 sm:block">Carga privada, extracción y segmentación</p>
            </div>
            <a href="{{ route('documents.upload') }}" class="btn-primary shrink-0">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span class="hidden sm:inline">Cargar documento</span>
                <span class="sm:hidden">Cargar</span>
            </a>
        </div>
    </x-slot>

    <div class="space-y-4">
        <x-ui.alert type="info" title="Privacidad documental">
            Los archivos se almacenan fuera de la carpeta pública y solo su propietario puede consultarlos. El contenido no se usa para entrenar modelos sin autorización explícita.
        </x-ui.alert>

        @livewire('documents.document-list')
    </div>
</x-layouts.app>
