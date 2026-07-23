<x-ui.card>
    <div x-data="{ open: false }">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="settlement-panel"
            aria-expanded="false"
            aria-label="{{ __('contracts.settlement_panel_toggle') }}"
        >
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.settlement_title') }}</h2>
                <p x-show="!open" class="mt-0.5 text-sm text-slate-600">
                    {{ __('contracts.deposit_paid') }}: <strong class="font-medium text-slate-900">${{ number_format($paidDeposit, 2) }}</strong>
                    <span class="text-slate-400">/</span>
                    {{ __('contracts.current_outstanding') }}: <strong class="font-medium text-slate-900">${{ number_format($currentOutstanding, 2) }}</strong>
                </p>
            </div>
            <svg
                class="h-5 w-5 shrink-0 text-slate-500 transition-transform"
                :class="{ 'rotate-180': open }"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div id="settlement-panel" x-show="open" x-cloak class="mt-4">
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                <p>{{ __('contracts.deposit_paid') }}: <strong>${{ number_format($paidDeposit, 2) }}</strong></p>
                <p>{{ __('contracts.deposit_applied') }}: <strong>${{ number_format($appliedDeposit, 2) }}</strong></p>
                <p>{{ __('contracts.deposit_refunded') }}: <strong>${{ number_format($refundedDeposit, 2) }}</strong></p>
                <p>{{ __('contracts.available') }}: <strong>${{ number_format($availableDeposit, 2) }}</strong></p>
                <p>{{ __('contracts.current_outstanding') }}: <strong>${{ number_format($currentOutstanding, 2) }}</strong></p>
            </div>

            <p class="mt-4 text-sm text-slate-600">
                {{ __('contracts.settlement_description') }}
            </p>

            @error('settlement_general')
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            @if ($isEnded)
                <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    {{ __('contracts.settlement_ended_blocked') }}
                </div>
            @else
            <form wire:submit="process" class="mt-4 space-y-4" enctype="multipart/form-data">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <x-ui.input :label="__('contracts.move_out_date').' *'" type="date" wire:model.blur="move_out_date" />
                        @error('move_out_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('contracts.exit_concepts') }}</h3>
                        <x-ui.button type="button" wire:click="addConcept" variant="secondary" size="sm">
                            {{ __('contracts.add_concept') }}
                        </x-ui.button>
                    </div>

                    @error('concepts') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    @foreach ($concepts as $index => $concept)
                        <div wire:key="settlement-concept-{{ $index }}" class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 md:grid-cols-12">
                            <div class="md:col-span-5">
                                <x-ui.input :label="__('contracts.concept').' *'" type="text" wire:model.blur="concepts.{{ $index }}.description" />
                                @error('concepts.'.$index.'.description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-3">
                                <x-ui.input :label="__('common.amount').' *'" type="number" step="0.01" min="0.01" wire:model.blur="concepts.{{ $index }}.amount" />
                                @error('concepts.'.$index.'.amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-3">
                                @php $evidenceInputId = 'settlement-evidence-'.$index; @endphp
                                <label for="{{ $evidenceInputId }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('contracts.evidence_photo') }}</label>
                                <input id="{{ $evidenceInputId }}" type="file" wire:model="evidenceFiles.{{ $index }}" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                @error('evidenceFiles.'.$index) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex items-end justify-end md:col-span-1">
                                <x-ui.button type="button" wire:click="removeConcept({{ $index }})" variant="danger" size="sm">
                                    {{ __('contracts.remove') }}
                                </x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <x-ui.button type="submit">
                        {{ __('contracts.confirm_settlement') }}
                    </x-ui.button>
                </div>
            </form>
            @endif

            @if ($lastSettlementPdfUrl)
                @php
                    $settlementViewerItem = \App\Support\FileViewerItem::fromUrl(
                        $lastSettlementPdfUrl,
                        __('contracts.view_settlement_pdf'),
                    );
                @endphp
                <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <p>{{ $lastSettlementSummary }}</p>
                    <x-ui.file-viewer-trigger
                        :items="[$settlementViewerItem]"
                        :index="0"
                        variant="emerald"
                        size="sm"
                        class="mt-2 !rounded-lg !px-3 !py-1.5 !text-xs"
                    >
                        {{ __('contracts.view_settlement_pdf') }}
                    </x-ui.file-viewer-trigger>
                </div>
            @endif
        </div>
    </div>
</x-ui.card>
