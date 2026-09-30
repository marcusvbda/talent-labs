<?php

namespace App\Http\Controllers;

use App\Enums\Region;
use App\Plans\PlanCatalog;
use App\Support\RegionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(Request $request, PlanCatalog $catalog): Response
    {
        $contactEmail = config('talent.contact_email');

        return Inertia::render('landing', [
            'plans' => $this->plans($catalog),
            'defaultRegion' => $this->defaultRegion($request)->value,
            'betaClosed' => (bool) config('talent.landing.beta_closed'),
            'pricesAreIllustrative' => (bool) config('talent.landing.prices_illustrative'),
            'contactEmail' => is_string($contactEmail) && $contactEmail !== '' ? $contactEmail : null,
            'legal' => [
                'privacy' => Route::has('privacy') ? route('privacy') : null,
                'terms' => Route::has('terms') ? route('terms') : null,
                'optOut' => Route::has('opt-out') ? route('opt-out') : null,
            ],
        ]);
    }

    /**
     * @return list<array{key: string, mode: string, dailyLimit: int, highlighted: bool, prices: array<string, array{amount: int, currency: string}>}>
     */
    private function plans(PlanCatalog $catalog): array
    {
        $plans = [];

        foreach ($catalog->all() as $plan) {
            $prices = [];

            foreach (Region::cases() as $region) {
                $prices[$region->value] = [
                    'amount' => $catalog->priceFor($plan->key, $region),
                    'currency' => $region->currency(),
                ];
            }

            $plans[] = [
                'key' => $plan->key->value,
                'mode' => $plan->mode,
                'dailyLimit' => $plan->dailyLimit,
                'highlighted' => $catalog->isHighlighted($plan->key),
                'prices' => $prices,
            ];
        }

        return $plans;
    }

    /**
     * Region from the first Accept-Language entry carrying a region subtag (`pt_BR`); none = Region::Row.
     */
    private function defaultRegion(Request $request): Region
    {
        foreach ($request->getLanguages() as $language) {
            $parts = preg_split('/[_-]/', $language) ?: [];

            if (count($parts) > 1) {
                return RegionResolver::fromCountry(end($parts));
            }
        }

        return Region::Row;
    }
}
