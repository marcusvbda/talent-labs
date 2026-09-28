<?php

namespace App\Client;

use App\Models\User;
use App\Outreach\Data\PreferenceCriteria;
use App\Outreach\Queries\MatchingJobPostings;

final class LanguageUnlockCounts
{
    /**
     * Pool jobs per language, ignoring the language rule. Counts one per company, like the Jobs list.
     * A null criteria means the user's saved preferences.
     *
     * @return array{en: int, pt: int}
     */
    public static function forUser(User $user, ?PreferenceCriteria $criteria = null): array
    {
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
            'en' => (int) ($counts['en'] ?? 0),
            'pt' => (int) ($counts['pt'] ?? 0),
        ];
    }
}
