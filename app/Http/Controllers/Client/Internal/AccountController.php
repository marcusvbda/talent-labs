<?php

namespace App\Http\Controllers\Client\Internal;

use App\Actions\Users\DeleteClientAccount;
use App\Exceptions\ClientAccountDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\DeleteAccountRequest;
use App\Http\Requests\Client\UpdateAccountRequest;
use App\Http\Requests\Client\UpdatePasswordRequest;
use App\Http\Resources\Client\AccountResource;
use App\Models\User;
use App\Support\RegionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function show(Request $request): AccountResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AccountResource($user);
    }

    public function update(UpdateAccountRequest $request): AccountResource
    {
        /** @var User $user */
        $user = $request->user();

        $country = $request->filled('country')
            ? mb_strtoupper($request->string('country')->toString())
            : null;

        $user->update([
            'name' => $request->string('name')->toString(),
            'locale' => $request->string('locale')->toString(),
            'timezone' => $request->filled('timezone') ? $request->string('timezone')->toString() : null,
            'country' => $country,
            'region' => RegionResolver::fromCountry($country),
        ]);

        return new AccountResource($user);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $password = $request->string('password')->toString();

        $user->update(['password' => $password]);

        if (config('session.driver') === 'database') {
            Auth::logoutOtherDevices($password);

            // Keep this session valid under AuthenticateSession after the rehash.
            $request->session()->put([
                'password_hash_'.Auth::getDefaultDriver() => $user->getAuthPassword(),
            ]);
        } else {
            $request->session()->regenerate();
        }

        return response()->json([]);
    }

    public function destroy(DeleteAccountRequest $request, DeleteClientAccount $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $action->run($user);
        } catch (ClientAccountDeletionBlocked) {
            return response()->json(['message' => 'This account cannot be deleted from here.'], 409);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([]);
    }
}
