<?php

namespace App\Actions\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\PaymentAllocation;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidDepositHoldAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Contract $contract, int $chargeId, ?int $userId): void
    {
        DB::transaction(function () use ($contract, $chargeId, $userId): void {
            $lockedContract = Contract::query()
                ->withoutOrganizationScope()
                ->lockForUpdate()
                ->findOrFail($contract->id);

            if ($lockedContract->status === Contract::STATUS_ENDED
                || data_get($lockedContract->meta, 'settlement_batch_id') !== null) {
                throw ValidationException::withMessages([
                    'deposit_void' => __('contracts.validation.deposit_void_settled'),
                ]);
            }

            $charge = Charge::query()
                ->withoutOrganizationScope()
                ->lockForUpdate()
                ->find($chargeId);

            if ($charge === null
                || (int) $charge->organization_id !== (int) $lockedContract->organization_id
                || (int) $charge->contract_id !== (int) $lockedContract->id
                || $charge->type !== Charge::TYPE_DEPOSIT_HOLD) {
                throw ValidationException::withMessages([
                    'deposit_void' => __('contracts.validation.deposit_void_not_found'),
                ]);
            }

            $hasAllocations = PaymentAllocation::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $lockedContract->organization_id)
                ->where('charge_id', $charge->id)
                ->exists();

            if ($hasAllocations) {
                throw ValidationException::withMessages([
                    'deposit_void' => __('contracts.validation.deposit_void_has_payment'),
                ]);
            }

            $amount = (float) $charge->amount;
            $charge->delete();

            $this->auditLogger->log(
                action: 'deposit.hold.void',
                auditable: $charge,
                summary: sprintf(
                    'Depósito anulado $%s en contrato #%d',
                    number_format($amount, 2),
                    $lockedContract->id
                ),
                meta: [
                    'amount' => $amount,
                    'contract_id' => $lockedContract->id,
                    'charge_id' => $charge->id,
                ],
                actorUserId: $userId,
            );
        }, 3);
    }
}
