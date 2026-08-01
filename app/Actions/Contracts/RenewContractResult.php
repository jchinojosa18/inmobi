<?php

namespace App\Actions\Contracts;

use App\Models\Charge;
use App\Models\Contract;

final readonly class RenewContractResult
{
    public function __construct(
        public Contract $newContract,
        public Contract $oldContract,
        public ?Charge $transferOutCharge,
        public ?Charge $transferredHoldCharge,
        public ?Charge $differenceHoldCharge,
        public float $transferredAmount,
        public float $differenceAmount,
    ) {}
}
