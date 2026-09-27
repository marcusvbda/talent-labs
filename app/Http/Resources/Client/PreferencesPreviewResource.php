<?php

namespace App\Http\Resources\Client;

use App\Client\PreferencesPreviewPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `PreferencesPreview` contract, as built by {@see PreferencesPreviewPresenter}.
 *
 * @property array<string, mixed> $resource
 */
class PreferencesPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
