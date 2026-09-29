<?php

namespace App\Http\Resources\Client;

use App\Billing\Billing;
use App\Enums\PlanSource;
use App\Enums\Region;
use App\Models\User;
use App\Plans\Plan;
use App\Plans\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `PlansData` contract, priced for the display region.
 *
 * @property User $resource
 */
class PlansResource extends JsonResource
{
    public function __construct(User $user, private readonly Region $region)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $catalog = app(PlanCatalog::class);
        $currency = $this->region->currency();

        /** @var array<string, mixed> $regions */
        $regions = config('talent.regions', []);

        $contactEmail = config('talent.plans.contact_email');

        return [
            'region' => $this->region->value,
            'regions' => collect(array_keys($regions))
                ->map(fn (string $key): ?Region => Region::tryFrom($key))
                ->filter()
                ->map(fn (Region $region): array => ['key' => $region->value, 'currency' => $region->currency()])
                ->values()
                ->all(),
            'plans' => array_map(fn (Plan $plan): array => [
                'key' => $plan->key->value,
                'name' => $plan->name,
                'mode' => $plan->mode,
                'dailyLimit' => $plan->dailyLimit,
                'price' => $catalog->priceFor($plan->key, $this->region),
                'currency' => $currency,
                'interval' => 'month',
                'highlighted' => $catalog->isHighlighted($plan->key),
            ], $catalog->all()),
            'current' => $catalog->for($this->resource)->key->value,
            'billingAvailable' => Billing::available(),
            'hasSubscription' => $this->resource->subscribed('default'),
            'checkoutBlocked' => $this->resource->plan_source === PlanSource::Manual
                && $catalog->priceFor($this->resource->plan_key, $this->resource->region) > 0,
            'contactEmail' => is_string($contactEmail) && $contactEmail !== '' ? $contactEmail : null,
        ];
    }
}
