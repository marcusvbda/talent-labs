<?php

use App\Integrations\Gmail\GmailPlugin;
use App\Outreach\Support\GmailApiMessageSender;
use App\Outreach\Support\SmtpMessageSender;

return [
    'state_ttl' => (int) env('OAUTH_CONNECTION_STATE_TTL', 600),

    'plugins' => [
        GmailPlugin::class,
    ],

    // Email transport per plugin key, resolved by EmailTransportResolver.
    'transports' => [
        'gmail' => GmailApiMessageSender::class,
        'smtp' => SmtpMessageSender::class,
    ],

    'gmail' => [
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.send',
        ],
    ],

    'smtp' => [
        // Verified against each provider's official documentation on 2026-10-30. Port 465 uses
        // implicit TLS, 587 STARTTLS. Zoho paid-organization/custom-domain accounts use
        // smtppro.zoho.com and other Zoho regions their own host: those pick Custom.
        'presets' => [
            'yahoo' => ['host' => 'smtp.mail.yahoo.com', 'port' => 465, 'security' => 'tls'],
            'icloud' => ['host' => 'smtp.mail.me.com', 'port' => 587, 'security' => 'starttls'],
            'zoho' => ['host' => 'smtp.zoho.com', 'port' => 465, 'security' => 'tls'],
            'fastmail' => ['host' => 'smtp.fastmail.com', 'port' => 465, 'security' => 'tls'],
            // Host, port and security come from the user.
            'custom' => null,
        ],

        // Gmail and Microsoft accounts connect through OAuth only, never as Custom SMTP.
        'blocked_hosts' => [
            'smtp.gmail.com',
            'smtp.googlemail.com',
            'smtp.office365.com',
            'smtp-mail.outlook.com',
            'smtp.live.com',
        ],
    ],
];
