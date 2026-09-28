<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\LiveSendingPresenter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SendingController extends Controller
{
    /**
     * The `LiveSending` payload for the live panel.
     *
     * @return array<string, mixed>
     */
    public function show(Request $request, LiveSendingPresenter $presenter): array
    {
        /** @var User $user */
        $user = $request->user();

        return $presenter->forUser($user);
    }
}
