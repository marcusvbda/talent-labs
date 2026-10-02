<?php

namespace App\Http\Resources\Client;

use App\Enums\PlanKey;
use App\Models\Source;
use App\Plans\PlanCatalog;
use Illuminate\Http\Request;

/**
 * The `JobDetail` contract: the card plus details. Expects `profile`, `company` and
 * `source` loaded; the source is exposed by name only. The captured job link (`jobUrl`)
 * is exposed to paid plans only; free users get `null`.
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
        $user = $request->user();
        $paid = $user !== null && app(PlanCatalog::class)->for($user)->key !== PlanKey::Free;

        return [
            ...parent::toArray($request),
            'locations' => $posting->profile->locations ?? [],
            'employmentType' => $posting->employment_type,
            'department' => $posting->department,
            'publishedAt' => $posting->published_at?->toIso8601String(),
            'sourceLabel' => $source->name,
            'jobUrl' => $paid ? ApplicationItemResource::safeUrl($posting->applicationUrl()) : null,
        ];
    }
}
