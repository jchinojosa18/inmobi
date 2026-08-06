<?php

namespace App\Actions\MonthCloses;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\Payment;
use App\Support\LedgerOutstandingCalculator;
use App\Support\OperatingIncomeService;
use Carbon\CarbonImmutable;

class BuildMonthCloseSnapshotAction
{
    public function __construct(
        private readonly OperatingIncomeService $operatingIncomeService,
        private readonly LedgerOutstandingCalculator $ledgerOutstandingCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $organizationId, string $month): array
    {
        $periodStart = CarbonImmutable::createFromFormat('!Y-m', $month, 'America/Tijuana')->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $cutoffTimestamp = $periodEnd->setTime(23, 59, 59);

        $ingresosOperativos = $this->operatingIncomeService->sumForRange(
            organizationId: $organizationId,
            dateFrom: $periodStart->startOfDay(),
            dateTo: $cutoffTimestamp,
        );
        $ingresosOperativosPorTipo = $this->operatingIncomeService->totalsByTypeForRange(
            organizationId: $organizationId,
            dateFrom: $periodStart->startOfDay(),
            dateTo: $cutoffTimestamp,
        );

        $egresos = (float) Expense::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->whereBetween('spent_at', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->sum('amount');

        $cartera = $this->ledgerOutstandingCalculator->outstandingForOrganizationAsOf(
            organizationId: $organizationId,
            chargeDateTo: $periodEnd->toDateString(),
            paymentPaidAtTo: $cutoffTimestamp->toDateTimeString(),
        );

        $contractsActive = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('status', Contract::STATUS_ACTIVE)
            ->count();

        $paymentsCount = Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->whereBetween('paid_at', [
                $periodStart->toDateTimeString(),
                $cutoffTimestamp->toDateTimeString(),
            ])
            ->count();

        $expensesCount = Expense::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->whereBetween('spent_at', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->count();

        return [
            'month' => $month,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'ingresos_operativos' => round($ingresosOperativos, 2),
            'ingresos_operativos_por_tipo' => $ingresosOperativosPorTipo,
            'egresos' => round($egresos, 2),
            'neto' => round($ingresosOperativos - $egresos, 2),
            'cartera' => $cartera,
            'conteos' => [
                'contratos_activos' => $contractsActive,
                'pagos' => $paymentsCount,
                'egresos' => $expensesCount,
            ],
            'income_source' => 'strict_allocations_by_charge_type',
            'generated_at' => now('America/Tijuana')->toIso8601String(),
        ];
    }
}
