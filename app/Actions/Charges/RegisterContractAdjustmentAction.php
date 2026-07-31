<?php

namespace App\Actions\Charges;

use App\Actions\Payments\ApplyCreditBalanceAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RegisterContractAdjustmentAction
{
    public function __construct(
        private readonly ApplyCreditBalanceAction $applyCreditBalanceAction,
    ) {}

    public function execute(
        Contract $contract,
        float $amount,
        CarbonImmutable $chargeDate,
        string $reason,
        ?string $comment = null,
        ?string $linkedTo = null,
        ?int $createdByUserId = null,
    ): Charge {
        $amount = round($amount, 2);

        if ($amount == 0.0) {
            throw new \InvalidArgumentException('Adjustment amount cannot be zero.');
        }

        return DB::transaction(function () use (
            $contract,
            $amount,
            $chargeDate,
            $reason,
            $comment,
            $linkedTo,
            $createdByUserId,
        ): Charge {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            $meta = [
                'reason' => trim($reason),
                'comment' => trim((string) ($comment ?? '')),
                'linked_to' => trim((string) ($linkedTo ?? '')),
                'created_from' => 'contract_show_adjustment',
                'created_by_user_id' => $createdByUserId,
            ];

            /** @var Charge $charge */
            $charge = Charge::query()->create([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
                'unit_id' => $contract->unit_id,
                'type' => Charge::TYPE_ADJUSTMENT,
                'period' => $chargeDate->format('Y-m'),
                'charge_date' => $chargeDate->toDateString(),
                'amount' => $amount,
                'meta' => $meta,
            ]);

            if ($amount < 0) {
                $creditAmount = round(abs($amount), 2);
                $this->creditFromAdjustment($contract, $charge, $creditAmount);

                $chargeMeta = is_array($charge->meta) ? $charge->meta : [];
                $chargeMeta['settled_as_credit'] = true;
                $chargeMeta['credit_amount'] = $creditAmount;
                $charge->meta = $chargeMeta;
                $charge->save();
            }

            $this->applyCreditBalanceAction->execute($contract);

            return $charge->refresh();
        }, 3);
    }

    public function settleExistingNegativeAdjustment(Charge $charge): bool
    {
        return DB::transaction(function () use ($charge): bool {
            $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);

            if ($charge->type !== Charge::TYPE_ADJUSTMENT) {
                return false;
            }

            if ((float) $charge->amount >= 0) {
                return false;
            }

            if ((bool) data_get($charge->meta, 'settled_as_credit')) {
                return false;
            }

            $contract = Contract::query()->lockForUpdate()->findOrFail($charge->contract_id);
            $creditAmount = round(abs((float) $charge->amount), 2);
            $this->creditFromAdjustment($contract, $charge, $creditAmount);

            $meta = is_array($charge->meta) ? $charge->meta : [];
            $meta['settled_as_credit'] = true;
            $meta['credit_amount'] = $creditAmount;
            $charge->meta = $meta;
            $charge->save();

            $this->applyCreditBalanceAction->execute($contract);

            return true;
        }, 3);
    }

    private function creditFromAdjustment(Contract $contract, Charge $charge, float $amount): void
    {
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
        $creditBalance->meta = [
            'last_source' => 'adjustment_credit',
            'last_amount' => $amount,
            'source_charge_id' => $charge->id,
        ];
        $creditBalance->save();
    }
}
