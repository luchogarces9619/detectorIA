@props(['tone' => 'neutral'])

@php
    $styles = match ($tone) {
        'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200',
        'danger' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
        'info' => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200',
        'aqua' => 'bg-aqua-50 text-aqua-700 ring-1 ring-inset ring-aqua-200',
        default => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$styles]) }}>{{ $slot }}</span>
