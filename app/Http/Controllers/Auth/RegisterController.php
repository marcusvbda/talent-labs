<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Region;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Invitations\InvitationTokens;
use App\Models\Invitation;
use App\Models\User;
use App\Plans\PlanCatalog;
use App\Registration\RegistrationMode;
use Illuminate\Auth\Events\Registered;
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
        $usable = $invitation !== null && $invitation->isUsable();
        $open = RegistrationMode::isOpen();

        if (! $open && ! $usable) {
            return redirect()->route('register.closed');
        }

        return Inertia::render('auth/register', [
            'invite' => $usable ? ['email' => $invitation->email] : null,
            'open' => $open,
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, invite?: string|null, timezone: string} $validated */
        $validated = $request->validated();
        $invite = $validated['invite'] ?? null;
        $open = RegistrationMode::isOpen();

        // The row lock serialises concurrent submits of the same link: the
        // loser re-reads the invitation as used and, in invite mode, lands on
        // Closed; in open mode it registers without the invitation.
        $user = DB::transaction(function () use ($validated, $invite, $open): ?User {
            $invitation = null;

            if ($invite !== null && $invite !== '') {
                $invitation = Invitation::query()
                    ->where('token_hash', InvitationTokens::hash($invite))
                    ->lockForUpdate()
                    ->first();
            }

            if ($invitation !== null && ! $invitation->isUsable()) {
                $invitation = null;
            }

            if ($invitation === null && ! $open) {
                return null;
            }

            $user = new User;
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_admin' => false,
                'status' => UserStatus::Active,
                // Never from the request: the invitation's plan or the configured default.
                'plan_key' => $invitation?->planKeyOrDefault()
                    ?? app(PlanCatalog::class)->get((string) config('talent.plans.default'))->key,
                'locale' => app()->getLocale(),
                'timezone' => $validated['timezone'],
                'country' => null,
                'region' => Region::Row,
            ])->save();

            $invitation?->forceFill(['used_by' => $user->id, 'used_at' => now()])->save();

            return $user;
        });

        if ($user === null) {
            return redirect()->route('register.closed');
        }

        // Outside the transaction so the queued verification mail never points at a rolled-back user.
        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
