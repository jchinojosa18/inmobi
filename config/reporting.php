<?php

use App\Models\Charge;

return [
    /*
    |--------------------------------------------------------------------------
    | Operating Income Charge Types
    |--------------------------------------------------------------------------
    |
    | Charge types considered as operating income when aggregating by
    | payment allocations. Deposit movements are intentionally excluded.
    |
    */
    'operating_income_charge_types' => [
        Charge::TYPE_RENT,
        Charge::TYPE_PENALTY,
        Charge::TYPE_SERVICE,
        Charge::TYPE_OTHER,
        Charge::TYPE_ADJUSTMENT,
    ],
];
