<?php

namespace App\Listeners;

use App\Billing\Billing;
use App\Models\User;
use App\Notifications\Client\ClientNotification;
use App\Notifications\Client\PaymentFailed;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookReceived;

final class NotifyPaymentFailed
{
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;

        if (($payload['type'] ?? null) !== 'invoice.payment_failed' || ! Billing::available()) {
            return;
        }

        $eventId = $payload['id'] ?? null;
        $customer = $payload['data']['object']['customer'] ?? null;

        if (! is_string($eventId) || $eventId === '' || ! is_string($customer) || $customer === '') {
            return;
        }

        // Same lookup as Cashier::findBillable(), whose `Billable|null` PHPDoc (a trait) hides the User type.
        $user = User::query()->where('stripe_id', $customer)->first();

        if ($user === null) {
            return;
        }

        // Stripe retries deliveries; only the first one for this event notifies.
        if (! Cache::add('stripe-webhook:'.$eventId, true, now()->addDays(7))) {
            return;
        }

        ClientNotification::send($user, new PaymentFailed);
    }
}
