<?php

namespace App\Billing;

use App\Enums\PlanSource;
use App\Models\User;
use App\Plans\PlanCatalog;
use RuntimeException;

final class SyncPlanFromStripe
{
    public function __construct(private PlanCatalog $plans) {}

    /**
     * Recompute the user's plan from local subscription state. Idempotent; manual users are never touched.
     */
    public function run(User $user): void
    {
        if (! Billing::available() || $user->plan_source === PlanSource::Manual) {
            return;
        }

        $subscription = $user->subscription('default');

        if ($subscription?->valid()) {
            $plan = $this->plans->planForStripePrice((string) $subscription->stripe_price);

            if ($plan === null) {
                report(new RuntimeException("Unknown Stripe price {$subscription->stripe_price}"));

                return;
            }
        } else {
            $plan = $this->plans->get((string) config('talent.plans.default'))->key;
        }

        if ($user->plan_key === $plan) {
            return;
        }

        $user->plan_key = $plan;
        $user->save();
    }
}
