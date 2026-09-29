<?php

namespace App\Http\Controllers\Client\Internal;

use App\Billing\Billing;
use App\Enums\PlanKey;
use App\Enums\PlanSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\BillingResource;
use App\Models\User;
use App\Plans\PlanCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BillingController extends Controller
{
    /**
     * Start a Stripe Checkout for the chosen plan, priced for the user's region.
     */
    public function checkout(Request $request, PlanCatalog $catalog): JsonResponse
    {
        $request->validate(['plan' => ['required', Rule::enum(PlanKey::class)]]);

        /** @var User $user */
        $user = $request->user();

        /** @var PlanKey $plan */
        $plan = $request->enum('plan', PlanKey::class);
        $region = $user->region;

        if (! Billing::available()) {
            return $this->refuse('unavailable');
        }

        if ($plan === $user->plan_key) {
            return $this->refuse('already_on_plan');
        }

        if ($user->plan_source === PlanSource::Manual && $catalog->priceFor($user->plan_key, $region) > 0) {
            return $this->refuse('contact_us');
        }

        if ($user->subscribed('default')) {
            return $this->refuse('use_portal');
        }

        if ($catalog->priceFor($plan, $region) === 0) {
            return $this->refuse('free_plan');
        }

        $priceId = $catalog->stripePriceId($plan, $region);

        if ($priceId === null) {
            $envKey = 'STRIPE_PRICE_'.strtoupper($plan->value).'_'.strtoupper($region->currency());

            report(new RuntimeException("Missing Stripe price for plan [{$plan->value}] in region [{$region->value}]: set {$envKey}."));

            return $this->refuse('unavailable');
        }

        if ($user->plan_source === PlanSource::Manual) {
            $user->forceFill(['plan_source' => PlanSource::Stripe])->save();
        }

        $checkout = $user->newSubscription('default', $priceId)->checkout([
            'success_url' => route('plans').'?checkout=success',
            'cancel_url' => route('plans').'?checkout=cancel',
        ]);

        return response()->json(['url' => $checkout->asStripeCheckoutSession()->url]);
    }

    /**
     * Open the Stripe Billing Portal, returning to the given client page.
     */
    public function portal(Request $request): JsonResponse
    {
        $request->validate(['returnTo' => ['nullable', Rule::in(['plans', 'account'])]]);

        /** @var User $user */
        $user = $request->user();

        if (! Billing::available()) {
            return $this->refuse('unavailable');
        }

        if (! $user->hasStripeId()) {
            return $this->refuse('no_customer');
        }

        $returnTo = $request->string('returnTo')->toString() ?: 'account';

        return response()->json(['url' => $user->billingPortalUrl(route($returnTo))]);
    }

    /**
     * The `BillingSummary` payload.
     */
    public function show(Request $request): BillingResource
    {
        /** @var User $user */
        $user = $request->user();

        return new BillingResource($user);
    }

    private function refuse(string $code): JsonResponse
    {
        return response()->json(['message' => __("billing.errors.{$code}")], 409);
    }
}
