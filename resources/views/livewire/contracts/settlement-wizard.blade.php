<x-ui.card>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.settlement_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ __('contracts.settlement_description') }}
            </p>
        </div>
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
            <p>{{ __('contracts.deposit_paid') }}: <strong>${{ number_format($paidDeposit, 2) }}</strong></p>
            <p>{{ __('contracts.deposit_applied') }}: <strong>${{ number_format($appliedDeposit, 2) }}</strong></p>
            <p>{{ __('contracts.deposit_refunded') }}: <strong>${{ number_format($refundedDeposit, 2) }}</strong></p>
            <p>{{ __('contracts.available') }}: <strong>${{ number_format($availableDeposit, 2) }}</strong></p>
            <p>{{ __('contracts.current_outstanding') }}: <strong>${{ number_format($currentOutstanding, 2) }}</strong></p>
        </div>
    </div>

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
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <p>{{ $lastSettlementSummary }}</p>
            <a href="{{ $lastSettlementPdfUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-600">
                {{ __('contracts.view_settlement_pdf') }}
            </a>
        </div>
    @endif
</x-ui.card>
