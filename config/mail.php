<?php

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),

            // ✅ allow self-signed certs / custom port 1587
            'stream' => [
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ],
        ],

        'secondary' => [
            'transport' => env('SECOND_MAIL_MAILER', 'smtp'),
            'host' => env('SECOND_MAIL_HOST', 'smtp.zoho.com'),
            'port' => env('SECOND_MAIL_PORT', 587),
            'encryption' => env('SECOND_MAIL_ENCRYPTION', 'tls'),
            'username' => env('SECOND_MAIL_USERNAME', 'info@mail.shahi.co.ke'),
            'password' => env('SECOND_MAIL_PASSWORD', 'NpL2024?'),
            'timeout' => null,
            'from' => [
                'address' => env('SECOND_MAIL_FROM_ADDRESS', 'info@mail.shahi.co.ke'),
                'name' => env('SECOND_MAIL_FROM_NAME', 'News Letter'),
            ],
        ],

        'newsletters_mailer' => [
            'transport' => 'smtp',
            'host' => env('NEWSLETTERS_MAIL_HOST', 'mail.shahi.co.ke'),
            'port' => env('NEWSLETTERS_MAIL_PORT', 587),
            'encryption' => env('NEWSLETTERS_MAIL_ENCRYPTION', 'tls'),
            'username' => env('NEWSLETTERS_MAIL_USERNAME', 'newsletters'),
            'password' => env('NEWSLETTERS_MAIL_PASSWORD', 'Mail2024!'),
            'timeout' => null,
            'auth_mode' => null,
            'from' => [
                'address' => env('NEWSLETTERS_MAIL_FROM_ADDRESS', 'newsletters@mail.shahi.co.ke'),
                'name' => env('NEWSLETTERS_MAIL_FROM_NAME', 'Your App Name'),
            ],

            // ✅ also allow relaxed SSL for newsletters if same host
            'stream' => [
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ],
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'info@shahi.co.ke'),
        'name' => env('MAIL_FROM_NAME', 'Matthew'),
    ],

    'contact_phone' => env('CONTACT_PHONE', '+254700000000'),

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],
];