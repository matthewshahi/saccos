<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation-level Bulk SMS settings
    |--------------------------------------------------------------------------
    |
    | When BULK_SMS_PROVIDER is set in .env, it takes priority over the
    | provider stored in sacco_defaults. This is appropriate because each
    | SACCO installation has its own database and its own provider account.
    |
    */

    'provider' => env('BULK_SMS_PROVIDER'),

    'default_sender_id' => env(
        'BULK_SMS_DEFAULT_SENDER_ID'
    ),

    /*
    |--------------------------------------------------------------------------
    | Environment-variable registry
    |--------------------------------------------------------------------------
    |
    | Provider configuration rows in the database contain config_env_key.
    | BulkSmsConfigService resolves those keys through this configuration
    | array instead of calling env() while the application is running.
    |
    */

    'environment' => [

        /*
        |--------------------------------------------------------------------------
        | Advanta
        |--------------------------------------------------------------------------
        */

        'ADVANTA_BASE_URL' => env(
            'ADVANTA_BASE_URL',
            'https://quicksms.advantasms.com'
        ),

        'ADVANTA_SEND_URL' => env(
            'ADVANTA_SEND_URL',
            'https://quicksms.advantasms.com/api/services/sendsms'
        ),

        'ADVANTA_BALANCE_URL' => env(
            'ADVANTA_BALANCE_URL',
            'https://quicksms.advantasms.com/api/services/getbalance'
        ),

        'ADVANTA_DLR_URL' => env(
            'ADVANTA_DLR_URL',
            'https://quicksms.advantasms.com/api/services/getdlr'
        ),

        'ADVANTA_API_KEY' => env('ADVANTA_API_KEY'),

        'ADVANTA_PARTNER_ID' => env('ADVANTA_PARTNER_ID'),

        'ADVANTA_SHORTCODE' => env('ADVANTA_SHORTCODE'),

        'ADVANTA_CALLBACK_URL' => env('ADVANTA_CALLBACK_URL'),

        /*
        |--------------------------------------------------------------------------
        | ADTEL
        |--------------------------------------------------------------------------
        */

        'ADTEL_AUTH_URL' => env(
            'ADTEL_AUTH_URL',
            'https://api.adtel.co.ke/oauth/token'
        ),

        'ADTEL_SEND_URL' => env(
            'ADTEL_SEND_URL',
            'https://api.adtel.co.ke/api/v2/sendsms'
        ),

        'ADTEL_USERNAME' => env('ADTEL_USERNAME'),

        'ADTEL_PASSWORD' => env('ADTEL_PASSWORD'),

        'ADTEL_SENDER_ID' => env('ADTEL_SENDER_ID'),

        'ADTEL_ACTION_RESPONSE_URL' => env(
            'ADTEL_ACTION_RESPONSE_URL'
        ),

        'ADTEL_CLIENT_ID' => env('ADTEL_CLIENT_ID'),

        'ADTEL_CLIENT_SECRET' => env(
            'ADTEL_CLIENT_SECRET'
        ),

        'ADTEL_BASIC_AUTH_TOKEN' => env(
            'ADTEL_BASIC_AUTH_TOKEN'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Direct provider configuration
    |--------------------------------------------------------------------------
    |
    | These values allow the provider to work even if a database provider
    | configuration row has not yet been created. The controller bootstrap
    | should still create those rows for administration and diagnostics.
    |
    */

    'providers' => [

        'advanta' => [
            'BASE_URL' => env(
                'ADVANTA_BASE_URL',
                'https://quicksms.advantasms.com'
            ),

            'SEND_URL' => env(
                'ADVANTA_SEND_URL',
                'https://quicksms.advantasms.com/api/services/sendsms'
            ),

            'BALANCE_URL' => env(
                'ADVANTA_BALANCE_URL',
                'https://quicksms.advantasms.com/api/services/getbalance'
            ),

            'DLR_URL' => env(
                'ADVANTA_DLR_URL',
                'https://quicksms.advantasms.com/api/services/getdlr'
            ),

            'API_KEY' => env('ADVANTA_API_KEY'),

            'PARTNER_ID' => env('ADVANTA_PARTNER_ID'),

            'SHORTCODE' => env('ADVANTA_SHORTCODE'),

            'CALLBACK_URL' => env('ADVANTA_CALLBACK_URL'),
        ],

        'adtel' => [
            'AUTH_URL' => env(
                'ADTEL_AUTH_URL',
                'https://api.adtel.co.ke/oauth/token'
            ),

            'SEND_URL' => env(
                'ADTEL_SEND_URL',
                'https://api.adtel.co.ke/api/v2/sendsms'
            ),

            'USERNAME' => env('ADTEL_USERNAME'),

            'PASSWORD' => env('ADTEL_PASSWORD'),

            'SENDER_ID' => env('ADTEL_SENDER_ID'),

            'ACTION_RESPONSE_URL' => env(
                'ADTEL_ACTION_RESPONSE_URL'
            ),

            'CLIENT_ID' => env('ADTEL_CLIENT_ID'),

            'CLIENT_SECRET' => env(
                'ADTEL_CLIENT_SECRET'
            ),

            'BASIC_AUTH_TOKEN' => env(
                'ADTEL_BASIC_AUTH_TOKEN'
            ),
        ],
    ],
];