<?php

return [
    'env' => env('MPESA_ENV', 'sandbox'),

    'initiator_name'     => env('MPESA_INITIATOR_NAME'),
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),

    'certificates' => [
        'sandbox'    => base_path('config/SandboxCertificate.cer'),
        'production' => base_path('config/ProductionCertificate.cer'),
    ],

    // ✅ Always expand relative paths into absolute URLs
    'result_url' => function () {
        $value = env('MPESA_RESULT_URL', '/api/mobile/status/result');
        return str_starts_with($value, 'http')
            ? $value
            : rtrim(env('APP_URL'), '/') . '/' . ltrim($value, '/');
    },

    'timeout_url' => function () {
        $value = env('MPESA_TIMEOUT_URL', '/api/mobile/status/timeout');
        return str_starts_with($value, 'http')
            ? $value
            : rtrim(env('APP_URL'), '/') . '/' . ltrim($value, '/');
    },
];