<?php

namespace App\Support;

final class MoneyToWords
{
    public static function mxn(float $amount): string
    {
        $absolute = round(abs($amount), 2);
        $integerPart = (int) floor($absolute);
        $centavos = (int) round(($absolute - $integerPart) * 100);

        if ($centavos === 100) {
            $integerPart++;
            $centavos = 0;
        }

        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $words = mb_strtoupper((string) $formatter->format($integerPart), 'UTF-8');

        return sprintf('%s PESOS %02d/100 M.N.', $words, $centavos);
    }
}
