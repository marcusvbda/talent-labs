<?php

namespace App\Http\Resources\Client;

use App\Client\ChartPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `ChartData` contract, as built by {@see ChartPresenter}.
 *
 * @property array<string, mixed> $resource
 */
class ChartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
