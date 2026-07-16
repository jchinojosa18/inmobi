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
        <div class="grid gap-3 md:grid-cols-4">
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

            <x-ui.select id="expenses-unit" :label="__('common.unit')" wire:model.live="unitFilter">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select id="expenses-category" :label="__('common.category')" wire:model.live="categoryFilter">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </x-ui.select>
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
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3">{{ optional($expense->spent_at)->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $expense->category }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ $expense->unit?->name ?: __('finance.expenses.general') }}
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
