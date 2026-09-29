<?php

namespace App\Listeners;

use App\Billing\SyncPlanFromStripe;
use App\Models\User;
use Laravel\Cashier\Events\WebhookHandled;

final class SyncPlanOnStripeWebhook
{
    private const array EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function __construct(private SyncPlanFromStripe $sync) {}

    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;

        if (! in_array($payload['type'] ?? null, self::EVENTS, true)) {
            return;
        }

        $customer = $payload['data']['object']['customer'] ?? null;

        if (! is_string($customer) || $customer === '') {
            return;
        }

        // Same lookup as Cashier::findBillable(), whose `Billable|null` PHPDoc (a trait) hides the User type.
        $user = User::query()->where('stripe_id', $customer)->first();

        if ($user === null) {
            return;
        }

        $this->sync->run($user);
    }
}
