@props([
    'variant' => 'light',
    'href' => '/',
    'showTagline' => false,
])

@php
    $isDark = $variant === 'dark';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-aqua-400 focus-visible:ring-offset-2 ' . ($isDark ? 'focus-visible:ring-offset-navy-950' : '')]) }} aria-label="SciTrace AI, inicio">
    <x-brand.mark class="h-10 w-10 shrink-0" />
    <span class="flex flex-col leading-none">
        <span class="text-lg font-bold tracking-tight {{ $isDark ? 'text-white' : 'text-navy-950' }}">
            SciTrace <span class="text-aqua-500">AI</span>
        </span>
        @if ($showTagline)
            <span class="mt-1 text-[11px] font-medium uppercase tracking-[0.18em] {{ $isDark ? 'text-navy-300' : 'text-slate-500' }}">
                Trazabilidad académica
            </span>
        @endif
    </span>
</a>
