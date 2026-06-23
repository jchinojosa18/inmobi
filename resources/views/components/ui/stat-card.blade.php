@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'default',
    'valueClass' => null,
])

@php
    $tones = [
        'default' => 'border-slate-200/80',
        'success' => 'border-emerald-200/80',
        'warning' => 'border-amber-200/80',
        'danger' => 'border-rose-200/80',
    ];
    $labelTones = [
        'default' => 'text-slate-500',
        'success' => 'text-emerald-700',
        'warning' => 'text-amber-700',
        'danger' => 'text-rose-700',
    ];
    $borderClass = $tones[$tone] ?? $tones['default'];
    $labelClass = $labelTones[$tone] ?? $labelTones['default'];
@endphp

<article {{ $attributes->merge(['class' => 'rounded-xl border bg-white p-5 shadow-sm '.$borderClass]) }}>
    <p class="text-xs font-medium uppercase tracking-wide {{ $labelClass }}">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold {{ $valueClass ?? 'text-slate-900' }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</article>
