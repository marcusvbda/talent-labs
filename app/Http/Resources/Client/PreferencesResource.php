<?php

namespace App\Http\Resources\Client;

use App\Models\JobPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `Preferences` contract.
 *
 * @property JobPreference $resource
 */
class PreferencesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $preference = $this->resource;

        return [
            'titles' => $preference->titles,
            'seniorities' => $preference->seniorities,
            'stack' => $preference->stack,
            'locations' => $preference->locations,
            'remoteMode' => $preference->remote_mode->value,
            'excludeWords' => $preference->exclude_words,
        ];
    }

    /**
     * The row is created lazily on first read/save; that is not a resource creation, so never 201.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }
}
