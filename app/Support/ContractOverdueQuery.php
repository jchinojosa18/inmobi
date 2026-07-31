<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * SQL compartido para clasificar contratos por mora (Dashboard y Cobranza).
 */
class ContractOverdueQuery
{
    private ?string $databaseDriver = null;

    public function statusSql(string $todayDate): string
    {
        $todayLiteral = "'{$todayDate}'";

        return "CASE
            WHEN rent_status.contract_id IS NULL THEN 'current'
            WHEN {$todayLiteral} > rent_status.grace_until THEN 'overdue'
            WHEN {$todayLiteral} >= rent_status.due_date AND {$todayLiteral} <= rent_status.grace_until THEN 'grace'
            ELSE 'current'
        END";
    }

    public function daysSql(string $todayDate): string
    {
        $todayLiteral = "'{$todayDate}'";
        $diffExpression = $this->overdueDiffExpression($todayDate);

        return "CASE
            WHEN rent_status.contract_id IS NULL THEN 0
            WHEN {$todayLiteral} > rent_status.grace_until THEN {$diffExpression}
            ELSE 0
        END";
    }

    public function balanceByContractSubquery(): Builder
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $includedBalanceTypes = [
            Charge::TYPE_RENT,
            Charge::TYPE_PENALTY,
            Charge::TYPE_SERVICE,
            Charge::TYPE_DAMAGE,
            Charge::TYPE_CLEANING,
            Charge::TYPE_OTHER,
            Charge::TYPE_ADJUSTMENT,
            Charge::TYPE_MOVEOUT,
            Charge::TYPE_DEPOSIT_APPLY,
        ];

        $pendingExpression = $this->contractPendingAmountExpression();

        return Charge::query()
            ->selectRaw("charges.contract_id, {$pendingExpression} as pending_balance")
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->whereIn('charges.type', $includedBalanceTypes)
            ->groupBy('charges.contract_id');
    }

    public function oldestPendingRentSubquery(bool $includePeriod = false): QueryBuilder
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $dueDateExpression = $this->dueDateExpression();
        $graceUntilExpression = $this->graceUntilExpression($dueDateExpression);
        $pendingExpression = $this->pendingAmountExpression();

        $rankedColumns = ['charges.contract_id'];
        if ($includePeriod) {
            $rankedColumns[] = 'charges.period as period';
        }
        $rankedColumns[] = "{$dueDateExpression} as due_date";
        $rankedColumns[] = "{$graceUntilExpression} as grace_until";
        $rankedColumns[] = "ROW_NUMBER() OVER (
                PARTITION BY charges.contract_id
                ORDER BY {$dueDateExpression} asc, charges.id asc
            ) as row_num";

        $rankedSubquery = Charge::query()
            ->from('charges')
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->where('charges.type', Charge::TYPE_RENT)
            ->whereRaw("{$pendingExpression} > 0")
            ->selectRaw(implode(', ', $rankedColumns));

        $outputColumns = ['rent_rows.contract_id'];
        if ($includePeriod) {
            $outputColumns[] = 'rent_rows.period';
        }
        $outputColumns[] = 'rent_rows.due_date';
        $outputColumns[] = 'rent_rows.grace_until';

        return DB::query()
            ->fromSub($rankedSubquery, 'rent_rows')
            ->selectRaw(implode(', ', $outputColumns))
            ->where('rent_rows.row_num', 1);
    }

    public function latestPaymentByContractSubquery(): QueryBuilder
    {
        $rankedPayments = Payment::query()
            ->selectRaw('
                payments.contract_id,
                payments.id as payment_id,
                ROW_NUMBER() OVER (
                    PARTITION BY payments.contract_id
                    ORDER BY payments.paid_at desc, payments.id desc
                ) as row_num
            ');

        return DB::query()
            ->fromSub($rankedPayments, 'payment_rows')
            ->selectRaw('payment_rows.contract_id, payment_rows.payment_id')
            ->where('payment_rows.row_num', 1);
    }

    public function dueDateExpression(): string
    {
        return 'COALESCE(charges.due_date, charges.charge_date)';
    }

    public function graceUntilExpression(string $dueDateExpression): string
    {
        return "COALESCE(charges.grace_until, {$dueDateExpression})";
    }

    public function pendingAmountExpression(): string
    {
        $rawPending = $this->rawPendingAmountExpression();

        if ($this->databaseDriver() === 'sqlite') {
            return "MAX({$rawPending}, 0)";
        }

        return "GREATEST({$rawPending}, 0)";
    }

    /**
     * Clamps each charge before summing: a settled negative ADJUSTMENT already reduced the
     * charges it credited, so its raw negative pending would double-count the discount.
     */
    public function contractPendingAmountExpression(): string
    {
        return 'SUM('.$this->pendingAmountExpression().')';
    }

    private function overdueDiffExpression(string $todayDate): string
    {
        if ($this->databaseDriver() === 'sqlite') {
            return "CAST(julianday('{$todayDate}') - julianday(rent_status.grace_until) AS INTEGER)";
        }

        return "DATEDIFF('{$todayDate}', rent_status.grace_until)";
    }

    private function rawPendingAmountExpression(): string
    {
        return 'charges.amount - COALESCE(alloc.allocated_total, 0)';
    }

    private function databaseDriver(): string
    {
        if ($this->databaseDriver === null) {
            $this->databaseDriver = DB::connection()->getDriverName();
        }

        return $this->databaseDriver;
    }
}
