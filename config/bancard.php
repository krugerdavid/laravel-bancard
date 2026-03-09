<?php

return [
    'public_key' => env('BANCARD_PUBLIC_KEY'),
    'private_key' => env('BANCARD_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Bancard Environment
    |--------------------------------------------------------------------------
    |
    | Staging: https://vpos.infonet.com.py:8888
    | Production: https://vpos.infonet.com.py
    |
    */
    'staging' => (bool) env('BANCARD_STAGING', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    */
    'timeout' => env('BANCARD_TIMEOUT', 30),
];
