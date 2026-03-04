<?php

namespace App\Actions\MonthCloses;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\OperatingIncomeService;
use Carbon\CarbonImmutable;

class BuildMonthCloseSnapshotAction
{
    public function __construct(
        private readonly OperatingIncomeService $operatingIncomeService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $organizationId, string $month): array
    {
        $periodStart = CarbonImmutable::createFromFormat('Y-m', $month, 'America/Tijuana')->startOfMonth();
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

        $totalCharges = (float) Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->whereDate('charge_date', '<=', $periodEnd->toDateString())
            ->whereNotIn('type', [
                Charge::TYPE_DEPOSIT_HOLD,
                Charge::TYPE_DEPOSIT_APPLY,
                'DEPOSIT',
                'SECURITY_DEPOSIT',
            ])
            ->sum('amount');

        $totalAllocated = (float) PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.organization_id', $organizationId)
            ->where('charges.organization_id', $organizationId)
            ->where('payments.organization_id', $organizationId)
            ->whereNull('charges.deleted_at')
            ->whereNull('payments.deleted_at')
            ->whereDate('charges.charge_date', '<=', $periodEnd->toDateString())
            ->whereNotIn('charges.type', [
                Charge::TYPE_DEPOSIT_HOLD,
                Charge::TYPE_DEPOSIT_APPLY,
                'DEPOSIT',
                'SECURITY_DEPOSIT',
            ])
            ->where('payments.paid_at', '<=', $cutoffTimestamp->toDateTimeString())
            ->sum('payment_allocations.amount');

        $creditedFromPayments = (float) Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('paid_at', '<=', $cutoffTimestamp->toDateTimeString())
            ->get(['meta'])
            ->reduce(function (float $carry, Payment $payment): float {
                return $carry + (float) data_get($payment->meta, 'credited_amount', 0);
            }, 0.0);

        $cartera = round(max($totalCharges - $totalAllocated - $creditedFromPayments, 0), 2);

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
