<?php

namespace App\Http\Resources\Client;

use App\Client\StackHighlight;
use App\Collection\Support\PostingDay;
use App\Enums\PlanKey;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Support\ClientSafeText;
use App\Outreach\Support\StackNormalizer;
use App\Plans\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `JobCard` contract. Expects `profile`, `company` and `collectionRun` loaded. Never exposes descriptions,
 * raw payloads or contact data; the captured job link (`jobUrl`) is exposed to paid plans only
 * (free users get `null`). `stack` lists up to 6 tags with the user's preferred technologies first;
 * `stackMatches` holds the displayed tags that match those preferences (empty without preferences).
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
        $user = $request->user();
        $paid = $user !== null && app(PlanCatalog::class)->for($user)->key !== PlanKey::Free;
        $preferred = $user instanceof User && $user->jobPreference !== null
            ? StackNormalizer::normalize($user->jobPreference->stack ?? [])
            : [];
        $highlight = StackHighlight::forCard($profile->stack ?? [], $preferred);

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
            'stack' => $highlight['stack'],
            'stackMatches' => $highlight['matches'],
            'summary' => trim($summary) === '' ? null : $summary,
            'firstSeenAt' => $posting->first_seen_at->toIso8601String(),
            'collectedToday' => PostingDay::isToday($posting->collectionRun?->started_at),
            'jobUrl' => $paid ? ApplicationItemResource::safeUrl($posting->applicationUrl()) : null,
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
