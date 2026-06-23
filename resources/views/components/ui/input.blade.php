@props(['label' => null, 'error' => null, 'id' => null])

<div>
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">
            {{ $label }}
        </label>
    @endif
    <input
        @if ($id) id="{{ $id }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100'.($error ? ' border-red-300' : '')]) }}
    />
    @if ($error)
        <p class="mt-1 text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
