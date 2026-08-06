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

            if (! $lockedContract->allowsLedgerMutations()
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

            $allocations = PaymentAllocation::query()
                ->withoutOrganizationScope()
                ->with(['payment' => fn ($query) => $query->withoutOrganizationScope()])
                ->where('organization_id', $lockedContract->organization_id)
                ->where('charge_id', $charge->id)
                ->lockForUpdate()
                ->get();

            $paymentsToMaybeVoid = [];

            foreach ($allocations as $allocation) {
                $payment = $allocation->payment;
                if ($payment !== null && ! $payment->trashed()) {
                    $paymentsToMaybeVoid[$payment->id] = $payment;
                }

                $allocation->delete();
            }

            foreach ($paymentsToMaybeVoid as $payment) {
                $remainingAllocations = PaymentAllocation::query()
                    ->withoutOrganizationScope()
                    ->where('payment_id', $payment->id)
                    ->count();

                // Only remove payments that existed solely to fund this deposit hold.
                if ($remainingAllocations === 0) {
                    $payment->delete();
                }
            }

            $amount = (float) $charge->amount;
            $folio = data_get($charge->meta, 'deposit_receipt_folio');
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
                    'deposit_receipt_folio' => $folio,
                    'cleared_payment_ids' => array_keys($paymentsToMaybeVoid),
                ],
                actorUserId: $userId,
            );
        }, 3);
    }
}
