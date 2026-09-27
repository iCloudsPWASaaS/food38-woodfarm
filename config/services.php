<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],


    'paytm-wallet' => [
        'env'              => "",
        'merchant_id'      => "",
        'merchant_key'     => "",
        'merchant_website' => "",
        'channel'          => "",
        'industry_type'    => "",
    ],

    'easypaisa' => [
        'env'              => "",
        'storeId'      => "",
        'hashKey'     => "",
    ],

    'uber' => [
        'client_id'      => env('UBER_CLIENT_ID'),
        'client_secret'  => env('UBER_CLIENT_SECRET'),
        'webhook_secret' => env('UBER_WEBHOOK_SECRET'),
        'environment' => env('UBER_ENVIRONMENT', 'sandbox'), // sandbox or production
        'default_branch_id' => env('UBER_DEFAULT_BRANCH_ID', 1),
        'default_user_id' => env('UBER_DEFAULT_USER_ID', 1),
    ],

];
