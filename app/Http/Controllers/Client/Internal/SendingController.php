<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\LiveSendingPresenter;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\SendingPauseReason;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Outreach\Support\SendScheduler;
use Illuminate\Http\JsonResponse;
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

    /**
     * Pause sending manually. Already paused leaves the pause unchanged.
     *
     * @return array<string, mixed>
     */
    public function pause(Request $request, SendScheduler $scheduler, LiveSendingPresenter $presenter): array
    {
        /** @var User $user */
        $user = $request->user();

        $scheduler->pause($user, SendingPauseReason::Manual);

        return $presenter->forUser($user);
    }

    /**
     * Resume sending; refused while Gmail is not connected, since every queued
     * item would otherwise fail at `checking_gmail`.
     *
     * @return array<string, mixed>|JsonResponse
     */
    public function resume(Request $request, SendScheduler $scheduler, LiveSendingPresenter $presenter): array|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->gmailIntegration?->status !== ConnectedIntegrationStatus::Connected) {
            return response()->json(['message' => __('sending.resume.reconnect_gmail')], 422);
        }

        $scheduler->resume($user);

        return $presenter->forUser($user);
    }
}
