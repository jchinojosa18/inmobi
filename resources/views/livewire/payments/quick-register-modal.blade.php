<div>
    @if($open)
    <div
        id="quick-payment-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="quick-payment-modal-title"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @include('components.ui.partials.modal-focus-trap')
    >
        <div
            class="absolute inset-0 bg-black/50"
            wire:click="close"
            aria-hidden="true"
        ></div>

        <div
            data-modal-panel
            tabindex="-1"
            class="relative z-10 w-full max-w-xl rounded-2xl bg-white shadow-xl outline-none"
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 id="quick-payment-modal-title" class="text-base font-semibold text-slate-900">{{ __('finance.payments.register_modal') }}</h2>
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    aria-label="{{ __('common.close') }}"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-5 py-4">
                @if($step === 'search')
                    <div class="space-y-3">
                        <p class="text-sm text-slate-600">{{ __('finance.payments.search_description') }}</p>

                        <x-ui.input
                            id="qpm-input"
                            type="text"
                            wire:model.live.debounce.200ms="q"
                            wire:keydown.escape="close"
                            :placeholder="__('finance.payments.search_placeholder')"
                            autocomplete="off"
                        />

                        @if(strlen(trim($q)) >= 2)
                            @if(count($searchResults) > 0)
                                <ul class="max-h-64 overflow-y-auto divide-y divide-slate-100 rounded-lg border border-slate-200">
                                    @foreach($searchResults as $result)
                                        <li>
                                            <button
                                                type="button"
                                                role="option"
                                                tabindex="-1"
                                                wire:click="selectContract({{ $result['id'] }})"
                                                class="w-full px-4 py-3 text-left text-sm hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                                            >
                                                <span class="font-medium text-slate-900">
                                                    #{{ $result['id'] }} · {{ $result['tenant_name'] }}
                                                </span>
                                                <span class="text-slate-500">
                                                    — {{ $result['property_name'] }} / {{ $result['unit_name'] ?: ($result['unit_code'] ?: __('common.n_a')) }}
                                                </span>
                                                <span class="ml-auto text-slate-500">
                                                    | {{ __('common.balance') }}: ${{ number_format($result['pending_balance'], 2) }}
                                                </span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-slate-500 py-2">{{ __('finance.payments.no_matches', ['query' => $q]) }}</p>
                            @endif
                        @else
                            <p class="text-xs text-slate-400">{{ __('finance.payments.search_min_chars') }}</p>
                        @endif
                    </div>

                @elseif($step === 'form')
                    <div class="space-y-4">
                        @if($contractSummary)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-slate-900">#{{ $contractSummary['id'] }} · {{ $contractSummary['tenant_name'] }}</span>
                                @if($contractSummary['overdue_status'] === 'overdue')
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('finance.payments.overdue_days', ['days' => $contractSummary['overdue_days']]) }}</span>
                                @elseif($contractSummary['overdue_status'] === 'grace')
                                    <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">{{ __('finance.payments.in_grace') }}</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('finance.payments.current') }}</span>
                                @endif
                            </div>
                            <p class="text-slate-600">{{ $contractSummary['unit_label'] }}</p>
                            <div class="flex gap-4 text-xs text-slate-500">
                                <span>{{ __('finance.payments.pending_balance') }}: <strong class="text-slate-700">${{ number_format($contractSummary['pending_balance'], 2) }}</strong></span>
                                @if($contractSummary['credit_balance'] > 0)
                                    <span>{{ __('common.credit_balance') }}: <strong class="text-emerald-700">${{ number_format($contractSummary['credit_balance'], 2) }}</strong></span>
                                @endif
                            </div>
                        </div>
                        @endif

                        @error('month_close')
                            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                {{ $message }}
                            </div>
                        @enderror

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <x-ui.input
                                    id="qpm-paid-at"
                                    :label="__('finance.payments.paid_at')"
                                    type="datetime-local"
                                    wire:model.blur="paidAt"
                                />
                                @error('paidAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <x-ui.input
                                    id="qpm-amount"
                                    :label="__('common.amount')"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    wire:model.blur="amount"
                                    placeholder="0.00"
                                />
                                @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('finance.payments.payment_method') }}</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" wire:model.live="method" value="{{ \App\Models\Payment::METHOD_CASH }}" class="accent-slate-700">
                                    {{ __('finance.payments.cash') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" wire:model.live="method" value="{{ \App\Models\Payment::METHOD_TRANSFER }}" class="accent-slate-700">
                                    {{ __('finance.payments.transfer') }}
                                </label>
                            </div>
                            @error('method') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if($method === \App\Models\Payment::METHOD_TRANSFER)
                        <div>
                            <x-ui.input
                                id="qpm-reference"
                                :label="__('finance.payments.reference_optional')"
                                type="text"
                                wire:model.blur="reference"
                                maxlength="120"
                                :placeholder="__('finance.payments.reference_placeholder')"
                            />
                            @error('reference') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        @endif

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('finance.payments.evidence_label') }} <span class="text-slate-400">{{ __('finance.expenses.evidence_hint') }}</span></label>
                            <input
                                type="file"
                                wire:model="evidence"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                            >
                            @error('evidence') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if(isset($contractSummary['tenant_email']) && $contractSummary['tenant_email'])
                        <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="sendEmail" class="rounded accent-slate-700">
                            <span>{!! __('finance.payments.send_receipt_email', ['email' => '<strong>'.$contractSummary['tenant_email'].'</strong>']) !!}</span>
                        </label>
                        @endif
                    </div>

                    <div class="mt-5 flex items-center justify-between gap-3">
                        <x-ui.button type="button" variant="secondary" wire:click="backToSearch">
                            {{ __('finance.payments.change_contract') }}
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            wire:click="save"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-60 cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="save">{{ __('common.register_payment') }}</span>
                            <span wire:loading wire:target="save">{{ __('common.saving') }}</span>
                        </x-ui.button>
                    </div>

                @elseif($step === 'done')
                    <div class="space-y-4 text-center">
                        <div class="flex justify-center">
                            <div class="rounded-full bg-emerald-100 p-3">
                                <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('finance.payments.payment_registered') }}</h3>
                            @if($receiptFolio)
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ __('finance.payments.folio_label') }} <span class="font-mono font-medium text-slate-900">{{ $receiptFolio }}</span>
                                </p>
                            @endif
                            @if($contractSummary)
                                <p class="text-sm text-slate-500">{{ $contractSummary['tenant_name'] }} · {{ $contractSummary['unit_label'] }}</p>
                            @endif
                            @if($amount)
                                <p class="mt-1 text-2xl font-semibold text-emerald-700">${{ number_format((float) $amount, 2) }}</p>
                            @endif
                        </div>

                        <div
                            x-data="{ copied: false }"
                            class="flex flex-wrap justify-center gap-2"
                        >
                            @if($shareUrl)
                            <button
                                type="button"
                                x-on:click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <span x-text="copied ? @js(__('finance.payments.copied')) : @js(__('finance.payments.copy_link'))"></span>
                            </button>
                            @endif

                            @if($whatsAppUrl)
                            <a
                                href="{{ $whatsAppUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                            >
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                WhatsApp
                            </a>
                            @endif

                            @if($savedPaymentId)
                            <a
                                href="{{ route('payments.show', $savedPaymentId) }}"
                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                {{ __('common.view_payment') }}
                            </a>
                            @endif
                        </div>

                        <div class="flex justify-center gap-3 pt-2 border-t border-slate-100">
                            <x-ui.button type="button" variant="secondary" wire:click="resetForm">
                                {{ __('finance.payments.new_payment') }}
                            </x-ui.button>
                            <x-ui.button type="button" wire:click="close">
                                {{ __('common.close') }}
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
