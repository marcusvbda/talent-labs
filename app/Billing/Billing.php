<?php

namespace App\Billing;

final class Billing
{
    /**
     * Billing is live only when enabled and every Stripe credential is configured.
     */
    public static function available(): bool
    {
        return (bool) config('talent.billing.enabled')
            && filled(config('cashier.key'))
            && filled(config('cashier.secret'))
            && filled(config('cashier.webhook.secret'));
    }
}
