<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\AccountStatusPresenter;
use App\Client\ApplicationsPayload;
use App\Enums\ApplicationOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ApplicationFiltersRequest;
use App\Http\Requests\Client\QueueApplicationsRequest;
use App\Http\Requests\Client\QueueRandomApplicationsRequest;
use App\Http\Resources\Client\ApplicationDetailResource;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Actions\QueueApplication;
use App\Outreach\Actions\QueueRandomApplications;
use App\Outreach\Support\QueueRejectionMessage;
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

    /**
     * Queue the selected postings in the given order (Starter plan). Returns the `QueueResult` contract.
     */
    public function store(QueueApplicationsRequest $request, QueueApplication $queueApplication, AccountStatusPresenter $presenter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<int> $jobIds */
        $jobIds = array_map(intval(...), $request->validated('jobIds'));

        $postings = JobPosting::query()->whereKey($jobIds)->get()->keyBy('id');

        $queued = [];
        $rejected = [];

        // Each handle() commits its own transaction, so send slots chain in order.
        foreach ($jobIds as $jobId) {
            $posting = $postings->get($jobId);

            if (! $posting instanceof JobPosting) {
                $rejected[] = ['jobId' => $jobId, 'reason' => QueueRejectionMessage::for('missing')];

                continue;
            }

            $application = $queueApplication->handle($user, $posting, ApplicationOrigin::Manual);

            if ($application === null) {
                $rejected[] = ['jobId' => $jobId, 'reason' => QueueRejectionMessage::for((string) $queueApplication->rejectionReason())];

                continue;
            }

            $queued[] = [
                'jobId' => $jobId,
                'applicationId' => $application->id,
                'scheduledFor' => $application->scheduled_for?->toIso8601String(),
            ];
        }

        return response()->json([
            'queued' => $queued,
            'rejected' => $rejected,
            'quota' => $presenter->quota($user),
        ]);
    }

    /**
     * Queue random matching postings up to today's remaining quota (every plan). Returns the `QueueResult` contract.
     */
    public function random(QueueRandomApplicationsRequest $request, QueueRandomApplications $action, AccountStatusPresenter $presenter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            ...$action->handle($user),
            'quota' => $presenter->quota($user),
        ]);
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

        $application->load(['company', 'jobPosting.profile', 'applicationProfile']);

        return new ApplicationDetailResource($application);
    }
}
