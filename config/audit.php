<?php

return [
    'prune' => [
        /*
        |--------------------------------------------------------------------------
        | Default retention windows
        |--------------------------------------------------------------------------
        |
        | These values are used by inmo:logs:prune when --auth-days / --audit-days
        | are not provided explicitly.
        |
        */
        'auth_days' => 90,
        'audit_days' => 180,
    ],
];
