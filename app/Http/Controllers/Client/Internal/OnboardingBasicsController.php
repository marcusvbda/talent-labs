<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\AccountStatusPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateOnboardingBasicsRequest;
use App\Http\Resources\Client\AccountStatusResource;
use App\Models\User;
use App\Support\RegionResolver;

class OnboardingBasicsController extends Controller
{
    public function __invoke(UpdateOnboardingBasicsRequest $request, AccountStatusPresenter $presenter): AccountStatusResource
    {
        /** @var User $user */
        $user = $request->user();

        $country = mb_strtoupper($request->string('country')->toString());

        $user->update([
            'country' => $country,
            'region' => RegionResolver::fromCountry($country),
            'locale' => $request->string('locale')->toString(),
            'timezone' => $request->string('timezone')->toString(),
        ]);

        return new AccountStatusResource($presenter->forUser($user));
    }
}
