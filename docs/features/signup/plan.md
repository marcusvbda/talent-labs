# Plan — Signup

Spec: `docs/features/signup/spec.md` · SHA-256 `0feeec802946ebeb516f0c3f31f814ccd7097f2501984b957644583ebf3486d8`
Run phases with `/exec-phase signup <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — 2026-10-30

Implemented: 2 · Partial: 1 · Divergent: 1 · Missing: 12 · Unverifiable: 0

| Requirement | State | Evidence |
| ----------- | ----- | -------- |
| P1 invite-only, plan from invite, admin changes plan | Implemented | `RegisterController`, `EditUser` |
| P2 `MustVerifyEmail`, no stamp, fires `Registered` | Missing | `app/Models/User.php:5`, `RegisterController.php:59` |
| P3 verification routes and page | Missing | `routes/web.php` |
| P4 `verified` on client routes, 403 message | Missing | `routes/web.php`, `resources/js/data/api.ts` |
| P5 verify page | Missing | `resources/js/pages/auth/` |
| P6 localized email | Missing | Pattern: `app/Notifications/Client/ResetPassword.php` |
| P7 admin-created users verified | Missing | `CreateUser.php` |
| P8 status and actions in `EditUser` | Missing | `EditUser.php`, `UserForm.php` |
| P9 `users:mark-verified` | Missing | `app/Console/Commands/` |
| P10 production flags `log`/`array` | Implemented | `ProductionCheck.php:95` |
| P11 mode switch | Missing | `config/talent.php`, `LandingController.php` |
| P12 open mode without invite | Missing | `RegisterRequest.php` |
| P13 plan never from request, paid via Checkout | Partial | Holds today; must survive P12 |
| P14 Free is free | Divergent (pending) | `config/talent.php` defaults 2500/500/500 |
| P15 captcha, IP limit, disposable domains | Missing | `AddContentSecurityPolicy.php` also needs Cloudflare |
| P16 no enumeration, link logs in | Missing | `unique:users,email`, `Auth::login` in `store` |

## Status board

Phases 1-6 are Stage 1 (beta). Phases 7-15 are Stage 2 (public launch). Do not
set `REGISTRATION_MODE=open` anywhere real before phase 15 passes: open mode is
incomplete until then.

| Phase | Title | Role | Depends on | Origin | Size | Status |
| ----- | ----- | ---- | ---------- | ------ | ---- | ------ |
| 1 | Verification email | laravel-backend | none | P2, P6 | S | PENDING |
| 2 | Verification routes and page | laravel-backend | 1 | P3, P5 | M | PENDING |
| 3 | `users:mark-verified` command | laravel-backend | none | P9 | S | DONE |
| 4 | Admin verification controls | filament-admin | 1 | P7, P8 | S | PENDING |
| 5 | Enforce verification | laravel-backend | 1, 2, 3, 4 | P2, P4 | M | PENDING |
| 6 | Stage 1 verification and report | laravel-backend | 5 | P1, P10 | S | PENDING |
| 7 | Free costs 0 | laravel-backend | none | P14 | S | PENDING |
| 8 | Registration mode switch | laravel-backend | 7 | P11 | M | PENDING |
| 9 | Registration without an invite | laravel-backend | 5, 8 | P12, P13 | M | PENDING |
| 10 | Verification link logs in | laravel-backend | 9 | P16 | S | PENDING |
| 11 | Signup without enumeration | laravel-backend | 10 | P16 | M | PENDING |
| 12 | Turnstile widget and CSP | inertia-frontend | 9 | P15 | M | PENDING |
| 13 | Turnstile enforcement | laravel-backend | 12 | P15 | S | PENDING |
| 14 | IP limit and disposable domains | laravel-backend | 11 | P15 | M | PENDING |
| 15 | Final verification and report | laravel-backend | 6, 10, 11, 13, 14 | all | S | PENDING |

## Owner decisions

All answered on 2026-10-30 and recorded in the spec under `## Decisions`.

### D6 — Wrong address at registration (O1)

Blocks: none · Answer: keep; the invite is consumed at registration, the admin fixes the email in `EditUser` and resends.

### D7 — What unverified users see (O3)

Blocks: none · Answer: only the verify page.

### D8 — `LANDING_BETA_CLOSED` (O2)

Blocks: none · Answer: remove it.

### D9 — Free price (O4)

Blocks: none · Answer: 0 in every region, no Stripe price. The owner sets `PRICE_FREE_*` to 0 in their own `.env`.

### D10 — Disposable-domain source (O5)

Blocks: none · Answer: static list in the repo, updated by hand, no package.

### D11 — Repeat signup on an unverified email (P16)

Blocks: none · Answer: newest signup wins; verifying ends other sessions and rotates `remember_token`.

## Global constraints (every phase)

- Git is read-only for subagents. Commits and pushes happen only through `/exec-phase`.
- Never run `migrate:fresh/refresh/reset/rollback`, `db:wipe`, `DROP` or `TRUNCATE`. No phase needs a migration.
- Do not add, remove or upgrade composer/npm packages.
- Do not write or change tests. Running existing tests is fine. If an existing test fails because of a required behaviour change, report it to the owner; do not edit it.
- All repo text is English. User-facing copy goes in `lang/en.json` and `lang/pt.json` (flat keys) only; there is no Spanish. Admin (Filament) copy is English only.
- Never overwrite existing `.env` values. `.env.example` may change.
- No `->poll()`, `wire:poll` or `refetchInterval`. The page's cooldown timer is local UI state, not data polling.
- Every `throttle:N,1` gets its own third-argument prefix (production-hardening convention).
- `is_admin`, `status`, `plan_key` and `email_verified_at` are not fillable. Write them with `forceFill` or `markEmailAsVerified()`.
- The plan is never read from the request or URL (P13).
- Frontend routes come from Wayfinder (`@/routes`, `@/actions`); these folders are git-ignored and regenerated by `yarn dev` or the Vite plugin. No hardcoded URLs.
- No speculative `useMemo`/`useCallback`/`React.memo` (React Compiler).
- `Assumption` below marks a minor choice made by the plan, not by the owner. Report it if it proves wrong; do not silently change it.

## Requirement coverage

| Requirement | Phases |
| ----------- | ------ |
| P1 | 6 (verify only), 5 (preserved) |
| P2 | 1, 5 |
| P3 | 2 |
| P4 | 5 |
| P5 | 2 |
| P6 | 1 |
| P7 | 4 |
| P8 | 4 |
| P9 | 3 |
| P10 | 6 (verify only) |
| P11 | 8 |
| P12 | 9 |
| P13 | 9, 15 |
| P14 | 7 |
| P15 | 12, 13, 14 |
| P16 | 10, 11 |
| D6-D11 | 2, 5, 7, 8, 14, 10, 11 |

## Phases

### Phase 1 — The app can send a localized verification email

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P2 (model), P6 · Size: S
Origin: P2 "`User` implements `MustVerifyEmail`"; P6 "Verification email: localized to the user's locale (en/pt), product name, link expiry from `auth.verification.expire` (default 60 min)."

**Goal.** `User` can be verified and sends a branded email. Registration does not fire it yet, and nothing is enforced.

**Gap -> desired.** The `MustVerifyEmail` import is commented out and no notification exists -> the contract is implemented and `App\Notifications\Client\VerifyEmail` exists.

**Contract.**

- `app/Models/User.php`: replace the commented import with `use Illuminate\Contracts\Auth\MustVerifyEmail;`, add `MustVerifyEmail` to `implements`. The base `Authenticatable` already provides the trait methods.
- Add to `User`, next to `sendPasswordResetNotification`:

  ```php
  public function sendEmailVerificationNotification(): void
  {
      $this->notify(new VerifyEmail);
  }
  ```

- `app/Notifications/Client/VerifyEmail.php`: `class VerifyEmail extends \Illuminate\Auth\Notifications\VerifyEmail implements ShouldQueue` with `use Queueable`. Mirror `ResetPassword`: `via()` returns `['mail']`; `toMail($notifiable)` returns a `MailMessage` with subject `__('mail.verify.subject', ['brand' => $brand])` and `->view('mail.client.verify-email', ['brand' => $brand, 'url' => $this->verificationUrl($notifiable), 'user' => $notifiable, 'expireMinutes' => config('auth.verification.expire', 60)])`.
- `Assumption:` queued on the default queue. Sent synchronously, a mail failure would return a 500 after the invite was consumed. Locale still comes from `User::preferredLocale()`.
- `resources/views/mail/client/verify-email.blade.php`: copy `reset-password.blade.php`, changing only the `mail.reset.*` keys to `mail.verify.*`.
- Copy (`lang/en.json` / `lang/pt.json`):

  | Key | EN | PT |
  | --- | -- | -- |
  | `mail.verify.subject` | Confirm your :brand email | Confirme seu e-mail no :brand |
  | `mail.verify.greeting` | Hi :name, | Olá, :name, |
  | `mail.verify.body` | Confirm your email address to start using :brand. | Confirme seu endereço de e-mail para começar a usar o :brand. |
  | `mail.verify.button` | Confirm email | Confirmar e-mail |
  | `mail.verify.fallback` | If the button doesn't work, copy and paste this link into your browser: | Se o botão não funcionar, copie e cole este link no seu navegador: |
  | `mail.verify.expiry` | This link expires in :minutes minutes. | Este link expira em :minutes minutos. |
  | `mail.verify.ignore` | If you didn't create a :brand account, you can safely ignore this email. | Se você não criou uma conta no :brand, pode ignorar este e-mail com segurança. |

**Steps.**

1. Change `User`.
2. Add the notification, the view and the copy.

**Done when.**

- `User::factory()->make() instanceof MustVerifyEmail` is true in `php artisan tinker --execute`.
- The notification's `via()` returns `['mail']` and it implements `ShouldQueue`.
- `vendor/bin/pint --dirty --format agent` and `composer types:check` pass.
- `composer test` passes.

**Not in this phase.** Routes (the URL helper needs `verification.verify`, so do not send the email yet), registration changes, enforcement.

### Phase 2 — An authenticated user can confirm their email and resend the link

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: P3, P5, D6 · Size: M
Origin: P3 "Routes (Inertia page `auth/verify-email`, en/pt)… After verification: `onboarding` if not completed, else `dashboard`."; P5 "Verify page: shows the address, resend button with a visible cooldown, and a 'wrong address?' path (logout + contact)."

**Goal.** The three routes and the page work. Nobody is forced through them yet.

**Gap -> desired.** No routes or page -> `EmailVerificationController` and `auth/verify-email`.

**Contract.**

- `app/Http/Controllers/Auth/EmailVerificationController.php`:
  - `notice(Request $request): Response|RedirectResponse`: already verified -> redirect to the destination. Otherwise `Inertia::render('auth/verify-email', ['contactEmail' => $contactEmail])`, where `contactEmail` is `config('talent.contact_email')` when a non-empty string, else `null` (same check as `LandingController`). The address comes from the shared `auth.user.email`.
  - `verify(EmailVerificationRequest $request): RedirectResponse`: `$request->fulfill()`, redirect to the destination with `->with('success', __('auth.verify.verified'))`.
  - `send(Request $request): RedirectResponse`: already verified -> redirect to the destination. Otherwise `$user->sendEmailVerificationNotification()` and `back()->with('success', __('auth.verify.sent'))`.
  - Private destination: `route('onboarding')` when `AccountStatusPresenter::forUser($user)['onboarding']['complete']` is false, else `route('dashboard')`.
- `routes/web.php`, outside the `guest` group:

  ```php
  Route::middleware(['auth', 'client'])->prefix('email')->name('verification.')->group(function (): void {
      Route::get('/verify', [EmailVerificationController::class, 'notice'])->name('notice');
      Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
          ->middleware(['signed', 'throttle:6,1,verification-verify'])
          ->name('verify');
      Route::post('/verification-notification', [EmailVerificationController::class, 'send'])
          ->middleware('throttle:6,1,verification-send')
          ->name('send');
  });
  ```

- `Assumption:` `client` is added beyond P3's `auth` so blocked users are still logged out.
- `Assumption:` an expired or invalid link should not end on a bare 403. In `bootstrap/app.php` `withExceptions`, add a render for `Illuminate\Routing\Exceptions\InvalidSignatureException`: when `$request->routeIs('verification.verify')`, redirect to `route('verification.notice')` with `->with('error', __('auth.verify.link_invalid'))`; otherwise return `null`.
- `resources/js/pages/auth/verify-email.tsx`, using `GuestLayout`, `useT`, `Button`:
  - Heading `auth.verify.title`; body `t('auth.verify.body', { email })`, where `email` is `auth.user.email` from `usePage<SharedProps>()`.
  - Resend button posts `send()` from `@/actions/App/Http/Controllers/Auth/EmailVerificationController`. On success start a 60-second cooldown in local state; while it runs the button is disabled and shows `t('auth.verify.resend_wait', { seconds })`, otherwise `auth.verify.resend`. A `setInterval` in `useEffect` with cleanup is fine.
  - `Assumption:` no cooldown on first load, since a person arriving by login may have an expired link. The 6/min throttle is the backstop.
  - "Wrong address?" block: `auth.verify.wrong_title`, then `t('auth.verify.wrong_contact', { email: contactEmail })` when `contactEmail` is set, else `auth.verify.wrong_no_contact`. A logout button (`user_menu.logout`) calls `router.post(logout().url)`.
- Copy:

  | Key | EN | PT |
  | --- | -- | -- |
  | `auth.verify.title` | Confirm your email | Confirme seu e-mail |
  | `auth.verify.body` | We sent a confirmation link to :email. Open it to continue. | Enviamos um link de confirmação para :email. Abra-o para continuar. |
  | `auth.verify.resend` | Resend email | Reenviar e-mail |
  | `auth.verify.resend_wait` | Resend in :seconds s | Reenviar em :seconds s |
  | `auth.verify.sent` | We sent a new link to your email. | Enviamos um novo link para o seu e-mail. |
  | `auth.verify.verified` | Email confirmed. | E-mail confirmado. |
  | `auth.verify.link_invalid` | This link has expired or is invalid. Request a new one. | Este link expirou ou é inválido. Solicite um novo. |
  | `auth.verify.wrong_title` | Wrong address? | Endereço errado? |
  | `auth.verify.wrong_contact` | Log out and write to :email so we can fix it. | Saia da conta e escreva para :email para corrigirmos. |
  | `auth.verify.wrong_no_contact` | Log out and contact whoever invited you. | Saia da conta e fale com quem enviou seu convite. |

**Steps.**

1. Add the controller, routes and exception render.
2. Add the copy and the page.
3. With `MAIL_MAILER=log`, send the notification to an unverified local user in tinker and open the logged link. Do not change seeded data permanently; use a user you created for this check.

**Done when.**

- `php artisan route:list --name=verification` shows the three routes with the middleware above.
- An unverified user sees the page with their address, resend starts the visible cooldown, and the link marks them verified and lands on `onboarding` or `dashboard`.
- A tampered link redirects to the notice with the error toast; a link for another user returns 403.
- A verified user opening `/email/verify` is redirected away.
- `composer test`, `yarn check` pass.

**Not in this phase.** `verified` on other routes; guest login by link (phase 10).

### Phase 3 — Existing unverified users can be listed and stamped

Status: DONE
Evidence: `php artisan users:mark-verified` dry run listed a throwaway unverified user and changed nothing, then reported none after the user was deleted; `--force` not run against the real DB (code reads `whereNull` so a second run reports none). `pint`, `composer types:check`, `composer lint:check`, `composer test` (33/33) pass. `code-reviewer`: APPROVED.
Role: laravel-backend · Depends on: none · Covers: P9 · Size: S
Origin: P9 "Existing users with `email_verified_at` null: read-only by default command `users:mark-verified {--force}` (dry run lists them; `--force` stamps `now()`). No migration changes existing rows."

**Goal.** Accounts that predate verification don't get locked out at deploy.

**Gap -> desired.** No command -> `users:mark-verified`.

**Contract.**

- `app/Console/Commands/MarkUsersVerified.php`, in the style of `BackfillContacts`. Signature `users:mark-verified {--force : Stamp the listed users}`, description `List users without a verified email; --force stamps them`.
- Query `User::query()->whereNull('email_verified_at')`. Print a table of `id`, `email`, `created_at`. None found -> info line and `SUCCESS`.
- Without `--force`: print the count and say nothing changed. With `--force`: `->update(['email_verified_at' => now()])` and print the count stamped. No realtime event is needed; no listener depends on this column.

**Steps.**

1. Add the command.

**Done when.**

- `php artisan users:mark-verified` lists users and changes nothing.
- `--force` stamps only null rows; a second run reports none.
- `composer types:check` and `composer lint:check` pass.

**Not in this phase.** Running it against any deployed database; that is the owner's deploy step (see phase 5).

### Phase 4 — Admins can see, resend and mark verification

Status: PENDING
Role: filament-admin · Depends on: 1 · Covers: P7, P8, D6 · Size: S
Origin: P7 "Admin-created users (`CreateUser`) are created verified… The admin panel itself does not require verification."; P8 "`EditUser`: show verified status; actions 'Resend verification email' and 'Mark as verified'."

**Goal.** Admins can fix verification problems, and users they create aren't blocked.

**Gap -> desired.** `CreateUser` leaves the column null and `EditUser` shows nothing -> created verified, with status and two actions.

**Contract.**

- `CreateUser::mutateFormDataBeforeCreate`: add `$data['email_verified_at'] = now();`. The existing `forceFill` persists it.
- `UserForm`: a read-only status visible only on the edit operation, label `Email verified`, state `$record->email_verified_at?->toDateTimeString() ?? 'Not verified'`. It must not dehydrate. Use Boost `search-docs` for the right Filament 5 schema component.
- `EditUser::getHeaderActions()`, before `DeleteAction`:
  - `Action::make('resendVerification')`, label `Resend verification email`, `requiresConfirmation()`, visible when `! $record->hasVerifiedEmail()`: `$record->sendEmailVerificationNotification()`, success notification `Verification email sent.`
  - `Action::make('markVerified')`, label `Mark as verified`, `requiresConfirmation()`, same visibility: `$record->markEmailAsVerified()`, success notification `Email marked as verified.`, then `$this->refreshFormData(['email_verified_at'])` or the Filament 5 equivalent so the status updates.
- `Assumption:` changing the email in `EditUser` leaves `email_verified_at` as it is (admins vouch for addresses, as P7 does). For D6: the admin saves the corrected address, then resends. Clients cannot change their email (`AccountController::update` doesn't accept it).
- Do not enable `->emailVerification()` on the panel.

**Steps.**

1. Change `CreateUser`, `UserForm`, `EditUser`.

**Done when.**

- A user created in `/admin` has `email_verified_at` set.
- On an unverified user both actions appear and work, the email lands in the log, and the status updates without a reload.
- On a verified user neither action appears.
- An admin logs in to `/admin` regardless of verification.
- `composer types:check`, `composer lint:check` pass.

**Not in this phase.** A verified column in the users table (not requested).

### Phase 5 — Unverified users are held at the verify page

Status: PENDING
Role: laravel-backend · Depends on: 1, 2, 3, 4 · Covers: P1, P2, P4, D7 · Size: M
Origin: P2 "`RegisterController::store` no longer sets `email_verified_at`; it fires `Registered`"; P4 "Every client route — pages, the `internal.*` group and the `integrations.*` OAuth routes — requires `verified`… `internal.*` JSON requests get 403 with a message the client UI shows. Logout stays reachable."

**Goal.** Email confirmation is required before using the app.

**Gap -> desired.** Registration stamps the date and no route checks it -> registration sends the email and every client route enforces it.

**Contract.**

- `RegisterController::store`: remove `'email_verified_at' => now()`. After the `$user === null` check, call `event(new Registered($user))` (`Illuminate\Auth\Events\Registered`), outside the transaction, then `Auth::login`, session regenerate, `redirect()->route('verification.notice')`. Keep invitation locking and consumption untouched (P1, D6). The framework registers the mail listener automatically; check that exactly one job is queued per registration.
- `app/Http/Middleware/EnsureEmailIsVerified.php` (own class; the framework's returns an untranslated message):

  ```php
  public function handle(Request $request, Closure $next): Response
  {
      $user = $request->user();

      if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
          return $next($request);
      }

      if ($request->expectsJson()) {
          return response()->json(['message' => __('auth.verify.required'), 'code' => 'email_unverified'], 403);
      }

      return redirect()->route('verification.notice');
  }
  ```

- `bootstrap/app.php` aliases: `'verified' => EnsureEmailIsVerified::class`.
- `routes/web.php`: add `verified` directly after `client` on `dashboard`, `onboarding`, `jobs`, `applications`, `profiles`, `preferences`, `plans`, `account` and the `internal.` group; after `auth` on the `integrations.oauth.` group. Keep `plan.jobs` and all throttles. Do not add it to `logout`, `verification.*` or `locale.update`.
- `resources/js/data/api.ts`: in `apiFetch`, when a response is 403 and `data.code === 'email_unverified'`, show `toast.error(message)` once (module-level flag, so parallel requests don't stack toasts) and `router.visit(notice().url)` (`@/routes/verification`). Still throw the `ApiError`. Apply the same in `apiUpload`. This matters because the send/review modals read other 403s as plan limits.
- Copy: `auth.verify.required` — EN `Confirm your email to continue.` / PT `Confirme seu e-mail para continuar.`
- Admins with a null date are also sent to the notice on client routes; the panel is not affected. Seeds and the factory already set the date, so tests keep working.

**Steps.**

1. Add the middleware and alias.
2. Change the routes and `RegisterController`.
3. Change `api.ts` and add the copy.

**Done when.**

- A new registration via invite creates `email_verified_at = null`, consumes the invite, queues one email and lands on the notice.
- While unverified: every page in the list redirects to the notice, `GET /internal/account/status` with `Accept: application/json` returns 403 with the localized message, `/integrations/gmail/connect` redirects, and logout works.
- After clicking the link the app works and the invite's plan is applied.
- `/admin` still works for an admin.
- `composer test`, `yarn check` pass.
- Tell the owner: **before deploying this phase to any environment with existing users, run `php artisan users:mark-verified`, then `--force`.**

**Not in this phase.** Open mode.

### Phase 6 — Stage 1 verified and reported

Status: PENDING
Role: laravel-backend · Depends on: 5 · Covers: P1, P10, all Stage 1 · Size: S
Origin: spec x code gap (verification of Stage 1 before the beta)

**Goal.** Confirm Stage 1 is ready for the beta. Make no feature changes.

**Gap -> desired.** Stage 1 phases done separately -> checked as a whole.

**Contract.**

- P1 and P10 need no code: confirm `/register` without an invite goes to `register.closed` and `app:production-check` still lists `MAIL_MAILER`.
- Greps (no results expected in changed files): `->poll(`, `wire:poll`, `refetchInterval`, Spanish copy.

**Steps.**

1. Run `composer ci:check`.
2. Walk through P1-P10 one by one, recording the evidence for each.
3. Give the owner this manual checklist: register with an invite; confirm from the log link in a second browser (it asks for login, then verifies); resend and see the cooldown; open an expired link; correct a wrong email in `EditUser` and resend; create a user in the admin; change a plan in `EditUser`.

**Done when.**

- `composer ci:check` passes.
- The walkthrough and checklist are in the report.

**Not in this phase.** Any Stage 2 work.

### Phase 7 — Free costs 0

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P14, D9 · Size: S
Origin: P14 "Free must actually be free in open mode: the price config for Free is 0 in every region and no Stripe price is configured for it."

**Goal.** Accounts on the default plan are not priced above 0.

**Gap -> desired.** Defaults of 2500/500/500 and `STRIPE_PRICE_FREE_*` exist -> 0 and removed. This also unblocks P13: `users.plan_source` defaults to `manual`, and `BillingController` answers `contact_us` when a manual user's current plan costs more than 0, so a Free user could not reach Checkout today.

**Contract.**

- `config/talent.php`: `PRICE_FREE_BRL`, `PRICE_FREE_EUR`, `PRICE_FREE_USD` default to `0`. Remove the `'free'` entry from `plans.stripe_prices`.
- `.env.example`: set the three `PRICE_FREE_*` to `0`, remove the three `STRIPE_PRICE_FREE_*`.
- Leave `ProductionCheck`'s warning: it still protects against a stale `.env`.
- Tell the owner: **set `PRICE_FREE_BRL`, `PRICE_FREE_EUR`, `PRICE_FREE_USD` to 0 in your own `.env`.** Never edit `.env` for them.

**Steps.**

1. Change the config and `.env.example`.
2. Check that `landing-plan-card.tsx` and `plans/plan-card.tsx` render a price of 0 acceptably, with values overridden in a tinker or local check. Report if they look wrong; do not redesign them.

**Done when.**

- `grep -rn STRIPE_PRICE_FREE app config .env.example` returns nothing.
- With prices at 0, `POST /internal/billing/checkout` for `free` is refused with `free_plan`, and a Free user can start paid Checkout if billing is enabled.
- `composer test` passes.

**Not in this phase.** Mode switch.

### Phase 8 — One switch controls registration mode

Status: PENDING
Role: laravel-backend · Depends on: 7 · Covers: P11, D5, D8 · Size: M
Origin: P11 "One switch: `talent.registration.mode` (env `REGISTRATION_MODE`, `invite` | `open`, default `invite`). The landing `betaClosed` prop derives from it." + D8 "`LANDING_BETA_CLOSED` is removed."

**Goal.** One setting drives the landing page. `/register` behaviour doesn't change yet.

**Gap -> desired.** `talent.landing.beta_closed` -> `talent.registration.mode`.

**Contract.**

- `config/talent.php`: remove the `landing` block; add `'registration' => ['mode' => env('REGISTRATION_MODE', 'invite')]`.
- `.env.example`: replace `LANDING_BETA_CLOSED=true` with `REGISTRATION_MODE=invite`.
- `app/Registration/RegistrationMode.php`: `final class RegistrationMode { public static function isOpen(): bool { return config('talent.registration.mode') === 'open'; } }`. Any other value counts as invite, so a typo fails closed.
- `LandingController`: `'betaClosed' => ! RegistrationMode::isOpen()`.
- `hero.tsx` and `cta-banner.tsx`: show the open copy when `betaClosed` is false. `CtaBanner` already gets the prop; `Hero` too.
- Copy:

  | Key | EN | PT |
  | --- | -- | -- |
  | `landing.hero.badge_open` | Now open · start for free | Aberto a todos · comece grátis |
  | `landing.cta.title_open` | Start for free today. | Comece grátis hoje. |

- Run `grep -n -i "invite\|beta" lang/en.json` and handle any other landing string shown when the mode is open. `plans.closed_beta.*` isn't tied to the mode: report it to the owner, don't change it.

**Steps.**

1. Change config, env example, class, controller.
2. Change the two components and copy.

**Done when.**

- `grep -rn "LANDING_BETA_CLOSED\|beta_closed" app config resources .env.example` returns nothing.
- `invite`: the landing is unchanged. `open`: CTA links to `/register` and no invite-only copy shows.
- `composer test`, `yarn check` pass.

**Not in this phase.** `/register` still requires an invite.

### Phase 9 — Open mode registers without an invite

Status: PENDING
Role: laravel-backend · Depends on: 5, 8 · Covers: P12, P13 · Size: M
Origin: P12 "`open` mode: `/register` works without an invite; when a usable invite is present it still applies (email lock, plan) and is consumed."; P13 "Without an invite the plan is always the default plan. The plan is never read from the request or URL."

**Goal.** Registration works without an invite in open mode. Invite mode doesn't change.

**Gap -> desired.** An invite is always required -> optional in open mode.

**Contract.**

- `RegisterController::create`: in open mode render `auth/register` with `invite` = `['email' => $invitation->email]` when usable, else `null`; plus prop `open: true`. Invite mode keeps the redirect and passes `open: false`.
- `RegisterRequest`:
  - `invite` rule: open -> `['nullable', 'string']`; invite -> current rule.
  - `after()`: in open mode a blank or unusable invite adds no error and skips the email lock. A usable invite keeps the lock.
  - `Assumption:` an unusable invite in open mode is ignored rather than redirecting to Closed.
- `RegisterController::store`, inside the transaction:
  - Invite mode: unchanged.
  - Open mode: look up with the row lock only when an invite was submitted; treat an unusable one as none.
  - `plan_key` = `$invitation?->planKeyOrDefault() ?? app(PlanCatalog::class)->get((string) config('talent.plans.default'))->key`. Nothing from the request.
  - Mark the invitation used only when there is a usable one.
- PHPStan shape for `$validated`: `invite: string|null` (`$validated['invite'] ?? null`).
- `register.tsx`: prop `invite: { email: string | null } | null`, `open: boolean`; use `invite?.email ?? null` for the lock. Show `auth.register.subtitle_open` when `open`.
- Copy: `auth.register.subtitle_open` — EN `Create your account and start for free.` / PT `Crie sua conta e comece grátis.`
- `Assumption:` `register.closed` stays reachable by direct URL in open mode. Nothing links to it.
- Open mode still logs in and enumerates here; phase 11 fixes that. `REGISTRATION_MODE` stays `invite` outside local checks.

**Steps.**

1. Change request, controller, page, copy.

**Done when.**

- `open`: `/register` renders without an invite; submit gives `plan_key = default`, `email_verified_at = null`. A `plan_key` in the POST body has no effect.
- `open` with a usable invite: email lock and plan apply and the invite is consumed. With an expired one: the form works as no invite.
- `invite`: `/register` with no invite still redirects to Closed.
- `composer test`, `yarn check` pass.

**Not in this phase.** Enumeration, link login, abuse protection.

### Phase 10 — In open mode the verification link logs the person in

Status: PENDING
Role: laravel-backend · Depends on: 9 · Covers: P16 (link), D11 (sessions) · Size: S
Origin: P16 "the signed verification link logs them in"; D11 "Verifying ends the user's other sessions and rotates `remember_token`."

**Goal.** A person who isn't logged in can complete verification from the email.

**Gap -> desired.** `verification.verify` requires `auth` -> it is signed-only and decides what to do in the controller.

**Contract.**

- `routes/web.php`: move `verification.verify` out of the `auth` group: middleware `['client', 'signed', 'throttle:6,1,verification-verify']`, `->whereNumber('id')`. `client` passes guests through.
- `EmailVerificationController::verify(Request $request, int $id, string $hash)`, replacing `EmailVerificationRequest`:

  ```php
  $user = User::query()->find($id);
  abort_unless($user !== null && hash_equals($hash, sha1($user->getEmailForVerification())), 403);

  $current = $request->user();
  abort_if($current !== null && $current->id !== $user->id, 403);

  // Invite mode keeps Stage 1: a guest logs in first, then returns here.
  if ($current === null && ! RegistrationMode::isOpen()) {
      return redirect()->guest(route('login'));
  }

  $firstUse = ! $user->hasVerifiedEmail();

  if ($firstUse) {
      $user->markEmailAsVerified();
      event(new Verified($user));
  }

  if ($firstUse && RegistrationMode::isOpen()) {
      // end other sessions and rotate remember_token (below)
  }

  if ($current === null) {
      if (! $firstUse || $user->is_admin || ! $user->isActive()) {
          return redirect()->route('login');
      }

      Auth::login($user);
      $request->session()->regenerate();
  }

  // redirect to the destination with the verified flash
  ```

- A link logs in only the first time, so a forwarded mail can't be replayed for a login during the 60 minutes.
- End other sessions: `$user->forceFill(['remember_token' => Str::random(60)])->save();` and, when `config('session.driver') === 'database'`, `DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();`.
- Check that the `InvalidSignatureException` render from phase 2 still works for guests: notice -> `auth` -> login, then the notice.

**Steps.**

1. Change route and controller.

**Done when.**

- `open`, logged out, first click: verified, logged in, on `onboarding`. Second click: sent to login, not logged in.
- An older session of that user no longer works after verification.
- `invite`, logged out: login page, then verified after login (Stage 1 behaviour).
- Logged in as a different user: 403. Tampered: redirected with the error.
- `composer test` passes.

**Not in this phase.** The signup screen.

### Phase 11 — Open-mode signup reveals nothing about existing accounts

Status: PENDING
Role: laravel-backend · Depends on: 10 · Covers: P16, D11 · Size: M
Origin: P16 "No account enumeration in open mode: an email that already exists gets the same screen as a new signup ('check your inbox') and the existing owner receives an email saying someone tried to register with it."; D11 "the newest signup wins".

**Goal.** Signup returns the same response whether or not the email exists.

**Gap -> desired.** `unique` rule and immediate login -> same screen in every case.

**Contract.**

- `RegisterRequest`:
  - `prepareForValidation()`: `email` trimmed and lowercased, in both modes. `Assumption:` Postgres `unique` is case-sensitive, so this stops `Foo@x` and `foo@x` becoming two accounts.
  - `unique:users,email` only in invite mode.
- `app/Actions/Users/RegisterOpenClient.php`, `run(array $validated): void`. Look up with `User::query()->whereRaw('lower(email) = ?', [$email])->first()`:
  - **None:** the phase 9 creation in its transaction, then `event(new Registered($user))`. Catch `UniqueConstraintViolationException` from a concurrent signup and treat it as the case below.
  - **Exists, unverified, active, not admin (D11):** `forceFill` new `name`, `password`, `timezone`, save, and send a fresh verification email. The invite is not consumed; plan unchanged.
  - **Exists, verified or admin:** `Hash::make($validated['password'])` and discard (same work as the other paths, so timing doesn't reveal the account), then notify `RegistrationAttempted`.
  - **Exists, blocked:** `Hash::make`, no change, no email.
- `Assumption:` per-address cap on these emails with `RateLimiter::attempt('register-mail:'.sha1($email), 3, ..., 3600)`, so the form can't flood an inbox. D11's password update still happens when capped. A new account's first email is exempt.
- `RegisterController::store`: open mode -> run the action, `redirect()->route('register.check-inbox')`, no `Auth::login`. Invite mode: unchanged, still logged in.
- `app/Notifications/Client/RegistrationAttempted.php`: `extends Notification implements ShouldQueue`, `via` mail; view `resources/views/mail/client/registration-attempted.blade.php`, based on `reset-password.blade.php`, button to `route('password.request')`.
- `routes/web.php` guest group: `Route::inertia('/register/check-inbox', 'auth/check-inbox')->name('register.check-inbox');`
- `resources/js/pages/auth/check-inbox.tsx`: same layout as `closed.tsx`, button `auth.closed.login_link`. It shows no address.
- Copy:

  | Key | EN | PT |
  | --- | -- | -- |
  | `auth.check_inbox.title` | Check your inbox | Verifique sua caixa de entrada |
  | `auth.check_inbox.body` | We sent the next step to the address you entered. Open the email to continue. | Enviamos o próximo passo para o endereço informado. Abra o e-mail para continuar. |
  | `mail.attempt.subject` | Someone tried to sign up to :brand with your email | Alguém tentou se cadastrar no :brand com seu e-mail |
  | `mail.attempt.greeting` | Hi :name, | Olá, :name, |
  | `mail.attempt.body` | Someone tried to create a :brand account with this email, but you already have one. If it was you, log in or reset your password. | Alguém tentou criar uma conta no :brand com este e-mail, mas você já tem uma. Se foi você, entre ou redefina sua senha. |
  | `mail.attempt.button` | Reset password | Redefinir senha |
  | `mail.attempt.ignore` | If it wasn't you, you can safely ignore this email. Your account has not changed. | Se não foi você, pode ignorar este e-mail com segurança. Sua conta não foi alterada. |

**Steps.**

1. Add the action, notification, view and copy.
2. Change request, controller and route; add the page.

**Done when.**

- `open`: new, unverified, verified and blocked addresses all give the identical redirect and page, with no logged-in session.
- Each case does the right thing: new -> verification email; unverified -> password replaced, fresh email; verified -> `mail.attempt.*` email, account unchanged; blocked -> nothing.
- `FOO@x.com` matches the existing `foo@x.com`.
- A fourth repeat within the hour sends no email.
- Password validation errors are identical for known and unknown addresses.
- `invite`: unique error and login behave as Stage 1.
- `composer test`, `yarn check` pass.

**Not in this phase.** Captcha, IP limit, disposable domains.

### Phase 12 — The form shows Turnstile and the CSP allows it

Status: PENDING
Role: inertia-frontend · Depends on: 9 · Covers: P15 (captcha, client) · Size: M
Origin: P15 "captcha on the form (Cloudflare Turnstile, verified server-side over HTTP; adding a package needs the owner's approval)". No package is added.

**Goal.** The widget renders and supplies a token. Nothing enforces it yet.

**Gap -> desired.** No widget and a CSP that would block it -> widget in open mode, CSP allows Cloudflare.

**Contract.**

- `config/talent.php` `registration`: `'turnstile' => ['site_key' => env('TURNSTILE_SITE_KEY'), 'secret_key' => env('TURNSTILE_SECRET_KEY')]`. `.env.example`: both keys, empty.
- `RegisterController::create`: prop `turnstileSiteKey`, the key in open mode when filled, else `null`.
- `AddContentSecurityPolicy`: when `RegistrationMode::isOpen()`, add `https://challenges.cloudflare.com` to `script-src` and `connect-src`, and add `frame-src https://challenges.cloudflare.com`. Otherwise the header doesn't change. The header is report-only today, but keep it correct.
- `register.tsx`: when `turnstileSiteKey` is set, load `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit` once with a `<script>` added in `useEffect`, carrying the page's CSP nonce if one is available; call `turnstile.render` into a container with `sitekey` and `response-field-name: 'captcha'`, which places the token in a hidden `captcha` input in the form. Show `errors.captcha` with the same `role="alert"` pattern as `errors.invite`. Call `turnstile.reset()` after a failed submit (`onError` on `Form`), since tokens are single use.
- `Assumption:` the widget's language follows the app's `locale`, passed as `language` (`en` or `pt-BR`).
- For local checks, use Cloudflare's documented test keys. Don't commit them to `.env.example`.

**Steps.**

1. Add config, prop and CSP.
2. Add the widget.

**Done when.**

- `open` + site key: the widget shows, the submit includes `captcha`, and there are no CSP report entries for Cloudflare.
- `open` without a key, and `invite`: no script loads and the form works.
- `yarn check`, `composer test` pass.

**Not in this phase.** Server-side verification.

### Phase 13 — The server rejects signups without a valid captcha

Status: PENDING
Role: laravel-backend · Depends on: 12 · Covers: P15 (captcha, server) · Size: S
Origin: P15 "captcha on the form (Cloudflare Turnstile, verified server-side over HTTP…)".

**Goal.** A bot can't skip the widget.

**Gap -> desired.** The token is ignored -> verified over HTTP.

**Contract.**

- `app/Registration/TurnstileVerifier.php`: `passes(?string $token, ?string $ip): bool`. `Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => ..., 'response' => $token, 'remoteip' => $ip])`, returning the `success` field as a strict bool. Blank token -> `false`. Connection errors -> `report()` and `false` (fails closed).
- `RegisterRequest::after()`: when open mode and `turnstile.secret_key` is filled, and there are no other errors yet (so a typo doesn't burn the token), a failed check adds `captcha` -> `__('validation.custom.captcha.failed')`.
- Blank secret: the check is skipped, which is the local default. Production is covered by the next item.
- `ProductionCheck::failures()`: in open mode, blank `TURNSTILE_SITE_KEY` or `TURNSTILE_SECRET_KEY` -> failure `open registration needs Turnstile keys.`
- `lang/en/validation.php`, `lang/pt/validation.php` under `custom`: `'captcha' => ['failed' => ...]`: EN `Could not confirm you are human. Please try again.` / PT `Não foi possível confirmar que você é humano. Tente novamente.`

**Steps.**

1. Add the verifier, rule, check and copy.

**Done when.**

- Using `Http::fake` in tinker or Cloudflare's test keys: valid token passes; invalid, missing or timeout is rejected with the message; `invite` mode never calls Cloudflare.
- `php artisan app:production-check` in open mode without keys lists the failure.
- `composer test` passes.

**Not in this phase.** IP limit, disposable domains.

### Phase 14 — Abuse limits: signups per IP and disposable domains

Status: PENDING
Role: laravel-backend · Depends on: 11 · Covers: P15 (limits), D10 · Size: M
Origin: P15 "registrations limited per IP per day (in addition to `throttle:10,1`); disposable-email domains blocked." + D10 "from a static list kept in the repo".

**Goal.** One address can't create unlimited accounts, and throwaway inboxes are rejected.

**Gap -> desired.** Only `throttle:10,1` -> a daily limit and a blocklist in open mode.

**Contract.**

- Config `talent.registration.ip_daily_limit` = `(int) env('REGISTRATION_IP_DAILY_LIMIT', 10)`; add to `.env.example`. `Assumption:` 10, so shared networks aren't blocked.
- `Assumption:` accepted signups count, not validation failures, so typos don't use up the limit. In `RegisterRequest::after()` (open mode) check `RateLimiter::tooManyAttempts('register-ip:'.$this->ip(), $limit)` and add an `email` error, `validation.custom.email.too_many_registrations`. In the controller's open path, `RateLimiter::hit('register-ip:'.$request->ip(), 86400)` for every accepted submission, including existing addresses, so the count reveals nothing.
- `resources/data/disposable-email-domains.txt`: from `https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf`. Confirm the licence in that repo allows copying and put source, licence and date in the phase report. If the download isn't possible, stop and tell the owner; don't write a list from memory.
- `app/Registration/DisposableEmailDomains.php`: `isBlocked(string $email): bool`. Lowercase the domain; match it and each parent domain (`a.mailinator.com` matches `mailinator.com`). Load the file once per instance as a keyed array.
- `RegisterRequest::after()`, open mode: blocked domain -> `email` error `validation.custom.email.disposable`. This applies to new and existing addresses alike, so it reveals nothing. It also applies with an invite.
- `lang/en/validation.php`, `lang/pt/validation.php` under `custom.email`:
  - `too_many_registrations`: EN `Too many sign-ups from this network today. Please try again tomorrow.` / PT `Muitos cadastros nesta rede hoje. Tente novamente amanhã.`
  - `disposable`: EN `Please use a permanent email address.` / PT `Use um endereço de e-mail permanente.`
- `$this->ip()` depends on `TRUSTED_PROXIES` (production-hardening P1, done).

**Steps.**

1. Add the list and the class.
2. Add config, validation, hit and copy.

**Done when.**

- `open`: `x@mailinator.com` and `x@a.mailinator.com` are rejected; `x@gmail.com` passes.
- The 11th accepted signup from one IP in a day is rejected; failed validations don't count.
- `invite`: neither rule applies.
- `composer test` passes.

**Not in this phase.** Automatic list updates.

### Phase 15 — Final verification and report

Status: PENDING
Role: laravel-backend · Depends on: 6, 10, 11, 13, 14 · Covers: all, P13 · Size: S
Origin: spec x code gap (required last phase)

**Goal.** Confirm the whole feature and hand the owner a manual checklist.

**Gap -> desired.** Phases done separately -> checked as a whole, pending changes folded into the spec by `/exec-phase`.

**Contract.**

- Greps (no results expected): `->poll(`, `wire:poll`, `refetchInterval`; Spanish copy in changed files; `LANDING_BETA_CLOSED`; `STRIPE_PRICE_FREE`; `plan_key` read from a request in `RegisterController` or `RegisterRequest`.
- Confirm no package changed: `git diff` on `composer.json`, `composer.lock`, `package.json`, `yarn.lock` across the feature's commits is empty.

**Steps.**

1. Run `composer ci:check`.
2. Walk through P1-P16 and D6-D11, recording evidence for each.
3. Give the owner this manual checklist:
   - Set `PRICE_FREE_*` to 0 in `.env`; set up Turnstile keys and a real mailer.
   - Run `php artisan app:production-check` in open mode.
   - Open mode: sign up new (link logs in), repeat on an unverified address (old password stops working), repeat on a verified address (attempt email, account unchanged), disposable address, stale invite.
   - Free user upgrades through Stripe Checkout.
   - Corporate mail scanners may open the link first. It verifies the account and uses up the one-time login, so the person is sent to the login page; they still have their password. Decide if this is acceptable after real use.
   - Back to invite mode: Stage 1 behaviour is unchanged.

**Done when.**

- `composer ci:check` passes.
- Every requirement has evidence in the report.

**Not in this phase.** Flipping `REGISTRATION_MODE` in production; that is the owner's call.
