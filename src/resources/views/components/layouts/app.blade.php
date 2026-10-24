@props(['title' => 'Panel'])

@php
    $user = auth()->user();
    $initials = collect(explode(' ', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : 'U';
@endphp

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
<body class="min-h-full bg-slate-50" x-data="{ sidebarOpen: false }">
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-navy-950/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false" aria-hidden="true"></div>

    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-navy-950 transition-transform duration-200 ease-out lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        aria-label="Navegación principal"
    >
        <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
            <x-brand.logo variant="dark" :href="route('dashboard')" />
            <button type="button" class="rounded-lg p-2 text-navy-300 hover:bg-white/10 hover:text-white lg:hidden" @click="sidebarOpen = false" aria-label="Cerrar menú">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 space-y-7 overflow-y-auto px-3 py-6">
            <div>
                <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-navy-400">Espacio de trabajo</p>
                <div class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'sidebar-link-active' : '' }}" @click="sidebarOpen = false">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 8.25 20.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
                        Panel principal
                    </a>
                    <a href="{{ route('documents.index') }}" class="sidebar-link {{ request()->routeIs('documents.*') ? 'sidebar-link-active' : '' }}" @click="sidebarOpen = false">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        <span class="flex-1">Documentos</span>
                    </a>
                    <span class="sidebar-link cursor-not-allowed opacity-60" aria-disabled="true" title="Disponible en la Fase 2">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <span class="flex-1">Historial</span>
                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold text-navy-200">Fase 2</span>
                    </span>
                </div>
            </div>

            @if ($user->isAdmin())
                <div>
                    <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-navy-400">Administración</p>
                    <div class="space-y-1">
                        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'sidebar-link-active' : '' }}" @click="sidebarOpen = false">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/></svg>
                            Resumen administrativo
                        </a>
                        <a href="{{ route('admin.users') }}" class="sidebar-link {{ request()->routeIs('admin.users') ? 'sidebar-link-active' : '' }}" @click="sidebarOpen = false">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                            Usuarios
                        </a>
                        <a href="{{ route('admin.system') }}" class="sidebar-link {{ request()->routeIs('admin.system') ? 'sidebar-link-active' : '' }}" @click="sidebarOpen = false">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3v-1.5a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3v1.5a3 3 0 0 1-3 3m-13.5 0a3 3 0 0 0-3 3v.75a3 3 0 0 0 3 3h13.5a3 3 0 0 0 3-3v-.75a3 3 0 0 0-3-3"/></svg>
                            Estado del sistema
                        </a>
                    </div>
                </div>
            @endif
        </nav>

        <div class="border-t border-white/10 p-3">
            <a href="{{ route('profile.edit') }}" class="sidebar-link {{ request()->routeIs('profile.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                Mi perfil
            </a>
            <div class="mt-3 rounded-xl bg-white/5 px-3 py-2.5">
                <p class="truncate text-xs font-medium text-white">{{ $user->name }}</p>
                <p class="mt-0.5 truncate text-[11px] text-navy-300">{{ $user->email }}</p>
            </div>
        </div>
    </aside>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:h-20 lg:px-8">
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:hidden" @click="sidebarOpen = true" aria-label="Abrir menú">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </button>

                <div class="min-w-0 flex-1">
                    @if (isset($header))
                        {{ $header }}
                    @else
                        <h1 class="truncate text-lg font-semibold tracking-tight text-navy-950">{{ $title }}</h1>
                    @endif
                </div>

                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="flex items-center gap-2 rounded-full border border-slate-200 bg-white p-1.5 pr-2.5 text-left shadow-sm transition hover:border-slate-300" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-navy-900 text-xs font-bold text-white">{{ $initials }}</span>
                        <span class="hidden max-w-[9rem] truncate text-sm font-medium text-slate-700 sm:block">{{ $user->name }}</span>
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </button>

                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg" role="menu">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                            <x-ui.badge tone="aqua" class="mt-2">{{ $user->role->label() }}</x-ui.badge>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">Mi perfil</a>
                        @if ($user->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">Panel administrativo</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2.5 text-left text-sm font-medium text-rose-600 hover:bg-rose-50" role="menuitem">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <x-ui.flash />
            {{ $slot }}
        </main>

        <footer class="mx-auto w-full max-w-7xl px-4 pb-8 pt-2 text-xs leading-relaxed text-slate-500 sm:px-6 lg:px-8">
            <p class="border-t border-slate-200 pt-5">
                SciTrace AI · Los resultados son orientativos y no constituyen una prueba definitiva de uso de inteligencia artificial. La revisión humana es obligatoria.
            </p>
        </footer>
    </div>

    @livewireScripts
</body>
</html>
