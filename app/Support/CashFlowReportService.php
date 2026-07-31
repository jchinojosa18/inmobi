<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\MonthClose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CashFlowReportService
{
    public function __construct(
        private readonly OperatingIncomeService $operatingIncomeService,
    ) {}

    /**
     * @return array{
     *     incomeTotal: float,
     *     expenseTotal: float,
     *     netTotal: float,
     *     incomeCount: int,
     *     expenseCount: int,
     *     incomeByType: array<string, float>,
     *     incomeDetails: Collection<int, array<string, mixed>>,
     *     expenses: Collection<int, Expense>,
     *     expensesByCategory: Collection<string, float>,
     *     operatingChargeTypes: list<string>,
     *     closedMonthSnapshot: ?array<string, mixed>,
     *     snapshotMatches: ?bool,
     *     depositsReceivedTotal: float,
     *     depositsReceivedCount: int,
     *     grossCashInTotal: float
     * }
     */
    public function build(
        int $organizationId,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        ?int $plazaId = null
    ): array {
        $incomeDetails = $this->operatingIncomeService->allocationsForRange(
            $organizationId,
            $dateFrom,
            $dateTo,
            $plazaId,
        );
        $incomeByType = $this->operatingIncomeService->totalsByTypeForRange(
            $organizationId,
            $dateFrom,
            $dateTo,
            $plazaId,
        );
        $incomeTotal = round((float) array_sum($incomeByType), 2);

        $expenses = Expense::query()
            ->withoutOrganizationScope()
            ->with(['unit.property', 'expenseCategory'])
            ->where('organization_id', $organizationId)
            ->when($plazaId !== null, function (Builder $query) use ($plazaId, $organizationId): void {
                $query->where(function (Builder $scoped) use ($plazaId, $organizationId): void {
                    $scoped->whereNull('unit_id')
                        ->orWhereHas('unit', function (Builder $unitQuery) use ($plazaId, $organizationId): void {
                            $unitQuery->withoutOrganizationScope()
                                ->where('units.organization_id', $organizationId)
                                ->whereHas('property', function (Builder $propertyQuery) use ($plazaId, $organizationId): void {
                                    $propertyQuery->withoutOrganizationScope()
                                        ->where('properties.organization_id', $organizationId)
                                        ->where('plaza_id', $plazaId);
                                });
                        });
                });
            })
            ->whereBetween('spent_at', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('spent_at')
            ->get();

        $expenseTotal = round((float) $expenses->sum('amount'), 2);
        $expensesByCategory = $expenses
            ->groupBy(fn (Expense $expense) => $expense->expenseCategory?->name ?? '—')
            ->map(fn ($group) => round((float) $group->sum('amount'), 2))
            ->sortKeys();

        $netTotal = round($incomeTotal - $expenseTotal, 2);

        $deposits = $this->depositsReceivedForRange($organizationId, $dateFrom, $dateTo, $plazaId);
        $depositsReceivedTotal = $deposits['total'];
        $depositsReceivedCount = $deposits['count'];
        $grossCashInTotal = round($incomeTotal + $depositsReceivedTotal, 2);

        $closedMonthSnapshot = $this->resolveClosedMonthSnapshot(
            $organizationId,
            $dateFrom,
            $dateTo,
            $plazaId,
        );
        $snapshotMatches = null;
        if ($closedMonthSnapshot !== null) {
            $snapshotMatches = round((float) ($closedMonthSnapshot['ingresos_operativos'] ?? 0), 2) === $incomeTotal
                && round((float) ($closedMonthSnapshot['egresos'] ?? 0), 2) === $expenseTotal
                && round((float) ($closedMonthSnapshot['neto'] ?? 0), 2) === $netTotal;
        }

        return [
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netTotal' => $netTotal,
            'incomeCount' => $incomeDetails->count(),
            'expenseCount' => $expenses->count(),
            'incomeByType' => $incomeByType,
            'incomeDetails' => $incomeDetails,
            'expenses' => $expenses,
            'expensesByCategory' => $expensesByCategory,
            'operatingChargeTypes' => $this->operatingIncomeService->operatingChargeTypes(),
            'closedMonthSnapshot' => $closedMonthSnapshot,
            'snapshotMatches' => $snapshotMatches,
            'depositsReceivedTotal' => $depositsReceivedTotal,
            'depositsReceivedCount' => $depositsReceivedCount,
            'grossCashInTotal' => $grossCashInTotal,
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    private function depositsReceivedForRange(
        int $organizationId,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        ?int $plazaId
    ): array {
        $query = Charge::query()
            ->withoutOrganizationScope()
            ->where('charges.organization_id', $organizationId)
            ->where('charges.type', Charge::TYPE_DEPOSIT_HOLD)
            ->whereBetween('charges.charge_date', [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ])
            ->when($plazaId !== null, function (Builder $builder) use ($plazaId, $organizationId): void {
                $builder->whereHas('unit', function (Builder $unitQuery) use ($plazaId, $organizationId): void {
                    $unitQuery->withoutOrganizationScope()
                        ->where('units.organization_id', $organizationId)
                        ->whereHas('property', function (Builder $propertyQuery) use ($plazaId, $organizationId): void {
                            $propertyQuery->withoutOrganizationScope()
                                ->where('properties.organization_id', $organizationId)
                                ->where('plaza_id', $plazaId);
                        });
                });
            });

        $row = (clone $query)
            ->selectRaw('COALESCE(SUM(charges.amount), 0) as total_amount, COUNT(*) as holds_count')
            ->first();

        return [
            'total' => round((float) ($row?->total_amount ?? 0), 2),
            'count' => (int) ($row?->holds_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveClosedMonthSnapshot(
        int $organizationId,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        ?int $plazaId
    ): ?array {
        if ($plazaId !== null) {
            return null;
        }

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
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('month', $dateFrom->format('Y-m'))
            ->first();

        if ($monthClose === null) {
            return null;
        }

        return is_array($monthClose->snapshot) ? $monthClose->snapshot : null;
    }
}
