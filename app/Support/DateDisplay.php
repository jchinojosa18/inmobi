<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DateDisplay
{
    public const DATE = 'd/m/Y';

    public const DATETIME = 'd/m/Y H:i';

    public static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public static function formatDate(DateTimeInterface|string|null $value, string $empty = '-'): string
    {
        $parsed = self::parse($value);

        return $parsed?->timezone(self::timezone())->format(self::DATE) ?? $empty;
    }

    public static function formatDateTime(DateTimeInterface|string|null $value, string $empty = '-'): string
    {
        $parsed = self::parse($value);

        return $parsed?->timezone(self::timezone())->format(self::DATETIME) ?? $empty;
    }

    private static function parse(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
