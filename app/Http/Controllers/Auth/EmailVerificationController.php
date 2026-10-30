<?php

namespace App\Http\Controllers\Auth;

use App\Client\AccountStatusPresenter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        /** @var User $user */
        $user = $request->user();

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
     * Onboarding until it is complete, then the dashboard.
     */
    private function destination(User $user): string
    {
        $status = $this->status->forUser($user);

        return $status['onboarding']['complete'] ? route('dashboard') : route('onboarding');
    }
}
