<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\DashboardPresenter;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\DashboardResource;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardPresenter $presenter): DashboardResource
    {
        $request->validate(['period' => ['sometimes', 'string', 'in:today,week,month']]);

        /** @var User $user */
        $user = $request->user();

        return new DashboardResource($presenter->forUser($user, $request->string('period', 'today')->toString()));
    }
}
