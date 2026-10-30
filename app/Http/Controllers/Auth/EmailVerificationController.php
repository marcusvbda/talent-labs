<?php

namespace App\Http\Controllers\Auth;

use App\Client\AccountStatusPresenter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Registration\RegistrationMode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function __construct(
        private AccountStatusPresenter $status,
    ) {}

    public function notice(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->to($this->destination($user));
        }

        $contactEmail = config('talent.contact_email');

        return Inertia::render('auth/verify-email', [
            'contactEmail' => is_string($contactEmail) && $contactEmail !== '' ? $contactEmail : null,
        ]);
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->find($id);
        abort_unless($user !== null && hash_equals($hash, sha1($user->getEmailForVerification())), 403);

        /** @var User|null $current */
        $current = $request->user();
        abort_if($current !== null && $current->id !== $user->id, 403);

        // Invite mode keeps Stage 1: a guest logs in first, then returns here.
        if ($current === null && ! RegistrationMode::isOpen()) {
            return redirect()->guest(route('login'));
        }

        // Only the first use can log in, so a forwarded mail can't be replayed while the link is valid.
        $firstUse = ! $user->hasVerifiedEmail();

        if ($firstUse) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if ($firstUse && RegistrationMode::isOpen()) {
            $this->endOtherSessions($request, $user);
        }

        if ($current === null) {
            if (! $firstUse || $user->is_admin || ! $user->isActive()) {
                return redirect()->route('login');
            }

            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->to($this->destination($user))
            ->with('success', __('auth.verify.verified'));
    }

    public function send(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->to($this->destination($user));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', __('auth.verify.sent'));
    }

    /**
     * Sign the user out everywhere else: rotate the remember token and drop stored sessions but this one.
     */
    private function endOtherSessions(Request $request, User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
    }

    /**
     * Onboarding until it is complete, then the dashboard.
     */
    private function destination(User $user): string
    {
        $status = $this->status->forUser($user);

        return $status['onboarding']['complete'] ? route('dashboard') : route('onboarding');
    }
}
