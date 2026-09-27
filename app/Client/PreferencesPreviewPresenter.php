<?php

namespace App\Client;

use App\Models\User;
use App\Outreach\Data\PreferenceCriteria;
use App\Outreach\Queries\MatchingJobPostings;

final class PreferencesPreviewPresenter
{
    /**
     * Build the `PreferencesPreview` contract (resources/js/types/contracts.ts) for a draft of
     * preferences, without saving anything. Counts one per company, like the Jobs list.
     *
     * @return array{matchCount: int, byLanguage: array{en: int, pt: int}}
     */
    public function forUser(User $user, PreferenceCriteria $criteria): array
    {
        $matchCount = MatchingJobPostings::forUser($user, $criteria)
            ->reorder()
            ->distinct()
            ->count('job_postings.company_id');

        /** @var array<string, int|string> $counts */
        $counts = MatchingJobPostings::forUser($user, $criteria, withLanguageRule: false)
            ->reorder()
            ->toBase()
            ->select('job_posting_profiles.language')
            ->selectRaw('count(distinct job_postings.company_id) as aggregate')
            ->whereIn('job_posting_profiles.language', ['en', 'pt'])
            ->groupBy('job_posting_profiles.language')
            ->pluck('aggregate', 'language')
            ->all();

        return [
            'matchCount' => $matchCount,
            'byLanguage' => [
                'en' => (int) ($counts['en'] ?? 0),
                'pt' => (int) ($counts['pt'] ?? 0),
            ],
        ];
    }
}
