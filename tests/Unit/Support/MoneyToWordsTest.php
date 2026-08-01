<?php

namespace Tests\Unit\Support;

use App\Support\MoneyToWords;
use Tests\TestCase;

class MoneyToWordsTest extends TestCase
{
    public function test_mxn_formats_amount_in_spanish_uppercase(): void
    {
        $this->assertSame(
            'NUEVE MIL QUINIENTOS PESOS 00/100 M.N.',
            MoneyToWords::mxn(9500.0),
        );
    }

    public function test_mxn_includes_centavos(): void
    {
        $this->assertSame(
            'CIEN PESOS 50/100 M.N.',
            MoneyToWords::mxn(100.5),
        );
    }
}
