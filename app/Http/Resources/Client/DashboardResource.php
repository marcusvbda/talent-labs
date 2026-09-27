<?php

namespace App\Http\Resources\Client;

use App\Client\DashboardPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `DashboardData` contract, as built by {@see DashboardPresenter}.
 *
 * @property array<string, mixed> $resource
 */
class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
