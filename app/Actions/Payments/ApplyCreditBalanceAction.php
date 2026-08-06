<?php

namespace App\Actions\Payments;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\ChargeAllocationPrioritizer;
use Illuminate\Support\Facades\DB;

class ApplyCreditBalanceAction
{
    public function execute(Contract $contract): CreditApplicationResult
    {
        return DB::transaction(function () use ($contract): CreditApplicationResult {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            $credit = CreditBalance::query()
                ->where('contract_id', $contract->id)
                ->lockForUpdate()
                ->first();

            $available = round((float) ($credit?->balance ?? 0), 2);
            if ($available <= 0) {
                return new CreditApplicationResult(0.0, 0, null);
            }

            $charges = app(ChargeAllocationPrioritizer::class)->pendingPrioritized($contract);
            $pendingTotal = round($charges->sum(fn (Charge $c) => (float) $c->amount - (float) ($c->allocated_amount ?? 0)), 2);
            $toApply = round(min($available, $pendingTotal), 2);
            if ($toApply <= 0) {
                return new CreditApplicationResult(0.0, 0, null);
            }

            $paidAt = now();

            $payment = Payment::query()->create([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
                'paid_at' => $paidAt,
                'amount' => $toApply,
                'method' => Payment::METHOD_CREDIT,
                'reference' => null,
                'receipt_folio' => null,
                'meta' => ['source' => 'credit_application'],
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

                PaymentAllocation::create([
                    'organization_id' => $contract->organization_id,
                    'payment_id' => $payment->id,
                    'charge_id' => $charge->id,
                    'amount' => $appliedAmount,
                    'meta' => [
                        'source' => 'apply_credit_balance_action',
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

            $credit->balance = round($available - $allocatedAmount, 2);
            $credit->last_payment_id = $payment->id;
            $credit->save();

            return new CreditApplicationResult($allocatedAmount, $allocationsCount, $payment->id);
        }, 3);
    }
}
