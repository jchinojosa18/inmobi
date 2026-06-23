@props(['title' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200/80 bg-white shadow-sm'.($padding ? ' p-5' : '')]) }}>
    @if ($title)
        <h2 class="mb-4 text-lg font-semibold text-slate-900">{{ $title }}</h2>
    @endif
    {{ $slot }}
</div>
