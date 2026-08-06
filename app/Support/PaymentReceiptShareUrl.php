<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;

final class PaymentReceiptShareUrl
{
    public static function make(int $paymentId, ?DateTimeInterface $expiresAt = null): string
    {
        $relative = URL::temporarySignedRoute(
            'payments.receipt.share',
            $expiresAt ?? now()->addHours(SignedShareUrl::TTL_HOURS),
            ['paymentId' => $paymentId],
            absolute: false,
        );

        return URL::to($relative);
    }
}
