<?php

namespace App\Http\Controllers\Client;

use App\Client\ApplicationsPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ApplicationFiltersRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationsPageController extends Controller
{
    public function __invoke(ApplicationFiltersRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('applications', [
            'applications' => ApplicationsPayload::build($user, $request->filters()),
        ]);
    }
}
