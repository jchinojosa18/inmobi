<?php

namespace App\Support;

final class AuditContext
{
    private static ?string $reason = null;

    public static function setReason(?string $reason): void
    {
        self::$reason = self::normalize($reason);
    }

    public static function currentReason(): ?string
    {
        return self::$reason;
    }

    public static function clear(): void
    {
        self::$reason = null;
    }

    private static function normalize(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }
}
