<?php

namespace App\Support;

class TextCase
{
    public static function upper(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_strtoupper($trimmed, 'UTF-8');
    }

    public static function upperRequired(?string $value): string
    {
        return self::upper($value) ?? '';
    }

    public static function upperLive(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return mb_strtoupper($value, 'UTF-8');
    }
}
