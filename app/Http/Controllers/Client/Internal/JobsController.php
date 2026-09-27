<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\JobPoolQuery;
use App\Client\JobsPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\JobFiltersRequest;
use App\Http\Resources\Client\JobDetailResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobsController extends Controller
{
    public function index(JobFiltersRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(JobsPayload::build($user, $request->filters(), $request->string('cursor')->toString() ?: null));
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
