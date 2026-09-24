<?php

return [
    'seed' => [
        'admin' => [
            'name' => env('SEED_ADMIN_NAME'),
            'email' => env('SEED_ADMIN_EMAIL'),
            'password' => env('SEED_ADMIN_PASSWORD'),
        ],
        'client' => [
            'name' => env('SEED_CLIENT_NAME'),
            'email' => env('SEED_CLIENT_EMAIL'),
            'password' => env('SEED_CLIENT_PASSWORD'),
        ],
    ],

    'outreach' => [
        // Test mode: when set, every application email is delivered to this address
        // instead of the company. Empty/unset = emails go to the companies for real.
        'intercept_to' => env('OUTREACH_INTERCEPT_TO'),
    ],

    'contacts' => [
        'smtp_helo' => env('CONTACTS_SMTP_HELO', 'localhost'),
        'smtp_mail_from' => env('CONTACTS_SMTP_MAIL_FROM', ''),
        'smtp_timeout' => 5,
    ],
];
