<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Region;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Invitations\InvitationTokens;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $invite = $request->query('invite');
        $invitation = InvitationTokens::find(is_string($invite) ? $invite : null);

        if ($invitation === null || ! $invitation->isUsable()) {
            return redirect()->route('register.closed');
        }

        return Inertia::render('auth/register', [
            'invite' => ['email' => $invitation->email],
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, invite: string, timezone: string} $validated */
        $validated = $request->validated();

        // The row lock serialises concurrent submits of the same link: the
        // loser re-reads the invitation as used and lands on Closed.
        $user = DB::transaction(function () use ($validated): ?User {
            $invitation = Invitation::query()
                ->where('token_hash', InvitationTokens::hash($validated['invite']))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isUsable()) {
                return null;
            }

            $user = new User;
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_admin' => false,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'plan_key' => $invitation->planKeyOrDefault(),
                'locale' => app()->getLocale(),
                'timezone' => $validated['timezone'],
                'country' => null,
                'region' => Region::Row,
            ])->save();

            $invitation->forceFill(['used_by' => $user->id, 'used_at' => now()])->save();

            return $user;
        });

        if ($user === null) {
            return redirect()->route('register.closed');
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('onboarding');
    }
}
