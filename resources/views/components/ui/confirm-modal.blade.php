@props([
    'open' => false,
    'title' => '¿Confirmar acción?',
    'confirmAction' => '',
    'cancelAction' => 'cancelConfirm',
    'confirmLabel' => 'Confirmar',
    'cancelLabel' => 'Cancelar',
    'ariaLabel' => null,
    'maxWidth' => 'md',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        default => 'max-w-md',
    };
@endphp

@if ($open)
    <div
        {{ $attributes->class('fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-6') }}
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="confirm-modal-title"
            @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
        >
            <div
                class="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px]"
                wire:click="{{ $cancelAction }}"
                aria-hidden="true"
            ></div>

            <div class="relative z-10 flex w-full {{ $maxWidthClass }} max-h-[calc(100vh-2rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex shrink-0 items-start gap-3 border-b border-slate-200 px-5 py-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 ring-1 ring-red-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1 pt-0.5">
                        <h2 id="confirm-modal-title" class="text-base font-semibold leading-6 text-slate-900">
                            {{ $title }}
                        </h2>
                    </div>

                    <button
                        type="button"
                        wire:click="{{ $cancelAction }}"
                        class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Cerrar"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="overflow-y-auto px-5 py-4 text-sm leading-6 text-slate-600">
                    {{ $slot }}
                </div>

                <div class="flex shrink-0 flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        wire:click="{{ $cancelAction }}"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
                    >
                        {{ $cancelLabel }}
                    </button>
                    @if ($confirmAction !== '')
                        <button
                            type="button"
                            wire:click="{{ $confirmAction }}"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-50 sm:w-auto"
                        >
                            {{ $confirmLabel }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
@endif
