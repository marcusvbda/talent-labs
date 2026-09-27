<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\ApplicationsPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ApplicationFiltersRequest;
use App\Http\Resources\Client\ApplicationDetailResource;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApplicationsController extends Controller
{
    public function index(ApplicationFiltersRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(ApplicationsPayload::build($user, $request->filters(), $request->string('cursor')->toString() ?: null));
    }

    public function counts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bindings = [...ApplicationsPayload::STATUS_GROUPS['in_progress'], ...ApplicationsPayload::STATUS_GROUPS['sent'], ...ApplicationsPayload::STATUS_GROUPS['attention']];

        $row = $user->applications()
            ->toBase()
            ->selectRaw(
                'count(*) as "all", '
                .'count(*) filter (where status in (?, ?)) as in_progress, '
                .'count(*) filter (where status in (?)) as sent, '
                .'count(*) filter (where status in (?, ?)) as attention',
                $bindings,
            )
            ->first();

        return response()->json([
            'all' => (int) ($row->all ?? 0),
            'in_progress' => (int) ($row->in_progress ?? 0),
            'sent' => (int) ($row->sent ?? 0),
            'attention' => (int) ($row->attention ?? 0),
        ]);
    }

    public function show(Request $request, Application $application): ApplicationDetailResource
    {
        Gate::authorize('view', $application);

        $application->load(['company', 'jobPosting.profile', 'user.jobPreference']);

        return new ApplicationDetailResource($application);
    }
}
