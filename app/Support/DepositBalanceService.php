<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\PaymentAllocation;

class DepositBalanceService
{
    public function paidDepositAmount(Contract $contract): float
    {
        return round((float) PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.organization_id', $contract->organization_id)
            ->where('charges.organization_id', $contract->organization_id)
            ->where('payments.organization_id', $contract->organization_id)
            ->whereNull('charges.deleted_at')
            ->whereNull('payments.deleted_at')
            ->where('charges.contract_id', $contract->id)
            ->where('charges.type', Charge::TYPE_DEPOSIT_HOLD)
            ->sum('payment_allocations.amount'), 2);
    }

    public function appliedDepositAmount(Contract $contract): float
    {
        return round(abs((float) Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_APPLY)
            ->sum('amount')), 2);
    }

    public function refundedDepositAmount(Contract $contract): float
    {
        return round((float) Expense::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('category', 'Refund deposit')
            ->where('meta->contract_id', $contract->id)
            ->sum('amount'), 2);
    }

    public function availableDepositAmount(Contract $contract): float
    {
        return round(max(
            $this->paidDepositAmount($contract)
            - $this->appliedDepositAmount($contract)
            - $this->refundedDepositAmount($contract),
            0
        ), 2);
    }

    public function outstandingBalanceExcludingDepositHold(Contract $contract): float
    {
        $chargesTotal = (float) Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', '!=', Charge::TYPE_DEPOSIT_HOLD)
            ->sum('amount');

        $allocatedTotal = (float) PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.organization_id', $contract->organization_id)
            ->where('charges.organization_id', $contract->organization_id)
            ->where('payments.organization_id', $contract->organization_id)
            ->whereNull('charges.deleted_at')
            ->whereNull('payments.deleted_at')
            ->where('charges.contract_id', $contract->id)
            ->where('charges.type', '!=', Charge::TYPE_DEPOSIT_HOLD)
            ->sum('payment_allocations.amount');

        return round(max($chargesTotal - $allocatedTotal, 0), 2);
    }
}
