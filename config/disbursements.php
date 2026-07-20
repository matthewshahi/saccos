<?php

return [

    /*
    |--------------------------------------------------------------------------
    | General Loan Disbursement Configuration
    |--------------------------------------------------------------------------
    |
    | Each SACCO installation uses one configured outbound disbursement
    | provider.
    |
    | Examples:
    |
    | NCBA:
    |   LOAN_DISBURSEMENT_PROVIDER=NCBA
    |   LOAN_DISBURSEMENT_CHANNEL=MPESA
    |
    | Direct Safaricom M-Pesa B2C:
    |   LOAN_DISBURSEMENT_PROVIDER=MPESA
    |   LOAN_DISBURSEMENT_CHANNEL=MPESA
    |
    */

    'enabled' => env(
        'LOAN_DISBURSEMENT_ENABLED',
        false
    ),

    /*
     * Deliberately no default provider.
     *
     * When disbursement is enabled, the SACCO must explicitly configure
     * NCBA, MPESA, COOP, KCB or another supported provider.
     */
    'provider' => env(
        'LOAN_DISBURSEMENT_PROVIDER'
    ),

    /*
     * Destination or delivery channel.
     *
     * NCBA to M-Pesa:
     *   Provider = NCBA
     *   Channel  = MPESA
     *
     * Direct Safaricom B2C:
     *   Provider = MPESA
     *   Channel  = MPESA
     */
    'channel' => env(
        'LOAN_DISBURSEMENT_CHANNEL'
    ),

    'currency' => env(
        'LOAN_DISBURSEMENT_CURRENCY',
        'KES'
    ),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    */

    'providers' => [

        /*
        |--------------------------------------------------------------------------
        | NCBA Open Banking
        |--------------------------------------------------------------------------
        |
        | These values may remain absent for SACCO installations that do not
        | use NCBA. They are only validated when NCBA is selected.
        |
        */

        'ncba' => [

            'enabled' => env(
                'NCBA_ENABLED',
                false
            ),

            /*
             * Safe default: a missing value must never permit live sending.
             */
            'dry_run' => env(
                'NCBA_DRY_RUN',
                true
            ),

            'base_url' => env(
                'NCBA_BASE_URL'
            ),

            'user_id' => env(
                'NCBA_USER_ID'
            ),

            'password' => env(
                'NCBA_PASSWORD'
            ),

            'subscription_key' => env(
                'NCBA_SUBSCRIPTION_KEY'
            ),

            'debit_account' => env(
                'NCBA_DEBIT_ACCOUNT'
            ),

            'country_code' => env(
                'NCBA_COUNTRY_CODE',
                'KE'
            ),

            'sender_country' => env(
                'NCBA_SENDER_COUNTRY',
                'Kenya'
            ),

            'currency' => env(
                'NCBA_CURRENCY',
                'KES'
            ),

            'connect_timeout' => env(
                'NCBA_CONNECT_TIMEOUT',
                10
            ),

            'http_timeout' => env(
                'NCBA_HTTP_TIMEOUT',
                30
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | Direct Safaricom Daraja M-Pesa B2C
        |--------------------------------------------------------------------------
        |
        | This provider sends loan disbursements directly from the SACCO's
        | M-Pesa B2C account.
        |
        | All variables use the MPESA_B2C prefix so this configuration remains
        | independent from the existing STK Push and C2B integration.
        |
        */

        'mpesa' => [

            /*
             * Controls only direct M-Pesa B2C disbursement.
             */
            'enabled' => env(
                'MPESA_B2C_ENABLED',
                false
            ),

            /*
             * Safe default:
             *
             * true  = generate and validate payloads without transmission
             * false = allow transmission to the configured B2C endpoint
             */
            'dry_run' => env(
                'MPESA_B2C_DRY_RUN',
                true
            ),

            /*
             * Supported application values:
             *
             * sandbox
             * live
             */
            'environment' => env(
                'MPESA_B2C_ENVIRONMENT',
                'sandbox'
            ),

            /*
             * Sandbox:
             * https://sandbox.safaricom.co.ke
             *
             * Live:
             * https://api.safaricom.co.ke
             *
             * The safe default remains sandbox.
             */
            'base_url' => env(
                'MPESA_B2C_BASE_URL',
                'https://sandbox.safaricom.co.ke'
            ),

            /*
             * Daraja application credentials specifically used by the
             * B2C disbursement integration.
             */
            'consumer_key' => env(
                'MPESA_B2C_CONSUMER_KEY'
            ),

            'consumer_secret' => env(
                'MPESA_B2C_CONSUMER_SECRET'
            ),

            /*
             * M-Pesa B2C organization credentials.
             *
             * The security credential is not the STK Push passkey.
             */
            'shortcode' => env(
                'MPESA_B2C_SHORTCODE'
            ),

            'initiator_name' => env(
                'MPESA_B2C_INITIATOR_NAME'
            ),

            'security_credential' => env(
                'MPESA_B2C_SECURITY_CREDENTIAL'
            ),

            /*
             * BusinessPayment is normally appropriate for loan
             * disbursements.
             */
            'command_id' => env(
                'MPESA_B2C_COMMAND_ID',
                'BusinessPayment'
            ),

            /*
             * Public HTTPS callback endpoints.
             */
            'result_url' => env(
                'MPESA_B2C_RESULT_URL'
            ),

            'timeout_url' => env(
                'MPESA_B2C_TIMEOUT_URL'
            ),

            /*
             * Default B2C narration.
             */
            'remarks' => env(
                'MPESA_B2C_REMARKS',
                'Loan disbursement'
            ),

            'occasion' => env(
                'MPESA_B2C_OCCASION',
                'SACCO loan'
            ),

            /*
             * Safaricom Daraja endpoint paths.
             */
            'oauth_path' => env(
                'MPESA_B2C_OAUTH_PATH',
                '/oauth/v1/generate?grant_type=client_credentials'
            ),

            'b2c_path' => env(
                'MPESA_B2C_PAYMENT_PATH',
                '/mpesa/b2c/v1/paymentrequest'
            ),

            'transaction_status_path' => env(
                'MPESA_B2C_TRANSACTION_STATUS_PATH',
                '/mpesa/transactionstatus/v1/query'
            ),

            /*
             * Transaction and HTTP settings.
             */
            'currency' => env(
                'MPESA_B2C_CURRENCY',
                'KES'
            ),

            'connect_timeout' => env(
                'MPESA_B2C_CONNECT_TIMEOUT',
                10
            ),

            'http_timeout' => env(
                'MPESA_B2C_HTTP_TIMEOUT',
                30
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | Future Providers
        |--------------------------------------------------------------------------
        |
        | 'coop' => [
        |     'enabled' => env('COOP_ENABLED', false),
        |     'dry_run' => env('COOP_DRY_RUN', true),
        | ],
        |
        | 'kcb' => [
        |     'enabled' => env('KCB_ENABLED', false),
        |     'dry_run' => env('KCB_DRY_RUN', true),
        | ],
        |
        */
    ],
];