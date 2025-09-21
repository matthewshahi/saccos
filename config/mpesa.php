<?php

return [
    'env' => env('MPESA_ENV', 'sandbox'),

    'initiator_name'     => env('MPESA_INITIATOR_NAME'),
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),

    'certificates' => [
        'live'    => base_path('config/ProductionCertificate.cer'),
        'default' => base_path('config/SandboxCertificate.cer'),
    ],

    // Always store only relative paths in .env, expanded with url()
    'result_url'  => env('MPESA_RESULT_URL', '/api/mobile/status/result'),
    'timeout_url' => env('MPESA_TIMEOUT_URL', '/api/mobile/status/timeout'),
];