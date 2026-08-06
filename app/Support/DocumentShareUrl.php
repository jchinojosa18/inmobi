<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;

final class DocumentShareUrl
{
    public static function make(int $documentId, ?DateTimeInterface $expiresAt = null): string
    {
        $relative = URL::temporarySignedRoute(
            'documents.shared',
            $expiresAt ?? now()->addHours(SignedShareUrl::TTL_HOURS),
            ['documentId' => $documentId],
            absolute: false,
        );

        return URL::to($relative);
    }
}
