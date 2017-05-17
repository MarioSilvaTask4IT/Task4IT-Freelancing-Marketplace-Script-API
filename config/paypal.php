<?php

return [
    'merchant_account_id' => env('MERCHANT_ACCOUNT_ID'),
    'identity_token' => env('IDENTITY_TOKEN'),
    'validation_url' => env('VALIDATION_URL'),
    'subscription_types' => array(
        '5.00' => 'month',
        '50.00' => 'year',
    ),
];
