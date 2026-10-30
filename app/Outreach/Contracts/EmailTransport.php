<?php

namespace App\Outreach\Contracts;

use App\Models\ConnectedIntegration;
use App\Outreach\Data\OutboundEmail;

interface EmailTransport
{
    /**
     * Send the message from the integration's account.
     *
     * @return string The provider message id, or $email->messageId when the provider has none.
     */
    public function send(ConnectedIntegration $integration, OutboundEmail $email): string;
}
