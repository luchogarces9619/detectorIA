@props(['class' => 'h-9 w-9'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <rect width="48" height="48" rx="13" fill="#0B2035"/>
    <path d="M13 16.5C13 14.567 14.567 13 16.5 13H24L13 24V16.5Z" fill="#2DD4BF"/>
    <path d="M35 31.5C35 33.433 33.433 35 31.5 35H24L35 24V31.5Z" fill="#2DD4BF" fill-opacity="0.55"/>
    <path d="M18 30.5L23.5 25M24.5 24L30 18.5" stroke="white" stroke-width="2.6" stroke-linecap="round"/>
    <circle cx="30.5" cy="18" r="2.5" fill="#F8FAFC"/>
</svg>
