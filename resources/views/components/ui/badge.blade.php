@props(['variant' => 'neutral'])

@php
    $variants = [
        'success' => 'bg-emerald-50 text-emerald-700',
        'warning' => 'bg-amber-50 text-amber-700',
        'danger' => 'bg-red-50 text-red-700',
        'info' => 'bg-sky-50 text-sky-700',
        'neutral' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
