<section class="space-y-6">
    <x-ui.page-header
        :title="__('contracts.title')"
        :description="__('contracts.description')"
    >
        <x-slot:actions>
            @if ($canManageContracts)
                <x-ui.button type="button" wire:click="$dispatch('open-contract-create')">
                    {{ __('common.new_contract') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-6">
            <div class="md:col-span-2">
                <x-ui.input
                    id="contracts-search"
                    :label="__('contracts.global_search')"
                    type="text"
                    wire:model.live.debounce.300ms="q"
                    :placeholder="__('contracts.search_placeholder')"
                />
            </div>

            <x-ui.select id="contracts-status" :label="__('contracts.status')" wire:model.live="status_filter">
                <option value="active">{{ __('contracts.status_active') }}</option>
                <option value="expiring">{{ __('contracts.status_expiring') }}</option>
                <option value="expired">{{ __('contracts.status_expired') }}</option>
                <option value="attention">{{ __('contracts.status_attention') }}</option>
                <option value="ended">{{ __('contracts.status_ended') }}</option>
                <option value="all">{{ __('contracts.all_masculine') }}</option>
            </x-ui.select>

            <x-ui.select id="contracts-property" :label="__('contracts.property')" wire:model.live="property_id">
                <option value="">{{ __('contracts.all_feminine') }}</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}">{{ $property->name }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select
                id="contracts-unit"
                :label="__('common.unit')"
                wire:model.live="unit_id"
                :disabled="$property_id === ''"
            >
                <option value="">{{ __('contracts.all_feminine') }}</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' ('.$unit->code.')' : '' }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select id="contracts-overdue" :label="__('contracts.overdue_filter')" wire:model.live="overdue_filter">
                <option value="all">{{ __('contracts.all_masculine') }}</option>
                <option value="overdue">{{ __('contracts.overdue_only') }}</option>
                <option value="grace">{{ __('contracts.grace_only') }}</option>
                <option value="current">{{ __('contracts.current_only') }}</option>
            </x-ui.select>
        </div>

        <p class="mt-3 text-sm text-slate-600">
            {{ __('contracts.showing_count', ['count' => $contracts->count(), 'total' => $contracts->total()]) }}
        </p>
    </x-ui.card>

    @if ($attentionContracts->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">
                    {{ __('contracts.status_attention') }}
                    <span class="ml-1 text-sm font-medium text-slate-500">({{ $attentionContracts->count() }})</span>
                </h2>
            </div>

            <x-ui.table>
                <x-slot:head>
                    @include('livewire.contracts.partials.attention-table-head')
                </x-slot:head>
                <x-slot:body>
                    @foreach ($attentionContracts as $contract)
                        @include('livewire.contracts.partials.attention-row', [
                            'contract' => $contract,
                            'rowKey' => 'attention-contract-row-'.$contract->id,
                        ])
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        </div>
    @endif

    <x-ui.table>
        <x-slot:head>
            @include('livewire.contracts.partials.index-table-head', ['sortable' => true, 'sort' => $sort, 'dir' => $dir])
        </x-slot:head>
        <x-slot:body>
            @forelse ($contracts as $contract)
                @include('livewire.contracts.partials.index-row', [
                    'contract' => $contract,
                    'rowKey' => 'contract-row-'.$contract->id,
                    'canCreatePayments' => $canCreatePayments,
                ])
            @empty
                <x-ui.empty-state :title="__('contracts.empty')" :colspan="8" />
            @endforelse
        </x-slot:body>
        <x-slot:footer>
            <div class="bg-slate-50/80 px-4 py-3">
                {{ $contracts->links() }}
            </div>
        </x-slot:footer>
    </x-ui.table>
</section>
