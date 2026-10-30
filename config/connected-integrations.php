<?php

use App\Integrations\Gmail\GmailPlugin;
use App\Outreach\Support\GmailApiMessageSender;

return [
    'state_ttl' => (int) env('OAUTH_CONNECTION_STATE_TTL', 600),

    'plugins' => [
        GmailPlugin::class,
    ],

    // Email transport per plugin key, resolved by EmailTransportResolver.
    'transports' => [
        'gmail' => GmailApiMessageSender::class,
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
