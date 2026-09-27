<?php

return [
    'brand' => [
        'name' => env('BRAND_NAME', 'Talent Labs'),
        // Wordmark split: first part regular weight, second part bold.
        'wordmark' => [env('BRAND_WORDMARK_REGULAR', 'Talent'), env('BRAND_WORDMARK_BOLD', 'Labs')],
    ],

    'client' => [
        'use_fixtures' => (bool) env('VITE_USE_FIXTURES', false),
    ],

    'locales' => ['en', 'pt'],

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

    'collection' => [
        // RoleFamily values collected postings are matched against.
        'target_role_families' => [
            'backend', 'frontend', 'fullstack', 'software', 'mobile',
            'devops', 'qa', 'support', 'customer_service', 'product',
        ],
    ],

    'plans' => [
        'default' => env('PLAN_DEFAULT', 'free'),
        'catalog' => [
            'free' => ['name' => 'Free', 'mode' => 'auto', 'daily_limit' => (int) env('PLAN_FREE_DAILY_LIMIT', 10)],
            'starter' => ['name' => 'Starter', 'mode' => 'select', 'daily_limit' => (int) env('PLAN_STARTER_DAILY_LIMIT', 50)],
            'pro' => ['name' => 'Pro', 'mode' => 'review', 'daily_limit' => (int) env('PLAN_PRO_DAILY_LIMIT', 150)],
        ],
    ],

    'regions' => [
        'br' => ['currency' => 'BRL', 'countries' => ['BR']],
        // EU-27 + IS, LI, NO, CH, GB.
        'eu' => ['currency' => 'EUR', 'countries' => [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV',
            'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'CH', 'GB',
        ]],
        // Default for every country not listed above.
        'row' => ['currency' => 'USD', 'countries' => []],
    ],

    'invitations' => [
        'default_expiry_days' => (int) env('INVITE_EXPIRY_DAYS', 14),
    ],
];
