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
            $expiresAt ?? now()->addDays(7),
            ['documentId' => $documentId],
            absolute: false,
        );

        return URL::to($relative);
    }
}
