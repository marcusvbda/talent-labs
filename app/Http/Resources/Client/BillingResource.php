<?php

namespace App\Http\Resources\Client;

use App\Billing\Billing;
use App\Models\User;
use App\Plans\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;
use Throwable;

/**
 * The `BillingSummary` contract.
 *
 * @property User $resource
 */
class BillingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $plan = app(PlanCatalog::class)->for($user);
        $subscription = $user->subscription('default');

        return [
            'hasCustomer' => $user->hasStripeId(),
            'source' => $user->plan_source->value,
            'plan' => ['key' => $plan->key->value, 'name' => $plan->name],
            'status' => $subscription?->stripe_status,
            'renewsAt' => $this->renewsAt($subscription),
            'endsAt' => $subscription?->onGracePeriod() ? $subscription->ends_at?->toIso8601String() : null,
        ];
    }

    /**
     * Current period end, read from Stripe only for a live, non-cancelled subscription.
     */
    private function renewsAt(?Subscription $subscription): ?string
    {
        if ($subscription === null || ! Billing::available() || ! $subscription->valid() || $subscription->canceled()) {
            return null;
        }

        try {
            $stripeSubscription = $subscription->asStripeSubscription();

            // Current Stripe API versions expose the period on the item; older ones on the subscription.
            $periodEnd = $stripeSubscription->items->data[0]->current_period_end
                ?? $stripeSubscription->current_period_end
                ?? null;

            return is_int($periodEnd) ? Carbon::createFromTimestamp($periodEnd)->toIso8601String() : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
