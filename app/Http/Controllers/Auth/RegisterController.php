<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Users\CreateClientAccount;
use App\Actions\Users\RegisterOpenClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Invitations\InvitationTokens;
use App\Registration\RegistrationMode;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        $siteKey = config('talent.registration.turnstile.site_key');

        return Inertia::render('auth/register', [
            'invite' => $usable ? ['email' => $invitation->email] : null,
            'open' => $open,
            'turnstileSiteKey' => $open && is_string($siteKey) && $siteKey !== '' ? $siteKey : null,
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, invite?: string|null, timezone: string} $validated */
        $validated = $request->validated();

        // Open mode answers the same way whether or not the address has an account.
        if (RegistrationMode::isOpen()) {
            // Every accepted submission counts (new, existing or blocked) so the limit reveals nothing.
            RateLimiter::hit('register-ip:'.$request->ip(), 86400);

            app(RegisterOpenClient::class)->run($validated);

            return redirect()->route('register.check-inbox');
        }

        // A concurrent submit of the same link loses the invitation and lands on Closed.
        $user = app(CreateClientAccount::class)->run($validated, requireInvitation: true);

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
