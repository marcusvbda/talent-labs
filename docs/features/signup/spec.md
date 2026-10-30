# Signup

> Body: current behaviour, read from the code on 2026-10-30 (not produced by
> `/update-feature-spec`; run it first if you want it reconciled).
> `## Pending changes` holds what the owner decided on 2026-10-30. Items
> marked **O** are open decisions: `/plan-feature` asks the owner, never
> guesses.
>
> Depends on: `production-hardening` P1 (client IP) for the rate limits.
> Stage 1 is needed for the beta; Stage 2 is for the public launch.

## 1. What it is

How a person gets a client account: invitation links created by an admin,
the registration form, email confirmation, and later open registration.

## 2. Current behaviour (2026-10-30)

- **Invitations (admin):** `ListInvitations` "New invitation" action — note,
  optional email (the person must register with that exact address), optional
  plan (`plan_key`; empty = `talent.plans.default`, env `PLAN_DEFAULT`,
  default `free`), expiry in days (default `talent.invitations.default_expiry_days`,
  env `INVITE_EXPIRY_DAYS`, 14). Shows the link once.
- **Tokens:** `InvitationTokens` — 40 random chars; only the SHA-256 hash is
  stored; link `route('register', ['invite' => $token])`.
  `Invitation::isUsable()` = not used, not revoked, not expired.
- **Form:** `GET /register` without a usable invite → `register.closed`.
  `RegisterRequest`: name (2–80), email (unique), password (min 10,
  `confirmed`), invite (size 40), timezone. An invite with an email requires
  that email.
- **Store:** `RegisterController::store` locks the invitation row, creates the
  user with `forceFill` (`is_admin` false, status Active, **`email_verified_at
  = now()`**, plan from the invitation, region Row), marks the invitation used,
  logs in and redirects to `onboarding`.
- **Verification:** `User` does not implement `MustVerifyEmail` (import is
  commented). No verification routes. Client routes use `auth` + `client`,
  not `verified`.
- **Admin users:** `CreateUser` (form with password) does not set
  `email_verified_at`. `EditUser` changes the plan and marks `plan_source`
  manual.
- **Landing switch:** `talent.landing.beta_closed` (env `LANDING_BETA_CLOSED`,
  default true) only changes landing copy and CTA; `/register` always requires
  an invite.
- **Mail:** `.env.example` has `MAIL_MAILER=log`.

## 3. Open points

- None beyond the open decisions below.

## Pending changes

### Stage 1 — Beta (now)

- **P1.** Registration stays **invite-only**, single-use links (current
  behaviour, keep). A registered user gets the invitation's plan, or the
  default plan (Free). The admin changes the plan afterwards in `EditUser`
  so the person can test it (works today, keep).
- **P2.** `User` implements `MustVerifyEmail`. `RegisterController::store`
  no longer sets `email_verified_at`; it fires `Registered`, which sends the
  verification email.
- **P3.** Routes (Inertia page `auth/verify-email`, en/pt):
  - `GET /email/verify` → `verification.notice` (auth)
  - `GET /email/verify/{id}/{hash}` → `verification.verify` (auth, signed,
    `throttle:6,1`)
  - `POST /email/verification-notification` → `verification.send` (auth,
    `throttle:6,1`)
  After verification: `onboarding` if not completed, else `dashboard`.
- **P4.** Every client route — pages, the `internal.*` group and the
  `integrations.*` OAuth routes — requires `verified` in addition to its
  current middleware. Pages redirect to the notice; `internal.*` JSON
  requests get 403 with a message the client UI shows. Logout stays
  reachable.
- **P5.** Verify page: shows the address, resend button with a visible
  cooldown, and a "wrong address?" path (logout + contact). **O1.**
- **P6.** Verification email: localized to the user's locale (en/pt), product
  name, link expiry from `auth.verification.expire` (default 60 min).
- **P7.** Admin-created users (`CreateUser`) are created verified
  (`email_verified_at = now()`). The admin panel itself does not require
  verification.
- **P8.** `EditUser`: show verified status; actions "Resend verification
  email" and "Mark as verified".
- **P9.** Existing users with `email_verified_at` null: read-only by default
  command `users:mark-verified {--force}` (dry run lists them; `--force`
  stamps `now()`). No migration changes existing rows.
- **P10.** Production needs a real mailer; `production-hardening` P13 flags
  `log`/`array`.

### Stage 2 — Open registration (public launch, not for the beta)

- **P11.** One switch: `talent.registration.mode` (env `REGISTRATION_MODE`,
  `invite` | `open`, default `invite`). The landing `betaClosed` prop derives
  from it. **O2:** remove `LANDING_BETA_CLOSED` or keep it as a fallback.
- **P12.** `invite` mode = Stage 1 behaviour. `open` mode: `/register` works
  without an invite; when a usable invite is present it still applies (email
  lock, plan) and is consumed.
- **P13.** Without an invite the plan is always the default plan. The plan is
  never read from the request or URL. Paid plans only through the existing
  Stripe Checkout (`BillingController::checkout`, `SyncPlanFromStripe`).
- **P14.** Free must actually be free in open mode: the price config for Free
  is 0 in every region and no Stripe price is configured for it. **O4.**
- **P15.** Abuse protection in open mode:
  - captcha on the form (Cloudflare Turnstile, verified server-side over
    HTTP; adding a package needs the owner's approval);
  - registrations limited per IP per day (in addition to `throttle:10,1`);
  - disposable-email domains blocked. **O5.**
- **P16.** No account enumeration in open mode: an email that already exists
  gets the same screen as a new signup ("check your inbox") and the existing
  owner receives an email saying someone tried to register with it. This
  means open-mode signup does not log the user in before verification; the
  signed verification link logs them in. Design in `/plan-feature`.

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

## Open decisions

- **O1.** The invite is consumed at registration (today). If the person types a
  wrong address they cannot verify and need a new link. Options: keep (admin
  fixes the email in `EditUser` + resend, P8) — recommended; or consume the
  invite only after verification.
- **O2.** `LANDING_BETA_CLOSED`: remove or keep as fallback.
- **O3.** Should unverified users be able to see onboarding (read-only) or
  nothing at all? Recommended: nothing but the verify page.
- **O4.** Is Free free? `PRICE_FREE_*` defaults are non-zero (BRL 2500, EUR
  500, USD 500 in minor units) and `STRIPE_PRICE_FREE_*` exist.
- **O5.** Disposable-domain list source (static list in config vs package).
