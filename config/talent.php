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

    // `locales`: everything that can be resolved/cookied (public pages).
    // `app_locales`: locales the logged-in app is translated to.
    'locales' => ['en', 'pt', 'es'],

    'app_locales' => ['en', 'pt'],

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
        'interval_min_seconds' => (int) env('OUTREACH_SEND_INTERVAL_MIN_SECONDS', 15),
        'interval_max_seconds' => (int) env('OUTREACH_SEND_INTERVAL_MAX_SECONDS', 15),
        'step_delay_ms' => (int) env('OUTREACH_STEP_DELAY_MS', 1200),
        'window' => [
            'enabled' => (bool) env('OUTREACH_WINDOW_ENABLED', false),
            'start' => env('OUTREACH_WINDOW_START', '00:00'),
            'end' => env('OUTREACH_WINDOW_END', '23:59'),
            'weekdays_only' => (bool) env('OUTREACH_WINDOW_WEEKDAYS_ONLY', false),
        ],
        'auto_pause_after_failures' => (int) env('OUTREACH_AUTO_PAUSE_AFTER_FAILURES', 3),
    ],

    'contacts' => [
        'smtp_helo' => env('CONTACTS_SMTP_HELO', 'localhost'),
        'smtp_mail_from' => env('CONTACTS_SMTP_MAIL_FROM', ''),
        'smtp_timeout' => 5,
    ],

    'collection' => [
        // RoleFamily values collected postings are matched against.
        'target_role_families' => [
            'backend',
            'frontend',
            'fullstack',
            'software',
            'mobile',
            'devops',
            'qa',
            'support',
            'customer_service',
            'product',
        ],
    ],

    'matching' => [
        // Postings with unclassified seniority always pass a seniority filter.
        'unknown_seniority_passes' => (bool) env('MATCHING_UNKNOWN_SENIORITY_PASSES', true),
    ],

    'plans' => [
        'default' => env('PLAN_DEFAULT', 'free'),
        'catalog' => [
            'free' => ['name' => 'Free', 'mode' => 'random', 'daily_limit' => (int) env('PLAN_FREE_DAILY_LIMIT', 25)],
            'starter' => ['name' => 'Starter', 'mode' => 'select', 'daily_limit' => (int) env('PLAN_STARTER_DAILY_LIMIT', 50)],
            'pro' => ['name' => 'Pro', 'mode' => 'review', 'daily_limit' => (int) env('PLAN_PRO_DAILY_LIMIT', 150)],
        ],
        // Displayed prices, in minor units per region. 0 = free in that region (no Checkout).
        'prices' => [
            'free' => [
                'br' => (int) env('PRICE_FREE_BRL', 2500),
                'eu' => (int) env('PRICE_FREE_EUR', 500),
                'row' => (int) env('PRICE_FREE_USD', 500),
            ],
            'starter' => [
                'br' => (int) env('PRICE_STARTER_BRL', 5000),
                'eu' => (int) env('PRICE_STARTER_EUR', 900),
                'row' => (int) env('PRICE_STARTER_USD', 1000),
            ],
            'pro' => [
                'br' => (int) env('PRICE_PRO_BRL', 10000),
                'eu' => (int) env('PRICE_PRO_EUR', 1900),
                'row' => (int) env('PRICE_PRO_USD', 2000),
            ],
        ],
        // Charged prices: Stripe Price IDs per plan and region. Keep them equal to `prices`.
        'stripe_prices' => [
            'free' => [
                'br' => env('STRIPE_PRICE_FREE_BRL'),
                'eu' => env('STRIPE_PRICE_FREE_EUR'),
                'row' => env('STRIPE_PRICE_FREE_USD'),
            ],
            'starter' => [
                'br' => env('STRIPE_PRICE_STARTER_BRL'),
                'eu' => env('STRIPE_PRICE_STARTER_EUR'),
                'row' => env('STRIPE_PRICE_STARTER_USD'),
            ],
            'pro' => [
                'br' => env('STRIPE_PRICE_PRO_BRL'),
                'eu' => env('STRIPE_PRICE_PRO_EUR'),
                'row' => env('STRIPE_PRICE_PRO_USD'),
            ],
        ],
        'highlighted' => env('PLAN_HIGHLIGHTED', 'starter'),
        'contact_email' => env('PLANS_CONTACT_EMAIL'),
    ],

    'billing' => [
        'enabled' => (bool) env('BILLING_ENABLED', false),
    ],

    'regions' => [
        'br' => ['currency' => 'BRL', 'countries' => ['BR']],
        // EU-27 + IS, LI, NO, CH, GB.
        'eu' => ['currency' => 'EUR', 'countries' => [
            'AT',
            'BE',
            'BG',
            'HR',
            'CY',
            'CZ',
            'DK',
            'EE',
            'FI',
            'FR',
            'DE',
            'GR',
            'HU',
            'IE',
            'IT',
            'LV',
            'LT',
            'LU',
            'MT',
            'NL',
            'PL',
            'PT',
            'RO',
            'SK',
            'SI',
            'ES',
            'SE',
            'IS',
            'LI',
            'NO',
            'CH',
            'GB',
        ]],
        // Default for every country not listed above.
        'row' => ['currency' => 'USD', 'countries' => []],
    ],

    'invitations' => [
        'default_expiry_days' => (int) env('INVITE_EXPIRY_DAYS', 14),
    ],

    'landing' => [
        // Closed beta: the public landing page shows "request access" instead of open signup.
        'beta_closed' => (bool) env('LANDING_BETA_CLOSED', true),
        // Shows a note that the displayed plan prices are illustrative, not final.
        'prices_illustrative' => (bool) env('LANDING_PRICES_ILLUSTRATIVE', true),
    ],

    // Public contact address shown on the landing page. Empty/unset = hidden.
    'contact_email' => env('TALENT_CONTACT_EMAIL'),
];
