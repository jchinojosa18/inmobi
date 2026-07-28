<section class="space-y-6">
    <x-ui.page-header
        :title="__('finance.cash_flow.title')"
        :description="__('finance.cash_flow.description')"
    >
        <x-slot:actions>
            <x-ui.button href="{{ $exportUrl }}" variant="secondary">
                {{ __('finance.cash_flow.export_csv') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div>
                <x-ui.input
                    id="cash-flow-date-from"
                    :label="__('common.from')"
                    type="date"
                    wire:model.live="date_from"
                />
                @error('date_from') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-ui.input
                    id="cash-flow-date-to"
                    :label="__('common.to')"
                    type="date"
                    wire:model.live="date_to"
                />
                @error('date_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <div class="rounded-md bg-slate-100 px-3 py-2 text-xs text-slate-600">
                    <p>{{ __('finance.cash_flow.operating_types') }} <strong>{{ implode(', ', $operatingChargeTypes) }}</strong></p>
                    <p class="mt-1">{{ __('finance.cash_flow.excludes') }} <strong>DEPOSIT_HOLD</strong> {{ __('common.and') }} <strong>DEPOSIT_APPLY</strong>.</p>
                </div>
            </div>
        </div>
    </x-ui.card>

    @if ($closedMonthSnapshot)
        <x-ui.card
            :padding="true"
            class="!p-4 {{ $snapshotMatches ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}"
        >
            <p class="text-sm font-medium {{ $snapshotMatches ? 'text-emerald-800' : 'text-amber-800' }}">
                {{ $snapshotMatches ? __('finance.cash_flow.snapshot_match') : __('finance.cash_flow.snapshot_mismatch') }}
            </p>
            <p class="mt-1 text-xs {{ $snapshotMatches ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ __('finance.cash_flow.snapshot_totals', [
                    'income' => number_format((float) ($closedMonthSnapshot['ingresos_operativos'] ?? 0), 2),
                    'expense' => number_format((float) ($closedMonthSnapshot['egresos'] ?? 0), 2),
                    'net' => number_format((float) ($closedMonthSnapshot['neto'] ?? 0), 2),
                ]) }}
            </p>
        </x-ui.card>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.stat-card
            :label="__('finance.cash_flow.income')"
            value="${{ number_format($incomeTotal, 2) }}"
            :hint="__('finance.cash_flow.allocations_hint', ['count' => $incomeCount])"
            tone="success"
            value-class="text-emerald-900"
        />
        <x-ui.stat-card
            :label="__('finance.cash_flow.expenses')"
            value="${{ number_format($expenseTotal, 2) }}"
            :hint="__('finance.cash_flow.expenses_hint', ['count' => $expenseCount])"
            tone="danger"
            value-class="text-rose-900"
        />
        <x-ui.stat-card
            :label="__('finance.cash_flow.net')"
            value="${{ number_format($netTotal, 2) }}"
            :hint="__('finance.cash_flow.net_hint')"
            :value-class="$netTotal >= 0 ? 'text-emerald-700' : 'text-rose-700'"
        />
    </div>

    <x-ui.card :padding="true" class="!p-4">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('finance.cash_flow.income_breakdown') }}</h2>
        <div class="mt-3 grid gap-2 md:grid-cols-3">
            @foreach ($incomeByType as $type => $amount)
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-medium text-slate-600">{{ $type }}</p>
                    <p class="text-sm font-semibold text-slate-900">${{ number_format((float) $amount, 2) }}</p>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.table>
        <x-slot:header>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('finance.cash_flow.income_allocations') }}</h2>
                <p class="text-xs text-slate-500">{{ __('common.showing_count', ['count' => $incomeCount]) }}</p>
            </div>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('finance.cash_flow.payment_date') }}</th>
            <th class="px-4 py-3">{{ __('common.folio') }}</th>
            <th class="px-4 py-3">{{ __('common.contract') }}</th>
            <th class="px-4 py-3">{{ __('common.unit') }}</th>
            <th class="px-4 py-3">{{ __('common.type') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.amount') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($incomeDetails as $row)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 text-slate-700"><x-ui.display-date :value="$row['paid_at']" time /></td>
                    <td class="px-4 py-3 text-slate-700">{{ $row['receipt_folio'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        #{{ $row['contract_id'] }}
                        <p class="text-xs text-slate-500">{{ $row['tenant_name'] ?? __('finance.cash_flow.no_tenant') }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ $row['property_name'] ?? __('finance.cash_flow.no_property') }} / {{ $row['unit_name'] ?? ($row['unit_code'] ?? __('finance.cash_flow.no_unit')) }}
                    </td>
                    <td class="px-4 py-3 font-medium text-slate-700">{{ $row['charge_type'] }}</td>
                    <td class="px-4 py-3 text-right font-medium text-emerald-700">${{ number_format((float) $row['allocated_amount'], 2) }}</td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('finance.cash_flow.no_income_allocations')" :colspan="6" />
            @endforelse
        </x-slot:body>
    </x-ui.table>

    <x-ui.table>
        <x-slot:header>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('finance.cash_flow.expenses_section') }}</h2>
                <p class="text-xs text-slate-500">{{ __('common.showing_count', ['count' => $expenseCount]) }}</p>
            </div>
        </x-slot:header>
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
                    <td class="px-4 py-3 text-slate-700"><x-ui.display-date :value="$expense->spent_at" /></td>
                    <td class="px-4 py-3 text-slate-700">{{ $expense->category }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $expense->unit?->property?->name }} / {{ $expense->unit?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $expense->vendor ?: '-' }}</td>
                    <td class="px-4 py-3 text-right font-medium text-rose-700">${{ number_format((float) $expense->amount, 2) }}</td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('finance.cash_flow.no_expenses')" :colspan="5" />
            @endforelse
        </x-slot:body>
    </x-ui.table>
</section>
