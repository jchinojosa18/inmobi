<section class="space-y-6">
    <x-ui.page-header
        :title="__('finance.payments.show_title', ['folio' => $payment->receipt_folio ?? __('common.n_a')])"
        :description="'#'. $payment->contract_id .' · '. $payment->contract->tenant->full_name"
    >
        <x-slot:actions>
            <x-ui.button href="{{ $backUrl }}" variant="secondary">
                {{ $backLabel }}
            </x-ui.button>
            @if ($payment->receipt_folio !== null && $receiptViewerItem)
                <x-ui.file-viewer-trigger
                    :items="[$receiptViewerItem]"
                    :index="0"
                    variant="secondary"
                >
                    {{ __('finance.payments.view_pdf') }}
                </x-ui.file-viewer-trigger>
                <x-ui.button
                    :href="$whatsAppUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="!border-0 !bg-emerald-600 !text-white hover:!bg-emerald-500"
                >
                    {{ __('finance.payments.open_whatsapp') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-4">
        <x-ui.stat-card
            :label="__('common.amount')"
            value="${{ number_format((float) $payment->amount, 2) }}"
        />
        <x-ui.stat-card
            :label="__('common.method')"
            :value="__('finance.payments.methods.'.$payment->method)"
        />
        <x-ui.stat-card
            :label="__('common.applied')"
            value="${{ number_format($receipt['allocated_total'], 2) }}"
        />
        <x-ui.stat-card
            :label="__('common.credit_balance')"
            value="${{ number_format($receipt['credited_amount'], 2) }}"
        />
    </div>

    <x-ui.table>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-slate-900">{{ __('finance.payments.allocations_breakdown') }}</h2>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('common.type') }}</th>
            <th class="px-4 py-3">{{ __('common.period') }}</th>
            <th class="px-4 py-3">{{ __('finance.payments.charge_date') }}</th>
            <th class="px-4 py-3 text-right">{{ __('finance.payments.applied_amount') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($receipt['allocations'] as $allocation)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3">{{ $allocation['charge_type'] }}</td>
                    <td class="px-4 py-3">{{ $allocation['period'] ?: __('common.n_a') }}</td>
                    <td class="px-4 py-3">{{ $allocation['charge_date'] ?: __('common.n_a') }}</td>
                    <td class="px-4 py-3 text-right font-medium">${{ number_format($allocation['amount'], 2) }}</td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('finance.payments.no_allocations')" :colspan="4" />
            @endforelse
        </x-slot:body>
    </x-ui.table>

    @if ($payment->receipt_folio !== null)
    <x-ui.card>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('finance.payments.share_receipt') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('finance.payments.share_mvp') }}</p>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div>
                <x-ui.input
                    id="payment-email-recipient"
                    :label="__('finance.payments.email_recipient')"
                    type="email"
                    :value="$payment->contract?->tenant?->email"
                    disabled
                />
                @error('emailRecipient') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <x-ui.button
                    type="button"
                    wire:click="sendEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendEmail"
                    class="mt-3"
                    :disabled="! $payment->contract?->tenant?->email"
                >
                    {{ __('finance.payments.send_email') }}
                </x-ui.button>
                <p class="mt-2 text-xs text-slate-500">{{ __('finance.payments.mailpit_hint') }}</p>
            </div>
            <div>
                <p class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('finance.payments.shareable_link') }}</p>
                <textarea
                    readonly
                    rows="4"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs"
                    x-data
                    x-ref="share"
                    x-init="$refs.share.value = @js($shareUrl)"
                ></textarea>
            </div>
        </div>
    </x-ui.card>
    @endif

    @if ($documents->isNotEmpty())
        <x-ui.card>
            <h2 class="text-lg font-semibold text-slate-900">{{ __('finance.payments.attached_evidence') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($documents as $document)
                    <li>
                        <x-ui.file-viewer-trigger
                            :items="$documentViewerItems"
                            :index="$loop->index"
                        >
                            {{ $document['path'] }}
                        </x-ui.file-viewer-trigger>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <div
        x-data="{ sent: false, timer: null }"
        x-on:payment-receipt-email-sent.window="sent = true; clearTimeout(timer); timer = setTimeout(() => sent = false, 2500)"
        class="pointer-events-none fixed bottom-6 right-6 z-[70]"
    >
        <div
            wire:loading
            wire:target="sendEmail"
            class="w-56 overflow-hidden rounded-lg bg-slate-800/95 px-4 py-3 shadow-lg"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-600">
                <div class="h-full w-1/3 animate-[paymentEmailProgress_1s_ease-in-out_infinite] rounded-full bg-white"></div>
            </div>
        </div>

        <div
            x-show="sent"
            x-cloak
            x-transition.opacity
            class="rounded-lg bg-slate-800/95 px-4 py-2 text-sm font-medium text-white shadow-lg"
            role="status"
            aria-live="polite"
        >
            {{ __('finance.flash.message_sent') }}
        </div>
    </div>

    <style>
        @keyframes paymentEmailProgress {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(300%); }
        }
    </style>
</section>
