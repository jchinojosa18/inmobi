<div>
    <x-ui.modal
        :open="$open"
        :title="$isEdit ? __('contracts.edit_contract_title') : ($step === 'done' ? __('contracts.create_success_title') : __('contracts.new_contract'))"
        :aria-label="$isEdit ? __('contracts.edit_contract_title') : ($step === 'done' ? __('contracts.create_success_title') : __('contracts.new_contract'))"
        max-width="2xl"
    >
        @if ($step === 'form')
            <p class="mb-4 text-sm text-slate-600">
                {{ __('contracts.form_description') }}
            </p>

            @error('landlord_name')
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            @error('contract_document')
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-ui.select :label="__('common.unit').' *'" wire:model="unit_id" :disabled="$isEdit">
                        <option value="">{{ __('contracts.select_unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">
                                {{ $unit->property?->name }} — {{ $unit->name }}@if($unit->code) ({{ $unit->code }}) @endif
                            </option>
                        @endforeach
                    </x-ui.select>
                    @error('unit_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.select :label="__('common.tenant').' *'" wire:model="tenant_id" :disabled="$isEdit">
                        <option value="">{{ __('contracts.select_tenant') }}</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->full_name }}</option>
                        @endforeach
                    </x-ui.select>
                    @error('tenant_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.monthly_rent').' *'" type="number" step="0.01" min="0" wire:model.blur="rent_amount" />
                    @error('rent_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.deposit').' *'" type="number" step="0.01" min="0" wire:model.blur="deposit_amount" placeholder="0" />
                    @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.due_day').' *'" type="number" min="1" max="31" wire:model.blur="due_day" placeholder="0" />
                    @error('due_day') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.grace_days').' *'" type="number" min="0" max="31" wire:model.blur="grace_days" placeholder="0" />
                    @error('grace_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.penalty_rate_daily').' *'" type="number" step="0.01" min="0" max="100" wire:model.blur="penalty_rate_daily" placeholder="0.00" />
                    @error('penalty_rate_daily') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.select :label="__('contracts.status').' *'" wire:model="status">
                        <option value="active">{{ __('common.active') }}</option>
                        <option value="ended">{{ __('common.finished') }}</option>
                    </x-ui.select>
                    @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.start_date').' *'" type="date" wire:model.blur="starts_at" />
                    @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.end_date').' *'" type="date" wire:model.blur="ends_at" />
                    @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('contracts.notes') }}</label>
                    <textarea wire:model.blur="meta_notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                    @error('meta_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if (! $isEdit && $canSendReceipts && $selectedTenantEmail)
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="send_email" class="rounded border-slate-300" />
                            {{ __('contracts.send_agreement_email') }}
                        </label>
                    </div>
                @endif

                <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                    <x-ui.button type="button" wire:click="cancelForm" variant="secondary">
                        {{ __('common.cancel') }}
                    </x-ui.button>
                    <x-ui.button type="submit">
                        {{ __('contracts.save_contract') }}
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
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('contracts.create_success_title') }}</h3>
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

                    @if ($createdContractId)
                        <a
                            href="{{ route('contracts.show', $createdContractId) }}"
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
