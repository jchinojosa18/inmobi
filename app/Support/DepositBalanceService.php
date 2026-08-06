<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;

class DepositBalanceService
{
    public function __construct(
        private readonly LedgerOutstandingCalculator $ledgerOutstandingCalculator,
    ) {}

    public function registeredDepositHoldAmount(Contract $contract): float
    {
        return round((float) Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->sum('amount'), 2);
    }

    public function remainingDepositHoldAmount(Contract $contract): float
    {
        $depositAmount = round((float) $contract->deposit_amount, 2);

        return round(max($depositAmount - $this->registeredDepositHoldAmount($contract), 0), 2);
    }

    public function paidDepositAmount(Contract $contract): float
    {
        // Deposit is received when registered (DEPOSIT_HOLD), not via cobranza payments.
        return $this->registeredDepositHoldAmount($contract);
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
            ->where('contract_id', $contract->id)
            ->whereHas('expenseCategory', fn ($query) => $query
                ->withoutOrganizationScope()
                ->where('organization_id', $contract->organization_id)
                ->where('is_system', true)
                ->where('name', 'REEMBOLSO DEPÓSITO'))
            ->sum('amount'), 2);
    }

    public function transferredOutDepositAmount(Contract $contract): float
    {
        return round(abs((float) Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_TRANSFER_OUT)
            ->sum('amount')), 2);
    }

    public function availableDepositAmount(Contract $contract): float
    {
        return round(max(
            $this->registeredDepositHoldAmount($contract)
            - $this->appliedDepositAmount($contract)
            - $this->refundedDepositAmount($contract)
            - $this->transferredOutDepositAmount($contract),
            0
        ), 2);
    }

    public function outstandingBalanceExcludingDepositHold(Contract $contract): float
    {
        return $this->ledgerOutstandingCalculator->outstandingForContract($contract);
    }
}
