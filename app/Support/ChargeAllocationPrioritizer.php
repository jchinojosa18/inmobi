<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Contract;
use Illuminate\Support\Collection;

class ChargeAllocationPrioritizer
{
    /**
     * @return Collection<int, Charge>
     */
    public function pendingPrioritized(Contract $contract): Collection
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
}
