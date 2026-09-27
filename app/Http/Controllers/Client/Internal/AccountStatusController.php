<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\AccountStatusPresenter;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\AccountStatusResource;
use App\Models\User;
use Illuminate\Http\Request;

class AccountStatusController extends Controller
{
    public function __invoke(Request $request, AccountStatusPresenter $presenter): AccountStatusResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AccountStatusResource($presenter->forUser($user));
    }
}
