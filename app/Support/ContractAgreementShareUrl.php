<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;

final class ContractAgreementShareUrl
{
    public static function make(int $contractId, ?DateTimeInterface $expiresAt = null): string
    {
        $relative = URL::temporarySignedRoute(
            'contracts.agreement.share',
            $expiresAt ?? now()->addHours(SignedShareUrl::TTL_HOURS),
            ['contractId' => $contractId],
            absolute: false,
        );

        return URL::to($relative);
    }
}
