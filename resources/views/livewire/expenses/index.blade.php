<section class="space-y-6">
    <x-ui.page-header
        :title="__('finance.expenses.title')"
        :description="__('finance.expenses.description')"
    >
        <x-slot:actions>
            @if ($canCreateExpenses)
                <x-ui.button type="button" onclick="Livewire.dispatch('open-quick-expense')">
                    {{ __('finance.expenses.register') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="space-y-3">
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                <x-ui.input
                    id="expenses-date-from"
                    :label="__('common.from')"
                    type="date"
                    wire:model.live="dateFromFilter"
                />

                <x-ui.input
                    id="expenses-date-to"
                    :label="__('common.to')"
                    type="date"
                    wire:model.live="dateToFilter"
                />

                <x-ui.select id="expenses-assignment" :label="__('finance.expenses.assignment')" wire:model.live="assignmentFilter">
                    <option value="all">{{ __('finance.expenses.filter_assignment_all') }}</option>
                    <option value="general">{{ __('finance.expenses.general_expense') }}</option>
                    <option value="unit">{{ __('finance.expenses.filter_assignment_unit') }}</option>
                </x-ui.select>

                <x-ui.select id="expenses-category" :label="__('common.category')" wire:model.live="categoryFilter">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select id="expenses-unit" :label="__('common.unit')" wire:model.live="unitFilter">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">
                            {{ trim($unit->property_name.' / '.$unit->name.($unit->code ? " ({$unit->code})" : '')) }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            @if ($hasActiveFilters)
                <div class="flex justify-end">
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="clearFilters">
                        {{ __('finance.expenses.clear_filters') }}
                    </x-ui.button>
                </div>
            @endif
        </div>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('common.date') }}</th>
            <th class="px-4 py-3">{{ __('common.category') }}</th>
            <th class="px-4 py-3">{{ __('common.unit') }}</th>
            <th class="px-4 py-3">{{ __('common.vendor') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.amount') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($expenses as $expense)
                <tr wire:key="expense-row-{{ $expense->id }}" class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3"><x-ui.display-date :value="$expense->spent_at" /></td>
                    <td class="px-4 py-3 font-medium text-slate-900">
                        <div class="flex flex-wrap items-center gap-2">
                            <span>{{ $expense->expenseCategory?->name ?? '—' }}</span>
                            @php
                                $isDepositRefund = ($expense->expenseCategory?->name === 'REEMBOLSO DEPÓSITO')
                                    || data_get($expense->meta, 'reason') === 'contract_settlement';
                            @endphp
                            @if ($isDepositRefund)
                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-800">
                                    {{ __('finance.expenses.deposit_refund_badge') }}
                                </span>
                            @endif
                        </div>
                        @if ($isDepositRefund && $expense->contract_id)
                            <a href="{{ route('contracts.show', $expense->contract_id) }}" class="mt-1 inline-block text-xs font-medium text-sky-700 underline">
                                {{ __('finance.expenses.contract_link', ['id' => $expense->contract_id]) }}
                            </a>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                        @if ($expense->unit)
                            {{ $expense->unit->property?->name }} / {{ $expense->unit->name }}{{ $expense->unit->code ? ' ('.$expense->unit->code.')' : '' }}
                        @else
                            {{ __('finance.expenses.general') }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $expense->vendor ?: __('common.n_a') }}</td>
                    <td class="px-4 py-3 text-right font-medium">${{ number_format((float) $expense->amount, 2) }}</td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('finance.expenses.empty')" :colspan="5" />
            @endforelse
        </x-slot:body>
        <x-slot:footer>
            <div class="bg-slate-50/80 px-4 py-3">
                {{ $expenses->links() }}
            </div>
        </x-slot:footer>
    </x-ui.table>
</section>
