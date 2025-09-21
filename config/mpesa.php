<?php

return [
    'env' => env('MPESA_ENV', 'sandbox'),

    'initiator_name'     => env('MPESA_INITIATOR_NAME'),
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),

    'certificates' => [
        'sandbox'    => base_path('config/SandboxCertificate.cer'),
        'live'       => base_path('config/ProductionCertificate.cer'),
        'production' => base_path('config/ProductionCertificate.cer'),
    ],

    'result_url'  => env('MPESA_RESULT_URL', url('/api/mobile/status/result')),
    'timeout_url' => env('MPESA_TIMEOUT_URL', url('/api/mobile/status/timeout')),
];