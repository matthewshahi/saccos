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
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret'   => env('RECAPTCHA_SECRET_KEY'),
    ],

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
    'mpesa' => [
        'env' => env('MPESA_ENV', 'sandbox'),
    ],

    'ncba' => [
        'enabled' => env('NCBA_ENABLED', false),
        'dry_run' => env('NCBA_DRY_RUN', true),

        'base_url' => rtrim(env('NCBA_BASE_URL', ''), '/'),
        'user_id' => env('NCBA_USER_ID'),
        'password' => env('NCBA_PASSWORD'),
        'subscription_key' => env('NCBA_SUBSCRIPTION_KEY'),

        'debit_account' => env('NCBA_DEBIT_ACCOUNT'),
        'country_code' => env('NCBA_COUNTRY_CODE', 'KE'),
        'sender_country' => env('NCBA_SENDER_COUNTRY', 'Kenya'),
        'currency' => env('NCBA_CURRENCY', 'KES'),
    ],

];
