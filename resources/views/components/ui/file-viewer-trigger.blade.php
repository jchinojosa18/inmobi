@props([
    'items' => [],
    'index' => 0,
    'variant' => null,
    'size' => 'md',
])

@php
    $linkClasses = 'font-medium text-blue-700 underline transition hover:text-blue-800';
    $base = 'inline-flex items-center justify-center gap-1.5 font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2';
    $sizes = [
        'sm' => 'rounded-lg px-3 py-1.5 text-xs',
        'md' => 'rounded-lg px-4 py-2 text-sm',
    ];
    $variants = [
        'primary' => 'bg-slate-900 text-white hover:bg-slate-800',
        'secondary' => 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        'danger' => 'border border-red-200 bg-white text-red-700 hover:bg-red-50',
        'accent' => 'bg-indigo-600 text-white hover:bg-indigo-500',
        'emerald' => 'bg-emerald-700 text-white hover:bg-emerald-600',
    ];

    $classes = $variant === null
        ? $linkClasses
        : $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

<button
    type="button"
    @click="$dispatch('open-file-viewer', { index: {{ (int) $index }}, items: @js($items) })"
    {{ $attributes->merge(['class' => $classes]) }}
>
    {{ $slot }}
</button>
