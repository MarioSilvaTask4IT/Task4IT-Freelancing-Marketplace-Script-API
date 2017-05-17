<?php

return array(

    'live' => [
        'public_key' => env('PAYMILL_PUBLIC_KEY'),
        'private_key' => env('PAYMILL_PRIVATE_KEY'),
    ],

    'test' => [
        'public_key' => env('PAYMILL_PUBLIC_KEY'),
        'private_key' => env('PAYMILL_PRIVATE_KEY'),
    ]

);
