@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';
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
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
