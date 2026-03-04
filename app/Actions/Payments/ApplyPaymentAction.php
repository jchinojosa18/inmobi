<?php

namespace App\Actions\Payments;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyPaymentAction
{
    public function execute(Contract $contract, Payment $payment): PaymentApplicationResult
    {
        return DB::transaction(function () use ($contract, $payment): PaymentApplicationResult {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $this->guardSameTenantContract($contract, $payment);

            $existingAllocations = $payment->allocations()
                ->lockForUpdate()
                ->get();

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $alreadyProcessed = (bool) ($meta['allocation_processed'] ?? false);

            if ($alreadyProcessed || $existingAllocations->isNotEmpty()) {
                return PaymentApplicationResult::idempotent(
                    allocatedAmount: round((float) $existingAllocations->sum('amount'), 2),
                    creditedAmount: round((float) ($meta['credited_amount'] ?? 0), 2),
                    allocationsCount: $existingAllocations->count(),
                );
            }

            $remaining = round((float) $payment->amount, 2);
            $allocatedAmount = 0.0;
            $allocationsCount = 0;

            $charges = $this->prioritizedPendingCharges($contract);

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

                PaymentAllocation::create([
                    'organization_id' => $contract->organization_id,
                    'payment_id' => $payment->id,
                    'charge_id' => $charge->id,
                    'amount' => $appliedAmount,
                    'meta' => [
                        'source' => 'apply_payment_action',
                    ],
                ]);

                $remaining = round($remaining - $appliedAmount, 2);
                $allocatedAmount = round($allocatedAmount + $appliedAmount, 2);
                $allocationsCount++;
            }

            $creditedAmount = 0.0;
            if ($remaining > 0) {
                $creditedAmount = $remaining;
                $this->registerCredit($contract, $payment, $creditedAmount);
            }

            $meta['allocation_processed'] = true;
            $meta['allocation_processed_at'] = now()->toISOString();
            $meta['credited_amount'] = $creditedAmount;
            $payment->meta = $meta;
            $payment->save();

            return new PaymentApplicationResult(
                idempotent: false,
                allocatedAmount: $allocatedAmount,
                creditedAmount: $creditedAmount,
                allocationsCount: $allocationsCount,
            );
        }, 3);
    }

    /**
     * @return Collection<int, Charge>
     */
    private function prioritizedPendingCharges(Contract $contract): Collection
    {
        /** @var Collection<int, Charge> $charges */
        $charges = Charge::query()
            ->where('contract_id', $contract->id)
            ->withSum('paymentAllocations as allocated_amount', 'amount')
            ->lockForUpdate()
            ->get();

        return $charges
            ->filter(function (Charge $charge): bool {
                $pendingAmount = (float) $charge->amount - (float) ($charge->allocated_amount ?? 0);

                return $pendingAmount > 0;
            })
            ->sort(function (Charge $left, Charge $right): int {
                $priorityCompare = $this->priorityRank($left) <=> $this->priorityRank($right);
                if ($priorityCompare !== 0) {
                    return $priorityCompare;
                }

                $leftDate = $left->charge_date?->format('Y-m-d') ?? '';
                $rightDate = $right->charge_date?->format('Y-m-d') ?? '';
                $dateCompare = strcmp($leftDate, $rightDate);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return $left->id <=> $right->id;
            })
            ->values();
    }

    private function priorityRank(Charge $charge): int
    {
        if ($charge->type === Charge::TYPE_RENT) {
            return 1;
        }

        if ($charge->type === Charge::TYPE_SERVICE && $this->isRefundableService($charge)) {
            return 2;
        }

        if ($charge->type === Charge::TYPE_PENALTY) {
            return 3;
        }

        return 4;
    }

    private function isRefundableService(Charge $charge): bool
    {
        return (bool) data_get($charge->meta, 'refundable', false);
    }

    private function registerCredit(Contract $contract, Payment $payment, float $amount): void
    {
        // Chosen strategy: a dedicated credit balance table keeps overpayments explicit and avoids
        // encoding financial credit as negative charges, which complicates charge status derivation.
        $creditBalance = CreditBalance::query()
            ->withTrashed()
            ->firstOrNew([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
            ]);

        if ($creditBalance->trashed()) {
            $creditBalance->restore();
        }

        $currentBalance = (float) ($creditBalance->balance ?? 0);
        $creditBalance->balance = round($currentBalance + $amount, 2);
        $creditBalance->last_payment_id = $payment->id;
        $creditBalance->meta = [
            'last_source' => 'payment_overflow',
            'last_amount' => $amount,
        ];
        $creditBalance->save();
    }

    private function guardSameTenantContract(Contract $contract, Payment $payment): void
    {
        if ($payment->contract_id !== $contract->id) {
            throw new RuntimeException('Payment does not belong to provided contract.');
        }

        if ($payment->organization_id !== $contract->organization_id) {
            throw new RuntimeException('Payment and contract must belong to the same organization.');
        }
    }
}
