@props([
    'open' => false,
    'title' => '',
    'closeAction' => 'cancelForm',
    'ariaLabel' => null,
    'maxWidth' => 'xl',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        default => 'max-w-xl',
    };
@endphp

@if ($open)
    <div
        {{ $attributes->class('fixed inset-0 z-50 flex items-center justify-center p-4') }}
        role="dialog"
        aria-modal="true"
        @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
    >
        <div
            class="absolute inset-0 bg-black/50"
            wire:click="{{ $closeAction }}"
            aria-hidden="true"
        ></div>

        <div class="relative z-10 flex w-full {{ $maxWidthClass }} max-h-[90vh] flex-col rounded-2xl bg-white shadow-xl">
            <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                <button
                    type="button"
                    wire:click="{{ $closeAction }}"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Cerrar"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto px-5 py-4">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
