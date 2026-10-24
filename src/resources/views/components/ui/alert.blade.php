@props(['type' => 'info', 'title' => null])

@php
    $styles = match ($type) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900',
        default => 'border-sky-200 bg-sky-50 text-sky-900',
    };
    $iconColor = match ($type) {
        'success' => 'text-emerald-600',
        'warning' => 'text-amber-600',
        'danger' => 'text-rose-600',
        default => 'text-sky-600',
    };
@endphp

<div role="alert" {{ $attributes->merge(['class' => 'flex gap-3 rounded-xl border px-4 py-3.5 text-sm ' . $styles]) }}>
    <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $iconColor }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        @if ($type === 'success')
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        @elseif ($type === 'danger')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 3.94l-7.5 13A1.5 1.5 0 0 0 4.14 19.5h15.72a1.5 1.5 0 0 0 1.3-2.25l-7.5-13a1.5 1.5 0 0 0-2.6 0Z" />
        @elseif ($type === 'warning')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM12 3.75 2.25 20.25h19.5L12 3.75Z" />
        @else
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
        @endif
    </svg>
    <div class="min-w-0">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-1' : '' }} leading-relaxed">{{ $slot }}</div>
    </div>
</div>
