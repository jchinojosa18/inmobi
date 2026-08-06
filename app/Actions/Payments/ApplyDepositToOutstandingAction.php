<?php

namespace App\Actions\Payments;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\ChargeAllocationPrioritizer;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Applies a deposit amount to pending operational charges via an internal DEPOSIT payment.
 * Used by finiquito so MOVEOUT/RENT rows show paid (allocations), not only DEPOSIT_APPLY.
 */
class ApplyDepositToOutstandingAction
{
    /**
     * @return CreditApplicationResult applied amount / allocations / payment id (null if no-op)
     */
    public function execute(
        Contract $contract,
        float $amount,
        string $settlementBatchId,
        CarbonImmutable $paidAt,
    ): CreditApplicationResult {
        $amount = round($amount, 2);
        if ($amount <= 0 || $settlementBatchId === '') {
            return new CreditApplicationResult(0.0, 0, null);
        }

        $previousOrganizationId = TenantContext::currentOrganizationId();
        TenantContext::setOrganizationId((int) $contract->organization_id);

        try {
            $existing = Payment::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $contract->organization_id)
                ->where('contract_id', $contract->id)
                ->where('method', Payment::METHOD_DEPOSIT)
                ->where('meta->settlement_batch_id', $settlementBatchId)
                ->first();

            if ($existing !== null) {
                $allocationsCount = (int) $existing->allocations()->count();
                $allocatedAmount = round((float) $existing->allocations()->sum('amount'), 2);

                return new CreditApplicationResult($allocatedAmount, $allocationsCount, (int) $existing->id);
            }

            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            $charges = app(ChargeAllocationPrioritizer::class)->pendingPrioritized($contract);
            $pendingTotal = round($charges->sum(
                fn (Charge $c): float => (float) $c->amount - (float) ($c->allocated_amount ?? 0)
            ), 2);
            $toApply = round(min($amount, $pendingTotal), 2);

            if ($toApply <= 0) {
                return new CreditApplicationResult(0.0, 0, null);
            }

            $payment = Payment::query()->create([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
                'paid_at' => $paidAt->toDateTimeString(),
                'amount' => $toApply,
                'method' => Payment::METHOD_DEPOSIT,
                'reference' => null,
                'receipt_folio' => null,
                'meta' => [
                    'source' => 'deposit_settlement',
                    'settlement_batch_id' => $settlementBatchId,
                ],
            ]);

            $remaining = $toApply;
            $allocatedAmount = 0.0;
            $allocationsCount = 0;

            foreach ($charges as $charge) {
                if ($remaining <= 0) {
                    break;
                }

                $pendingAmount = round(
                    (float) $charge->amount - (float) ($charge->allocated_amount ?? 0),
                    2
                );

                if ($pendingAmount <= 0) {
                    continue;
                }

                $appliedAmount = min($remaining, $pendingAmount);

                PaymentAllocation::query()->create([
                    'organization_id' => $contract->organization_id,
                    'payment_id' => $payment->id,
                    'charge_id' => $charge->id,
                    'amount' => $appliedAmount,
                    'meta' => [
                        'source' => 'apply_deposit_to_outstanding_action',
                        'settlement_batch_id' => $settlementBatchId,
                    ],
                ]);

                $remaining = round($remaining - $appliedAmount, 2);
                $allocatedAmount = round($allocatedAmount + $appliedAmount, 2);
                $allocationsCount++;
            }

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['allocation_processed'] = true;
            $meta['allocation_processed_at'] = now()->toISOString();
            $meta['credited_amount'] = 0;
            $payment->meta = $meta;
            $payment->save();

            return new CreditApplicationResult($allocatedAmount, $allocationsCount, (int) $payment->id);
        } finally {
            TenantContext::setOrganizationId($previousOrganizationId);
        }
    }
}
