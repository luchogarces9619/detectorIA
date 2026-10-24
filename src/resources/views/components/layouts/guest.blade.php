@props(['title' => 'Acceso'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ config('app.name', 'SciTrace AI') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-slate-50">
    <div class="min-h-screen lg:grid lg:grid-cols-[1.02fr_1fr]">
        <aside class="relative hidden overflow-hidden bg-navy-950 px-12 py-12 lg:flex lg:flex-col xl:px-16">
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-aqua-500/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-navy-600/30 blur-3xl"></div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.06]" style="background-image: linear-gradient(rgba(255,255,255,.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.5) 1px, transparent 1px); background-size: 48px 48px;"></div>

            <div class="relative">
                <x-brand.logo variant="dark" :show-tagline="true" />
            </div>

            <div class="relative my-auto max-w-lg py-12">
                <span class="inline-flex items-center gap-2 rounded-full border border-aqua-400/30 bg-aqua-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-aqua-300">
                    Análisis orientativo
                </span>
                <h1 class="mt-6 text-4xl font-bold leading-[1.15] tracking-tight text-white xl:text-[2.75rem]">
                    Trazabilidad para la escritura académica en la era de la IA.
                </h1>
                <p class="mt-5 text-base leading-relaxed text-navy-200">
                    Analiza documentos académicos, identifica fragmentos que requieren revisión humana y conserva la evidencia metodológica de cada resultado.
                </p>

                <ul class="mt-9 space-y-4 text-sm text-navy-100">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-aqua-400/15 text-aqua-300">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        Procesamiento privado de tesis, artículos y protocolos.
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-aqua-400/15 text-aqua-300">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        Resultados explicables, con limitaciones documentadas.
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-aqua-400/15 text-aqua-300">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        Ningún documento se usa para entrenar modelos sin autorización.
                    </li>
                </ul>
            </div>

            <p class="relative max-w-md text-xs leading-relaxed text-navy-300">
                SciTrace AI no determina autoría. Los resultados son indicios estadísticos que deben ser interpretados por una persona.
            </p>
        </aside>

        <main class="flex min-h-screen flex-col">
            <div class="flex items-center justify-between px-5 pt-6 sm:px-8 lg:hidden">
                <x-brand.logo variant="light" />
            </div>

            <div class="flex flex-1 items-center justify-center px-5 py-10 sm:px-8 lg:py-14">
                <div class="w-full max-w-md">
                    <x-ui.flash />
                    {{ $slot }}
                </div>
            </div>

            <footer class="px-5 pb-6 text-center text-xs leading-relaxed text-slate-500 sm:px-8">
                <p>SciTrace AI · Resultado orientativo, no una prueba de autoría.</p>
            </footer>
        </main>
    </div>

    @livewireScripts
</body>
</html>
