<?php

namespace App\Http\Resources\Client;

use App\Models\User;
use App\Support\RegionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `Account` contract.
 *
 * @property User $resource
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'country' => $user->country,
            'region' => ($user->region ?? RegionResolver::fromCountry($user->country))->value,
        ];
    }
}
