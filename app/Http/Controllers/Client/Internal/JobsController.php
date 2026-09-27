<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\JobPoolQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\JobFiltersRequest;
use App\Http\Resources\Client\JobCardResource;
use App\Http\Resources\Client\JobDetailResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobsController extends Controller
{
    private const PER_PAGE = 20;

    public function index(JobFiltersRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filters = [
            'q' => $request->string('q')->toString(),
            'language' => $request->string('language')->toString(),
            'seniority' => $request->array('seniority'),
            'remote' => $request->string('remote')->toString(),
            'today' => $request->boolean('today'),
            'stack' => $request->array('stack'),
        ];

        $page = JobPoolQuery::forUser($user, $filters)
            ->with(['profile', 'company'])
            ->cursorPaginate(self::PER_PAGE);

        $total = JobPoolQuery::forUser($user, $filters)->reorder()->count();
        $collectedToday = JobPoolQuery::forUser($user, [...$filters, 'today' => true])->reorder()->count();

        return response()->json([
            'data' => JobCardResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'nextCursor' => $page->nextCursor()?->encode(),
                'total' => $total,
            ],
            'summary' => [
                'total' => $total,
                'collectedToday' => $collectedToday,
                'lockedByLanguage' => [],
            ],
        ]);
    }

    public function show(Request $request, int $id): JobDetailResource
    {
        /** @var User $user */
        $user = $request->user();

        $posting = JobPoolQuery::forUser($user)
            ->where('job_postings.id', $id)
            ->with(['profile', 'company', 'source'])
            ->first();

        abort_if($posting === null, 404);

        return new JobDetailResource($posting);
    }
}
