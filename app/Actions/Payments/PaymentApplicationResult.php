<?php

namespace App\Actions\Payments;

final class PaymentApplicationResult
{
    public function __construct(
        public readonly bool $idempotent,
        public readonly float $allocatedAmount,
        public readonly float $creditedAmount,
        public readonly int $allocationsCount,
    ) {}

    public static function idempotent(float $allocatedAmount, float $creditedAmount, int $allocationsCount): self
    {
        return new self(
            idempotent: true,
            allocatedAmount: $allocatedAmount,
            creditedAmount: $creditedAmount,
            allocationsCount: $allocationsCount,
        );
    }
}
