<section class="space-y-6">
    <x-ui.page-header
        :title="$isEdit ? __('contracts.edit_contract_title') : __('contracts.new_contract')"
        :description="__('contracts.form_description')"
    >
        @if ($isEdit)
            <x-slot:actions>
                <x-ui.button href="{{ route('contracts.show', $contractId) }}" variant="secondary">
                    {{ __('contracts.view_detail') }}
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.card>
        <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
            <div>
                <x-ui.select :label="__('common.unit').' *'" wire:model="unit_id">
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
                <x-ui.select :label="__('common.tenant').' *'" wire:model="tenant_id">
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
                <x-ui.input :label="__('contracts.deposit').' *'" type="number" step="0.01" min="0" wire:model.blur="deposit_amount" />
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

            <div>
                <x-ui.input :label="__('contracts.penalty_rate_daily').' *'" type="number" step="0.0001" min="0.01" max="100" wire:model.blur="penalty_rate_daily" />
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
                <x-ui.input :label="__('contracts.end_date')" type="date" wire:model.blur="ends_at" />
                @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('contracts.notes') }}</label>
                <textarea wire:model.blur="meta_notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                @error('meta_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2 flex justify-end">
                <x-ui.button type="submit">
                    {{ __('contracts.save_contract') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</section>
