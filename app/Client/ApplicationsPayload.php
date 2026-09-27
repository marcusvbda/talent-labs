<?php

namespace App\Client;

use App\Http\Resources\Client\ApplicationItemResource;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * One page of the user's applications, as returned by `GET /internal/applications`
 * and passed as the `applications` prop of the applications page.
 */
class ApplicationsPayload
{
    private const PER_PAGE = 20;

    /**
     * Tab => statuses it lists. `all` has no filter.
     *
     * @var array<string, list<string>>
     */
    public const STATUS_GROUPS = [
        'in_progress' => ['queued', 'sending'],
        'sent' => ['sent'],
        'attention' => ['failed', 'ambiguous'],
    ];

    /**
     * @param  array{status: string, language: string, q: string}  $filters
     * @param  string|null  $cursor  Encoded cursor; null means the first page.
     * @return array{data: array<mixed>, meta: array{nextCursor: string|null, total: int}}
     */
    public static function build(User $user, array $filters, ?string $cursor = null): array
    {
        // An empty string decodes to "no cursor" without falling back to the request's query string.
        $page = self::query($user, $filters)
            ->with(['company', 'jobPosting.profile'])
            ->orderByDesc('applications.updated_at')
            ->orderByDesc('applications.id')
            ->cursorPaginate(self::PER_PAGE, ['*'], 'cursor', $cursor ?? '');

        // Every row belongs to the requesting user; reuse it instead of loading it per row.
        $page->getCollection()->each(fn (Application $application) => $application->setRelation('user', $user));

        $total = self::query($user, $filters)->count();

        return [
            'data' => ApplicationItemResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'nextCursor' => $page->nextCursor()?->encode(),
                'total' => $total,
            ],
        ];
    }

    /**
     * The user's applications with the list filters applied, unordered and unpaginated.
     *
     * @param  array{status: string, language: string, q: string}  $filters
     * @return Builder<Application>
     */
    private static function query(User $user, array $filters): Builder
    {
        $query = Application::query()
            ->select('applications.*')
            ->where('applications.user_id', $user->id)
            ->join('companies', 'companies.id', '=', 'applications.company_id')
            ->leftJoin('job_postings', 'job_postings.id', '=', 'applications.job_posting_id')
            ->leftJoin('job_posting_profiles', 'job_posting_profiles.job_posting_id', '=', 'job_postings.id');

        if (isset(self::STATUS_GROUPS[$filters['status']])) {
            $query->whereIn('applications.status', self::STATUS_GROUPS[$filters['status']]);
        }

        if (in_array($filters['language'], ['en', 'pt'], true)) {
            // Mirrors ApplicationItemResource::language(): stored language, then the posting
            // profile's en/pt language, then the owner's en/pt locale, then en. The owner is
            // the same for every row, so the last two steps collapse to one bound value.
            $ownerFallback = in_array($user->locale, ['en', 'pt'], true) ? $user->locale : 'en';

            $query->whereRaw(
                "coalesce(nullif(applications.language, ''), case when job_posting_profiles.language in ('en', 'pt') then job_posting_profiles.language end, ?) = ?",
                [$ownerFallback, $filters['language']],
            );
        }

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('companies.name', 'ilike', $term)
                    ->orWhere('job_postings.title', 'ilike', $term);
            });
        }

        return $query;
    }
}
