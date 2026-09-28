<?php

namespace App\Client;

use App\Http\Resources\Client\JobCardResource;
use App\Models\ApplicationProfile;
use App\Models\User;

/**
 * One page of the job pool plus its summary, as returned by `GET /internal/jobs`
 * and passed as the `jobs` prop of the jobs page.
 */
class JobsPayload
{
    private const PER_PAGE = 20;

    /**
     * @param  array{q: string, language: string, seniority: array<mixed>, remote: string, today: bool, stack: array<mixed>}  $filters
     * @param  string|null  $cursor  Encoded cursor; null means the first page.
     * @return array{data: array<mixed>, meta: array{nextCursor: string|null, total: int}, summary: array{total: int, collectedToday: int, lockedByLanguage: array<mixed>}}
     */
    public static function build(User $user, array $filters, ?string $cursor = null): array
    {
        // An empty string decodes to "no cursor" without falling back to the request's query string.
        $page = JobPoolQuery::forUser($user, $filters)
            ->with(['profile', 'company'])
            ->cursorPaginate(self::PER_PAGE, ['*'], 'cursor', $cursor ?? '');

        $total = JobPoolQuery::forUser($user, $filters)->reorder()->count();
        $collectedToday = JobPoolQuery::forUser($user, [...$filters, 'today' => true])->reorder()->count();

        $activeLanguages = ApplicationProfile::activeCompleteLanguagesFor($user);
        $unlockCounts = LanguageUnlockCounts::forUser($user);
        $lockedByLanguage = [];

        foreach (['en', 'pt'] as $language) {
            if (! in_array($language, $activeLanguages, true) && $unlockCounts[$language] > 0) {
                $lockedByLanguage[] = ['language' => $language, 'count' => $unlockCounts[$language]];
            }
        }

        return [
            'data' => JobCardResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'nextCursor' => $page->nextCursor()?->encode(),
                'total' => $total,
            ],
            'summary' => [
                'total' => $total,
                'collectedToday' => $collectedToday,
                'lockedByLanguage' => $lockedByLanguage,
            ],
        ];
    }
}
