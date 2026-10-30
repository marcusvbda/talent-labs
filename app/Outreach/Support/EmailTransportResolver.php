<?php

namespace App\Outreach\Support;

use App\Models\ConnectedIntegration;
use App\Outreach\Contracts\EmailTransport;
use LogicException;

class EmailTransportResolver
{
    /**
     * The transport configured for the integration's plugin key.
     */
    public function for(ConnectedIntegration $integration): EmailTransport
    {
        /** @var array<string, class-string<EmailTransport>> $transports */
        $transports = config('connected-integrations.transports', []);
        $class = $transports[$integration->plugin_key] ?? null;

        if ($class === null) {
            throw new LogicException("No email transport is configured for [{$integration->plugin_key}].");
        }

        return app($class);
    }
}
