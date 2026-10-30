# Signup

> Body: current behaviour. Verified against the code on 2026-10-30 when the
> last phase of the plan ran (not produced by `/update-feature-spec`; run it
> first if you want it reconciled).
> The **O** tags in `## Decisions` point to the owner's answers (D6 to D11).
>
> Depends on: `production-hardening` P1 (client IP) for the rate limits.
> Stage 1 (invite-only beta) is the default. Stage 2 (open registration) is
> built behind `REGISTRATION_MODE=open` and is for the public launch.

## 1. What it is

How a person gets a client account: invitation links created by an admin,
the registration form, email confirmation, and open registration.

## 2. Current behaviour (2026-10-30)

### Invitations and the form

- **Invitations (admin):** `ListInvitations` "New invitation" action — note,
  optional email (the person must register with that exact address), optional
  plan (`plan_key`; empty = `talent.plans.default`, env `PLAN_DEFAULT`,
  default `free`), expiry in days (default `talent.invitations.default_expiry_days`,
  env `INVITE_EXPIRY_DAYS`, 14). Shows the link once.
- **Tokens:** `InvitationTokens` — 40 random chars; only the SHA-256 hash is
  stored; link `route('register', ['invite' => $token])`.
  `Invitation::isUsable()` = not used, not revoked, not expired.
- **Registration mode:** `talent.registration.mode` (env `REGISTRATION_MODE`,
  `invite` | `open`, default `invite`; any other value counts as `invite`).
  `RegistrationMode::isOpen()` reads it. The landing `betaClosed` prop is
  `! isOpen()`; in open mode the hero badge and CTA use the open copy and the
  CTA links to `/register`. `LANDING_BETA_CLOSED` no longer exists (D8).
- **Form, `invite` mode:** `GET /register` without a usable invite →
  `register.closed`. `RegisterRequest`: name (2–80), email (trimmed,
  lowercased, `unique:users,email`), password (min 10, `confirmed`), invite
  (size 40, required), timezone. An invite with an email requires that email.
  A missing or unusable invite on submit redirects to `register.closed`.
- **Form, `open` mode:** `/register` renders without an invite. The `invite`
  field is optional; a usable invite still applies (email lock, plan) and is
  consumed; a blank, unknown or unusable one is ignored. There is no `unique`
  rule, so the form never says whether an address has an account.
  `register.closed` stays reachable by direct URL; nothing links to it.
- **Plan:** an invitation's plan, else the default plan. It is never read from
  the request or URL (P13). Paid plans only through the existing Stripe
  Checkout (`BillingController::checkout`, `SyncPlanFromStripe`).
- **Free costs 0:** `PRICE_FREE_BRL/EUR/USD` default to `0` and no
  `STRIPE_PRICE_FREE_*` exists (D9). The owner sets the `PRICE_FREE_*` values
  to 0 in their own `.env`.

### Creating the account

- **`invite` mode:** `RegisterController::store` runs `CreateClientAccount`
  (locks the invitation row, creates the user with `forceFill`: `is_admin`
  false, status Active, `email_verified_at` null, plan from the invitation,
  region Row; marks the invitation used), fires `Registered`, logs in and
  redirects to `verification.notice`. The invite is consumed at registration
  (D6). A concurrent submit of the same link redirects to `register.closed`.
- **`open` mode:** `RegisterOpenClient` answers the same way for every address:
  redirect to `register.check-inbox` ("Check your inbox"), no login, no
  address shown. Every branch hashes the password once. The address is matched
  case-insensitively.
    - **New address:** the account is created as above (no invite required) and
      `Registered` sends the verification email. A concurrent signup that wins
      the unique constraint is handled as an existing address.
    - **Existing, unverified, active, not admin:** the newest signup wins (D11):
      name, password and timezone are replaced and a fresh verification email
      is sent. The invite is not consumed and the plan does not change.
    - **Existing, verified or admin:** nothing changes; the owner gets the
      `RegistrationAttempted` email ("someone tried to sign up", button to
      reset the password).
    - **Existing, blocked:** nothing changes and no email.
    - Emails to existing addresses are capped at 3 per hour per address
      (`register-mail:`); the unverified password update still happens when
      capped. A new account's first email is exempt.

### Email verification

- `User` implements `MustVerifyEmail`; `sendEmailVerificationNotification`
  queues `App\Notifications\Client\VerifyEmail`, localized (en/pt) with the
  product name and the link expiry from `auth.verification.expire` (default
  60 min).
- **Routes** (`EmailVerificationController`, Inertia page `auth/verify-email`):
    - `GET /email/verify` → `verification.notice` (`auth`, `client`)
    - `GET /email/verify/{id}/{hash}` → `verification.verify` (`client`,
      `signed`, `throttle:6,1,verification-verify`, numeric id)
    - `POST /email/verification-notification` → `verification.send` (`auth`,
      `client`, `throttle:6,1,verification-send`)
- **After verifying:** `onboarding` if not completed, else `dashboard`, with
  an "Email confirmed." toast. A verified user opening the notice is sent
  there too. An expired or tampered link redirects to the notice with an error
  toast; a link for another user, or with a wrong hash, is a 403.
- **`verify` and guests:** in `invite` mode a guest is sent to login first
  and the link verifies after login. In `open` mode the first click of a link
  verifies and logs the person in (not for admins or blocked users); a repeat
  click only sends to login. On the first verification in `open` mode,
  `remember_token` is rotated and the user's other `database` sessions are
  deleted (D11).
- **Enforcement:** every client route — pages, the `internal.*` group and the
  `integrations.oauth.*` routes — carries the `verified` middleware
  (`EnsureEmailIsVerified`) after `client`/`auth`. Pages redirect to the
  notice; JSON requests get 403 `{message: auth.verify.required, code:
email_unverified}`. Unverified users see only the verify page (D7). Logout,
  `verification.*` and `locale.update` stay reachable. The client UI
  (`apiFetch`/`apiUpload`) shows the message once and visits the notice.
- **Verify page:** shows the address, a resend button with a 60-second visible
  cooldown, and a "wrong address?" block (log out and write to
  `talent.contact_email`, or contact whoever invited you). The admin fixes a
  wrong email in `EditUser` and resends (D6).
- **Admin:** `CreateUser` creates users verified. `EditUser` shows an "Email
  verified" entry and, while unverified, the actions "Resend verification
  email" and "Mark as verified". Changing the email leaves the verified date
  as it is. The admin panel itself does not require verification.
- **Existing users:** `php artisan users:mark-verified` lists users with a null
  `email_verified_at` and changes nothing; `--force` stamps `now()`. No
  migration changes existing rows. Run it (dry run, then `--force`) before
  deploying verification to an environment with existing users.

### Abuse protection (`open` mode only)

- **Captcha:** Cloudflare Turnstile. `register.tsx` loads the widget when
  `TURNSTILE_SITE_KEY` is set; the token goes in the `captcha` field.
  `TurnstileVerifier` checks it over HTTP (no package), fails closed on any
  error, and runs last in `RegisterRequest::after()` and only on an otherwise
  valid form, so a typo does not burn the token. A blank secret skips the
  check (local default). The CSP (report-only header) lists `https://challenges.cloudflare.com`
  (`script-src`, `connect-src`, `frame-src`) only in open mode.
- **IP limit:** `talent.registration.ip_daily_limit` (env
  `REGISTRATION_IP_DAILY_LIMIT`, default 10) accepted signups per IP per day,
  in addition to `throttle:10,1,register`. The controller counts every accepted
  submission (new or existing address); failed validations do not count.
  Over the limit → `email` error `too_many_registrations`.
- **Disposable domains:** `DisposableEmailDomains` rejects an address whose
  domain, or any parent domain, is in `resources/data/disposable-email-domains.txt`
  (static list from the disposable-email-domains project, CC0, updated by
  hand; D10). Error `disposable` on `email`, for every address, with or
  without an invite.
- `app:production-check` fails in open mode when `TURNSTILE_SITE_KEY` or
  `TURNSTILE_SECRET_KEY` is blank, and (production-hardening P13) when the
  mailer is `log` or `array`.

### Mail

- `.env.example` has `MAIL_MAILER=log`; production needs a real mailer.

## 3. Open points

- In open mode, anyone can sign up with someone else's **unverified** address
  and choose its password (D11, newest signup wins). If the real owner then
  clicks the unsolicited verification email, they land in an account whose
  password the other person knows. Other sessions are ended and
  `remember_token` rotates, but the password does not change.
- A usable invite is consumed only when the address is new, so someone holding
  a valid invite can tell from its still-usable state that the address already
  had an account. Anonymous visitors learn nothing.
- Corporate mail scanners may open the verification link first. That
  verifies the account and uses up the one-time login, so the person is sent
  to the login page; they still have their password. Decide after real use
  whether this is acceptable.

## Decisions

- **D1.** (2026-10-30) Beta: registration only through single-use invitation
  links created in the admin.
- **D2.** Password confirmation and email confirmation are required before the
  user can access the app.
- **D3.** Invited users register on Free (unless the invite sets a plan); the
  admin can change the plan for testing.
- **D4.** After the beta anyone can register; paid plans are paid through
  checkout.
- **D5.** One registration-mode switch (`invite` | `open`).
- **D6.** (2026-10-30, O1) The invite stays consumed at registration; the admin
  fixes a wrong email in `EditUser` and resends.
- **D7.** (2026-10-30, O3) Unverified users see only the verify page.
- **D8.** (2026-10-30, O2) `LANDING_BETA_CLOSED` is removed.
- **D9.** (2026-10-30, O4) Free costs 0 in every region and has no Stripe
  price. The owner sets `PRICE_FREE_*` to 0 in their own `.env`.
- **D10.** (2026-10-30, O5) Disposable domains come from a static list in the
  repo, updated by hand; no package.
- **D11.** (2026-10-30, P16) On an existing unverified email the newest signup
  wins; verifying ends other sessions and rotates `remember_token`.
