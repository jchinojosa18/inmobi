<?php

namespace App\Actions\Contracts;

class ContractSettlementResult
{
    /**
     * @param  list<int>  $moveoutChargeIds
     */
    public function __construct(
        public readonly string $batchId,
        public readonly float $moveoutTotal,
        public readonly float $outstandingBeforeDeposit,
        public readonly float $depositAvailable,
        public readonly float $depositApplied,
        public readonly float $depositRefund,
        public readonly float $balanceToCollect,
        public readonly ?int $depositApplyChargeId,
        public readonly ?int $refundExpenseId,
        public readonly array $moveoutChargeIds,
    ) {}
}
