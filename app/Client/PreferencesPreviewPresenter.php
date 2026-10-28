<?php

namespace App\Client;

use App\Models\User;
use App\Outreach\Data\PreferenceCriteria;
use App\Outreach\Queries\MatchingJobPostings;

final class PreferencesPreviewPresenter
{
    /**
     * Build the `PreferencesPreview` contract (resources/js/types/contracts.ts) for a draft of
     * preferences, without saving anything. Counts postings, like the Jobs list.
     *
     * @return array{matchCount: int, byLanguage: array{en: int, pt: int}}
     */
    public function forUser(User $user, PreferenceCriteria $criteria): array
    {
        $matchCount = MatchingJobPostings::forUser($user, $criteria)
            ->reorder()
            ->count();

        return [
            'matchCount' => $matchCount,
            'byLanguage' => LanguageUnlockCounts::forUser($user, $criteria),
        ];
    }
}
