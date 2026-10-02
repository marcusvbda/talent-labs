<?php

namespace App\Http\Resources\Client;

use App\Collection\Support\PostingDay;
use App\Models\Company;
use App\Models\JobPosting;
use App\Outreach\Support\ClientSafeText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `JobCard` contract. Expects `profile`, `company` and `collectionRun` loaded. Never exposes links,
 * descriptions, raw payloads or contact data.
 *
 * @property JobPosting $resource
 */
class JobCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $posting = $this->resource;
        $profile = $posting->profile;
        /** @var Company $company */
        $company = $posting->company;

        $summary = ClientSafeText::redact($profile?->summary);

        return [
            'id' => $posting->id,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'initials' => self::initials($company->name),
            ],
            'title' => $posting->title,
            'location' => $posting->location,
            'isRemote' => $profile->is_remote ?? $posting->is_remote,
            'language' => $profile?->language,
            'seniority' => $profile->seniority ?? 'unknown',
            'stack' => array_slice($profile->stack ?? [], 0, 6),
            'summary' => trim($summary) === '' ? null : $summary,
            'firstSeenAt' => $posting->first_seen_at->toIso8601String(),
            'collectedToday' => PostingDay::isToday($posting->collectionRun?->started_at),
        ];
    }

    private static function initials(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim($name)) ?: [],
            fn (string $word): bool => preg_match('/[\p{L}\p{N}]/u', $word) === 1,
        ));

        if ($words === []) {
            return '';
        }

        $first = self::firstCharacter($words[0]);
        $last = count($words) > 1 ? self::firstCharacter($words[count($words) - 1]) : '';

        return mb_strtoupper($first.$last);
    }

    private static function firstCharacter(string $word): string
    {
        return preg_match('/[\p{L}\p{N}]/u', $word, $match) === 1 ? $match[0] : '';
    }
}
