<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\ChartPresenter;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ChartResource;
use App\Models\User;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function __invoke(Request $request, ChartPresenter $presenter): ChartResource
    {
        $request->validate(['range' => ['sometimes', 'string', 'in:14d,30d']]);

        /** @var User $user */
        $user = $request->user();

        return new ChartResource($presenter->forUser($user, $request->string('range', '14d')->toString()));
    }
}
