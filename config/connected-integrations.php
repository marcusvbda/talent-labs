<?php

use App\Integrations\Gmail\GmailPlugin;

return [
    'state_ttl' => (int) env('OAUTH_CONNECTION_STATE_TTL', 600),

    'plugins' => [
        GmailPlugin::class,
    ],

    'gmail' => [
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.send',
        ],
    ],
];
