<div class="space-y-6">
    @if (session('status'))
        <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
    @endif

    <section class="card">
        <div class="card-header">
            <h2 class="text-sm font-semibold text-navy-950">Archivo académico</h2>
            <p class="mt-0.5 text-xs text-slate-500">El texto se extrae y segmenta para las siguientes fases del análisis.</p>
        </div>

        <form wire:submit="save" class="card-body space-y-5" novalidate>
            <div>
                <input
                    id="document-file"
                    type="file"
                    wire:model="file"
                    accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    class="sr-only"
                >
                <label for="document-file" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center transition hover:border-aqua-400 hover:bg-aqua-50/40">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-navy-600 shadow-sm">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/></svg>
                    </span>
                    <span class="mt-4 text-sm font-semibold text-slate-900">
                        @if ($file)
                            {{ $file->getClientOriginalName() }}
                        @else
                            Selecciona o arrastra un archivo
                        @endif
                    </span>
                    <span class="mt-1 text-xs text-slate-500">DOCX o PDF · máximo {{ number_format($maxKb / 1024, 0, ',', '.') }} MB</span>
                </label>

                <div wire:loading wire:target="file" class="mt-3 flex items-center gap-2 text-xs font-medium text-aqua-700">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    Subiendo archivo…
                </div>

                @error('file')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <label for="document-consent" class="flex items-start gap-3 text-sm text-slate-700">
                    <input id="document-consent" type="checkbox" wire:model="consent" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-aqua-600 focus:ring-aqua-500">
                    <span class="leading-relaxed">
                        Declaro que tengo autorización para cargar este documento y que se procese únicamente para el análisis solicitado. Entiendo que el contenido no se usará para entrenar modelos sin una autorización adicional.
                    </span>
                </label>
                @error('consent')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <x-ui.alert type="info" title="Almacenamiento privado">
                El archivo se guarda en el disco privado de la aplicación, con acceso restringido a tu cuenta. Puedes eliminarlo en cualquier momento desde el detalle del documento.
            </x-ui.alert>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('documents.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Cargar y procesar</span>
                    <span wire:loading wire:target="save">Procesando…</span>
                </button>
            </div>
        </form>
    </section>

    <section class="card p-5">
        <h2 class="text-sm font-semibold text-navy-950">Qué ocurre después</h2>
        <ol class="mt-3 space-y-2 text-xs leading-relaxed text-slate-500">
            <li><span class="font-semibold text-slate-700">1.</span> Se valida el tamaño, la extensión y la firma real del archivo.</li>
            <li><span class="font-semibold text-slate-700">2.</span> El documento se guarda con su hash SHA-256 en almacenamiento privado.</li>
            <li><span class="font-semibold text-slate-700">3.</span> Un trabajo en segundo plano solicita la extracción al motor de IA.</li>
            <li><span class="font-semibold text-slate-700">4.</span> Se guardan secciones y fragmentos; tablas y referencias se marcan como excluidas.</li>
        </ol>
    </section>
</div>
