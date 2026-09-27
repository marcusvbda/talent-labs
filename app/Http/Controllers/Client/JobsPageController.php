<?php

namespace App\Http\Controllers\Client;

use App\Client\JobsPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\JobFiltersRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class JobsPageController extends Controller
{
    public function __invoke(JobFiltersRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('jobs', [
            'jobs' => JobsPayload::build($user, $request->filters()),
        ]);
    }
}
