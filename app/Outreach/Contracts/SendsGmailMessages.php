<?php

namespace App\Outreach\Contracts;

use App\Models\ConnectedIntegration;

interface SendsGmailMessages
{
    /**
     * Send a raw RFC 822 message from the integration's Gmail account.
     *
     * @return string The Gmail message id.
     */
    public function send(ConnectedIntegration $integration, string $rawMime): string;
}
