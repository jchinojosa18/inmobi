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

    @php
        $sortIndicator = static fn (string $field): string => $sort === $field ? ($dir === 'asc' ? '↑' : '↓') : '';
    @endphp

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('common.contract') }}</th>
            <th class="px-4 py-3">
                <button type="button" wire:click="sortBy('tenant')" class="inline-flex items-center gap-1 hover:text-slate-800">
                    {{ __('common.tenant') }} <span>{{ $sortIndicator('tenant') }}</span>
                </button>
            </th>
            <th class="px-4 py-3">
                <button type="button" wire:click="sortBy('unit')" class="inline-flex items-center gap-1 hover:text-slate-800">
                    {{ __('contracts.property_unit') }} <span>{{ $sortIndicator('unit') }}</span>
                </button>
            </th>
            <th class="px-4 py-3">
                <button type="button" wire:click="sortBy('next_due')" class="inline-flex items-center gap-1 hover:text-slate-800">
                    {{ __('contracts.next_due') }} <span>{{ $sortIndicator('next_due') }}</span>
                </button>
            </th>
            <th class="px-4 py-3 text-right">{{ __('contracts.overdue_days') }}</th>
            <th class="px-4 py-3 text-right">
                <button type="button" wire:click="sortBy('balance')" class="ml-auto inline-flex items-center gap-1 hover:text-slate-800">
                    {{ __('common.balance') }} <span>{{ $sortIndicator('balance') }}</span>
                </button>
            </th>
            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($contracts as $contract)
                @php
                    $nextDueDate = $contract->next_due_date ? \Illuminate\Support\Carbon::parse($contract->next_due_date) : null;
                    $graceUntil = $contract->grace_until ? \Illuminate\Support\Carbon::parse($contract->grace_until) : null;
                    $overdueStatusLabel = match ($contract->overdue_status) {
                        'overdue' => __('contracts.overdue_status.overdue'),
                        'grace' => __('contracts.overdue_status.grace'),
                        default => __('contracts.overdue_status.current'),
                    };
                    $overdueBadgeVariant = match ($contract->overdue_status) {
                        'overdue' => 'danger',
                        'grace' => 'warning',
                        default => 'success',
                    };
                @endphp
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 align-top">
                        <p class="font-medium text-slate-900">#{{ $contract->id }}</p>
                        <x-ui.badge :variant="$contract->status === 'active' ? 'success' : 'neutral'" class="mt-1">
                            {{ $contract->status === 'active' ? __('common.active') : __('common.finished') }}
                        </x-ui.badge>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <p class="font-medium text-slate-900">{{ $contract->tenant->full_name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $contract->tenant->email ?: __('contracts.no_email') }}
                            @if ($contract->tenant->phone)
                                · {{ $contract->tenant->phone }}
                            @endif
                        </p>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <p class="font-medium text-slate-900">{{ $contract->unit->property->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $contract->unit->name }}
                            @if ($contract->unit->code)
                                ({{ $contract->unit->code }})
                            @endif
                        </p>
                    </td>

                    <td class="px-4 py-3 align-top">
                        @if ($nextDueDate)
                            <p class="font-medium text-slate-900">{{ $nextDueDate->format('Y-m-d') }}</p>
                            <p class="text-xs text-slate-500">{{ __('contracts.grace_until', ['date' => $graceUntil?->format('Y-m-d')]) }}</p>
                            <x-ui.badge :variant="$overdueBadgeVariant" class="mt-1">
                                {{ $overdueStatusLabel }}
                            </x-ui.badge>
                        @else
                            <p class="font-medium text-slate-700">{{ __('contracts.no_charges') }}</p>
                            <p class="text-xs text-slate-500">{{ __('contracts.no_pending_rent_due') }}</p>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-right align-top font-medium text-slate-900">
                        {{ max((int) $contract->overdue_days, 0) }}
                    </td>

                    <td class="px-4 py-3 text-right align-top">
                        <p class="font-medium text-slate-900">${{ number_format((float) $contract->pending_balance, 2) }}</p>
                        <p class="text-xs text-slate-500">{{ __('contracts.credit_balance_short') }}: ${{ number_format((float) $contract->credit_balance, 2) }}</p>
                    </td>

                    <td class="px-4 py-3 text-right align-top">
                        <div class="flex justify-end gap-2">
                            <x-ui.button href="{{ route('contracts.show', $contract) }}" variant="secondary" size="sm">
                                {{ __('contracts.view') }}
                            </x-ui.button>
                            @if ($canCreatePayments)
                                <x-ui.button href="{{ route('contracts.payments.create', $contract) }}" variant="accent" size="sm">
                                    {{ __('common.register_payment') }}
                                </x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('contracts.empty')" :colspan="7" />
            @endforelse
        </x-slot:body>
        <x-slot:footer>
            <div class="bg-slate-50/80 px-4 py-3">
                {{ $contracts->links() }}
            </div>
        </x-slot:footer>
    </x-ui.table>

    @if ($canManageContracts)
        <livewire:contracts.create-modal />
    @endif
</section>
