@props([
    'label' => null,
    'id' => null,
    'chooseLabel' => null,
    'uploadingLabel' => null,
    'accept' => null,
    'multiple' => false,
    'boxed' => false,
    'resetEvent' => null,
    'clearEvent' => null,
    'hint' => null,
    'loadingTarget' => null,
])

@php
    $chooseLabel = $chooseLabel ?? __('common.choose_file');
    $uploadingLabel = $uploadingLabel ?? __('common.uploading_file');
    $wireModel = $attributes->wire('model')->value();
    $resolvedId = $id ?? 'file-input-'.substr(md5((string) ($wireModel ?? uniqid('', true))), 0, 8);
    $loadingTarget = $loadingTarget ?? $wireModel;
    $labelClasses = $boxed
        ? 'mb-2 text-sm font-medium text-slate-700'
        : 'mb-1 block text-xs font-medium text-slate-700';
@endphp

<div @if ($boxed) class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4" @endif>
    @if ($label)
        <p class="{{ $labelClasses }}">{{ $label }}</p>
    @elseif (isset($labelSlot))
        <div class="{{ $labelClasses }}">
            {{ $labelSlot }}
        </div>
    @endif

    <div
        x-data="{ fileLabel: '' }"
        @if ($resetEvent) x-on:{{ $resetEvent }}.window="fileLabel = ''" @endif
        @if ($clearEvent) x-on:{{ $clearEvent }}.window="fileLabel = ''" @endif
        class="flex flex-col gap-2"
    >
        <div @class([
            'flex flex-col gap-2',
            'sm:flex-row sm:flex-wrap sm:items-center' => isset($slot) && ! $slot->isEmpty(),
        ])>
            <input
                id="{{ $resolvedId }}"
                type="file"
                @if ($multiple) multiple @endif
                @if ($accept) accept="{{ $accept }}" @endif
                {{ $attributes->except(['class']) }}
                x-on:change="
                    const files = Array.from($event.target.files || []);
                    fileLabel = files.map(file => file.name).join(', ');
                "
                class="sr-only"
            >
            <label
                for="{{ $resolvedId }}"
                {{ $attributes->only('class')->class([
                    'inline-flex min-h-10 w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2',
                    'sm:min-h-0 sm:w-auto sm:px-3 sm:py-1.5 sm:text-xs' => isset($slot) && ! $slot->isEmpty(),
                ]) }}
            >
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                {{ $chooseLabel }}
            </label>

            @if (isset($slot) && ! $slot->isEmpty())
                {{ $slot }}
            @endif
        </div>

        <p
            x-show="fileLabel"
            x-text="fileLabel"
            x-cloak
            class="truncate text-xs text-slate-500"
        ></p>
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @if ($loadingTarget)
        <p wire:loading wire:target="{{ $loadingTarget }}" class="mt-2 text-xs text-slate-500">{{ $uploadingLabel }}</p>
    @endif
</div>
