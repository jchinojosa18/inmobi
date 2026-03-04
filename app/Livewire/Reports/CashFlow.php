<?php

namespace App\Livewire\Reports;

use App\Models\Expense;
use App\Models\MonthClose;
use App\Support\OperatingIncomeService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CashFlow extends Component
{
    public string $date_from = '';

    public string $date_to = '';

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'date_from' => ['except' => ''],
        'date_to' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to = now()->toDateString();
    }

    public function render(): View
    {
        $this->validate($this->rules(), $this->messages());

        $dateFrom = CarbonImmutable::parse($this->date_from, 'America/Tijuana')->startOfDay();
        $dateTo = CarbonImmutable::parse($this->date_to, 'America/Tijuana')->endOfDay();
        $organizationId = (int) auth()->user()?->organization_id;

        $operatingIncomeService = app(OperatingIncomeService::class);
        $incomeDetails = $operatingIncomeService->allocationsForRange($organizationId, $dateFrom, $dateTo);
        $incomeByType = $operatingIncomeService->totalsByTypeForRange($organizationId, $dateFrom, $dateTo);
        $incomeTotal = round((float) array_sum($incomeByType), 2);

        $expenses = Expense::query()
            ->with(['unit.property'])
            ->whereBetween('spent_at', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('spent_at')
            ->get();

        $expenseTotal = round((float) $expenses->sum('amount'), 2);

        $netTotal = round($incomeTotal - $expenseTotal, 2);

        $incomeCount = $incomeDetails->count();
        $expenseCount = $expenses->count();
        $closedMonthSnapshot = $this->resolveClosedMonthSnapshot($organizationId, $dateFrom, $dateTo);
        $snapshotMatches = null;

        if ($closedMonthSnapshot !== null) {
            $snapshotMatches = round((float) ($closedMonthSnapshot['ingresos_operativos'] ?? 0), 2) === $incomeTotal
                && round((float) ($closedMonthSnapshot['egresos'] ?? 0), 2) === $expenseTotal
                && round((float) ($closedMonthSnapshot['neto'] ?? 0), 2) === $netTotal;
        }

        $exportUrl = route('reports.flow.export.csv', [
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ]);

        return view('livewire.reports.cash-flow', [
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netTotal' => $netTotal,
            'incomeCount' => $incomeCount,
            'expenseCount' => $expenseCount,
            'incomeByType' => $incomeByType,
            'incomeDetails' => $incomeDetails,
            'expenses' => $expenses,
            'operatingChargeTypes' => $operatingIncomeService->operatingChargeTypes(),
            'closedMonthSnapshot' => $closedMonthSnapshot,
            'snapshotMatches' => $snapshotMatches,
            'exportUrl' => $exportUrl,
        ])->layout('layouts.app', ['title' => 'Flujo por rango']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'date_from.required' => 'La fecha inicial es obligatoria.',
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.required' => 'La fecha final es obligatoria.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser mayor o igual a la inicial.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveClosedMonthSnapshot(int $organizationId, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): ?array
    {
        $monthStart = $dateFrom->startOfMonth()->toDateString();
        $monthEnd = $dateFrom->endOfMonth()->toDateString();

        if (
            $dateFrom->toDateString() !== $monthStart
            || $dateTo->toDateString() !== $monthEnd
            || $dateFrom->format('Y-m') !== $dateTo->format('Y-m')
        ) {
            return null;
        }

        $monthClose = MonthClose::query()
            ->where('organization_id', $organizationId)
            ->where('month', $dateFrom->format('Y-m'))
            ->first();

        if ($monthClose === null) {
            return null;
        }

        return is_array($monthClose->snapshot) ? $monthClose->snapshot : null;
    }
}
