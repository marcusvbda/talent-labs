<?php

namespace App\Http\Controllers\Client\Internal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(30)->get();

        return response()->json(NotificationResource::collection($notifications)->resolve($request));
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications->markAsRead();

        return response()->json([]);
    }
}
