<?php

namespace App\Http\Controllers\Client\Internal;

use App\Enums\Region;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\PlansResource;
use App\Models\User;
use App\Support\RegionResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlansController extends Controller
{
    public function __invoke(Request $request): PlansResource
    {
        $request->validate(['region' => ['nullable', Rule::enum(Region::class)]]);

        /** @var User $user */
        $user = $request->user();

        $region = $request->enum('region', Region::class)
            ?? $user->region
            ?? RegionResolver::fromCountry($user->country);

        return new PlansResource($user, $region);
    }
}
