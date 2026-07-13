<?php

return [

    /*
    |--------------------------------------------------------------------------
    | General Loan Disbursement Configuration
    |--------------------------------------------------------------------------
    |
    | Each SACCO installation uses one outbound disbursement provider.
    |
    */

    'enabled' => env(
        'LOAN_DISBURSEMENT_ENABLED',
        false
    ),

    /*
     * Deliberately no default provider.
     *
     * If disbursements are enabled, the SACCO must explicitly configure
     * NCBA, COOP, KCB, MPESA or another supported provider.
     */
    'provider' => env(
        'LOAN_DISBURSEMENT_PROVIDER'
    ),

    'channel' => env(
        'LOAN_DISBURSEMENT_CHANNEL'
    ),

    'currency' => env(
        'LOAN_DISBURSEMENT_CURRENCY',
        'KES'
    ),

    /*
    |--------------------------------------------------------------------------
    | NCBA
    |--------------------------------------------------------------------------
    |
    | These values may remain absent for SACCO installations that do not use
    | NCBA. NCBA is only validated when the selected provider is NCBA.
    |
    */

    'providers' => [

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
        ],

        /*
         * Future providers may be added here.
         *
         * 'coop' => [
         *     'enabled' => env('COOP_ENABLED', false),
         *     ...
         * ],
         *
         * 'kcb' => [
         *     'enabled' => env('KCB_ENABLED', false),
         *     ...
         * ],
         */
    ],
];