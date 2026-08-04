<div>
    <x-ui.modal
        :open="$open"
        :title="__('contracts.renew_title')"
        :aria-label="__('contracts.renew_title')"
        max-width="2xl"
    >
        @if ($step === 'form')
            <p class="mb-4 text-sm text-slate-600">
                {{ __('contracts.renew_description') }}
            </p>

            @if ($tenantName || $unitLabel)
                <div class="mb-4 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    @if ($tenantName)
                        <p>{{ __('common.tenant') }}: <strong>{{ $tenantName }}</strong></p>
                    @endif
                    @if ($unitLabel)
                        <p>{{ __('common.unit') }}: <strong>{{ $unitLabel }}</strong></p>
                    @endif
                </div>
            @endif

            @unless ($landlordConfigured)
                @if ($generate_pdf)
                    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        {{ __('contracts.renew_landlord_required') }}
                        <a href="{{ route('settings.index') }}" class="font-medium underline hover:text-amber-900">
                            {{ __('contracts.renew_go_to_settings') }}
                        </a>
                    </div>
                @endif
            @endunless

            <div class="mb-4 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                <p>{{ __('contracts.available') }}: <strong>${{ number_format($availableDeposit, 2) }}</strong></p>
                @if ($differenceAmount > 0)
                    <p>{{ __('contracts.renew_deposit_difference') }}: <strong>${{ number_format($differenceAmount, 2) }}</strong></p>
                @endif
            </div>

            @error('renew_general')
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <form wire:submit="renew" class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-ui.input :label="__('contracts.start_date').' *'" type="date" wire:model.blur="starts_at" />
                    @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.end_date').' *'" type="date" wire:model.blur="ends_at" />
                    @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.monthly_rent').' *'" type="number" step="0.01" min="0" wire:model.blur="rent_amount" />
                    @error('rent_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.deposit').' *'" type="number" step="0.01" min="0" wire:model.blur="deposit_amount" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('contracts.renew_deposit_hint') }}</p>
                    @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.due_day').' *'" type="number" min="1" max="31" wire:model.blur="due_day" />
                    @error('due_day') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.grace_days').' *'" type="number" min="0" max="31" wire:model.blur="grace_days" />
                    @error('grace_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($differenceAmount > 0)
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="register_difference" class="rounded accent-slate-700">
                            <span>{{ __('contracts.register_deposit_difference', ['amount' => number_format($differenceAmount, 2)]) }}</span>
                        </label>
                    </div>
                @endif

                <div class="md:col-span-2 space-y-2">
                    <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                        <input type="checkbox" wire:model.live="generate_pdf" class="rounded accent-slate-700">
                        <span>{{ __('contracts.generate_contract_pdf') }}</span>
                    </label>

                    @if ($generate_pdf && $canSendEmail && $tenantEmail)
                        <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="send_email" class="rounded accent-slate-700">
                            <span>{!! __('contracts.send_contract_email', ['email' => '<strong>'.$tenantEmail.'</strong>']) !!}</span>
                        </label>
                    @endif
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                    <x-ui.button type="button" wire:click="cancelForm" variant="secondary">
                        {{ __('common.cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-60 cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="renew">{{ __('contracts.renew_confirm') }}</span>
                        <span wire:loading wire:target="renew">{{ __('common.saving') }}</span>
                    </x-ui.button>
                </div>
            </form>
        @else
            <div class="space-y-4 text-center">
                <div class="flex justify-center">
                    <div class="rounded-full bg-emerald-100 p-3">
                        <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('contracts.renew_success_title') }}</h3>
                    @if ($tenantName || $unitLabel)
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $tenantName }}@if ($tenantName && $unitLabel) · @endif{{ $unitLabel }}
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap justify-center gap-2">
                    @if ($pdfUrl)
                        @php
                            $agreementViewerItem = \App\Support\FileViewerItem::fromUrl(
                                $pdfUrl,
                                __('contracts.view_contract_pdf'),
                            );
                        @endphp
                        <x-ui.file-viewer-trigger
                            :items="[$agreementViewerItem]"
                            :index="0"
                            variant="secondary"
                            size="sm"
                        >
                            {{ __('contracts.view_contract_pdf') }}
                        </x-ui.file-viewer-trigger>
                    @endif

                    @if ($shareUrl)
                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <span x-text="copied ? @js(__('finance.payments.copied')) : @js(__('contracts.shareable_link'))"></span>
                        </button>
                    @endif

                    @if ($whatsAppUrl)
                        <a
                            href="{{ $whatsAppUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                        >
                            WhatsApp
                        </a>
                    @endif

                    @if ($newContractId)
                        <a
                            href="{{ route('contracts.show', $newContractId) }}"
                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            {{ __('contracts.view_detail') }}
                        </a>
                    @endif
                </div>

                <div class="flex justify-center gap-3 border-t border-slate-100 pt-2">
                    <x-ui.button type="button" wire:click="close">
                        {{ __('common.close') }}
                    </x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.modal>
</div>
