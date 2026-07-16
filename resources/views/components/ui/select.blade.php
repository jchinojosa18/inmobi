@props(['label' => null, 'error' => null, 'id' => null])

@php
    $resolvedId = $id;
    if (! $resolvedId && $label) {
        $wireModel = $attributes->wire('model')->value();
        $resolvedId = 'field-'.\Illuminate\Support\Str::slug($label).'-'.substr(md5((string) ($wireModel ?? $label)), 0, 8);
    }
@endphp

<div>
    @if ($label)
        <label @if ($resolvedId) for="{{ $resolvedId }}" @endif class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">
            {{ $label }}
        </label>
    @endif
    <select
        @if ($resolvedId) id="{{ $resolvedId }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:bg-slate-100'.($error ? ' border-red-300' : '')]) }}
    >
        {{ $slot }}
    </select>
    @if ($error)
        <p class="mt-1 text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
