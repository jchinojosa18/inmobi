<?php

namespace App\Actions\Payments;

final class CreditApplicationResult
{
    public function __construct(
        public readonly float $appliedAmount,
        public readonly int $allocationsCount,
        public readonly ?int $paymentId,
    ) {}
}
