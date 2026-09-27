<?php

namespace App\Http\Resources\Client;

use App\Client\AccountStatusPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `AccountStatus` contract, as built by {@see AccountStatusPresenter}.
 *
 * @property array<string, mixed> $resource
 */
class AccountStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
