<?php

namespace App\Http\Resources\Client;

use App\Models\Source;
use Illuminate\Http\Request;

/**
 * The `JobDetail` contract: the card plus details. Expects `profile`, `company` and
 * `source` loaded; the source is exposed by name only.
 */
class JobDetailResource extends JobCardResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $posting = $this->resource;
        /** @var Source $source */
        $source = $posting->source;

        return [
            ...parent::toArray($request),
            'locations' => $posting->profile->locations ?? [],
            'employmentType' => $posting->employment_type,
            'department' => $posting->department,
            'publishedAt' => $posting->published_at?->toIso8601String(),
            'sourceLabel' => $source->name,
        ];
    }
}
