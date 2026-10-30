# Production Hardening

> Body: current behaviour as implemented by `/exec-phase production-hardening`
> (2026-10-30), checked against the code. `## Pending changes` holds what is
> still open: rate-limit rows that belong to other features and the owner's
> launch tasks.
>
> Order: run this feature **first**. Other specs depend on items here (the
> outbound-connection guard, `App\Support\PublicNetworkGuard`, is reused by
> `email-providers` and `contact-quality`).

## 1. What it is

Everything that must be true before talent-labs takes real users: client IP
resolution, rate limits, webhook integrity, privilege escalation guards,
outbound-connection safety, realtime, headers, admin access, secrets and a
production configuration check.

## 2. Current state

### 2.1 Already correct — do not change

- CV upload (`UploadCvRequest`): `max:5120`, `mimetypes:application/pdf` plus
  PDF magic-bytes check; stored on the private `local` disk under
  `cvs/{user_id}/{language}/` with a random 40-char name; old file deleted
  only if `ApplicationProfile::isOwnCvPath()`.
- Authorization through `Gate`/policies (`ApplicationPolicy`,
  `ApplicationProfilePolicy`, `JobPreferencePolicy`); profiles are always
  loaded through `$user->applicationProfiles()`.
- Password change (`UpdatePasswordRequest`) and account deletion
  (`DeleteAccountRequest`) require `current_password`.
- Password reset does not reveal whether an address exists
  (`ForgotPasswordController` ignores the broker status).
- Login: `throttle:6,1,login` on the route plus `LoginRequest` lockout after 5
  attempts keyed by `email|ip`; session regenerated on login.
- OAuth: state bound to the user (`OAuthConnectionStateManager`), PKCE,
  return target from a whitelist (`RETURN_ROUTES`), provider errors logged
  only when they match `[a-z0-9_]{1,64}`.
- Invitations: only the SHA-256 hash of the token is stored; single use with
  `lockForUpdate`.
- Filament admin: `canAccessPanel` requires `is_admin` and status Active;
  panel path from `ADMIN_PANEL_PATH`.
- Integration tokens (`ConnectedIntegration`) are set only by internal code
  (`CompleteConnectedIntegration`, `ConnectedIntegrationTokenManager`,
  `DisconnectConnectedIntegration`), never from request data, and use the
  `encrypted` cast.
- Email body HTML is `nl2br(e($text))` (`ApplicationTemplateRenderer::html`);
  the admin infolist shows `body` with an escaped `TextEntry`.
- Account export (`AccountExportController`) excludes recipients, contacts,
  provider ids and internal errors.
- Private broadcast channel `App.Models.User.{id}` checks the user id.

### 2.2 Hardening in place

#### Client IP, hosts and rate limits

- **Trusted proxies:** `config/talent.php` `security.trusted_proxies` reads
  `TRUSTED_PROXIES` (comma list of IPs/CIDRs, or `*`); `AppServiceProvider::boot()`
  calls `TrustProxies::at()` only when it is filled. Empty (the planned single
  server, D2) trusts no proxy. Documented in `.env.example`.
- **Trusted hosts:** `$middleware->trustHosts()` in `bootstrap/app.php` accepts
  the `APP_URL` host and its subdomains (skipped locally and in tests), so a
  forged `Host` header cannot poison password-reset links. Uptime monitors must
  call `/up` with the real hostname.
- **Rate limits:** every `throttle:N,1` route has its own prefix, so routes
  no longer share one counter: `internal` 120/min per user, `login` 6,
  `register` 10, `forgot-password` 5, `reset-password` 5, `oauth-callback` 20,
  `billing` 10, `preferences-preview` 60, `profiles-preview` 60. The three
  integration routes (`connect`, `reconnect`, `DELETE {plugin}`) share
  `integrations` at 10/min per user. `PUT /locale` uses the named limiter
  `locale`, 30/min per IP (`AppServiceProvider::boot()`). Filament's own login
  limiter stays on.

#### Billing webhooks

- `Cashier::ignoreRoutes()` is called in `AppServiceProvider::register()` and
  `routes/cashier.php` (required from the `then:` callback in
  `bootstrap/app.php`) registers Cashier's `payment/{id}` and `webhook`
  routes under the same URLs and names behind `EnsureBillingAvailable`, which
  answers 404 unless `Billing::available()`. This stays correct under
  `route:cache`. With billing available, an unsigned webhook is rejected by
  Cashier's signature check (403). CSRF stays disabled for `stripe/*`.

#### Privilege escalation

- `User`'s `#[Fillable]` is `name`, `email`, `password`, `locale`, `timezone`,
  `country`, `region`. `is_admin`, `status`, `plan_key` and `plan_source` are
  set only with `forceFill` or direct assignment: `UsersTable` block/unblock,
  Filament `CreateUser` / `EditUser`, `UserSeeder` (idempotent via
  `firstOrNew`; it now also saves `email_verified_at`), `RegisterController`
  and `SyncPlanFromStripe`.
- `LoginRequest::authenticate()` uses `Auth::attemptWhen` and rejects admin
  accounts with the normal `auth.failed` error and no session; the attempt
  counts toward the lockout. Admins sign in only at the panel login, so the
  MFA challenge cannot be bypassed.

#### Outbound connections (SSRF)

- `App\Support\PublicNetworkGuard` (`publicAddresses($host)`,
  `isPublicAddress($ip)`) resolves a host (A, AAAA and hosts-file names) and
  returns every address only if **all** are public; it rejects loopback,
  private, CGNAT, link-local (incl. `169.254.169.254`), multicast, reserved,
  documentation, IPv6 ULA/link-local and IPv4-mapped forms of those, and
  returns `[]` for unresolved or invalid hosts. Callers connect to a returned
  address, with the TLS peer name set to the original host, so DNS rebinding
  cannot swap the address. It is mandatory for the client SMTP transport
  (`email-providers`) and any fetch of company websites (`contact-quality`);
  `InteractsWithJobBoardApi` only hits configured source hosts and is exempt.
- `SmtpProbe` asks the guard first and connects only to the first returned
  address. A non-public or unresolved MX makes the probe inconclusive
  (`connected=false`, failure `host is not public or did not resolve`), so
  the existing pending and cooldown rules apply.

#### Realtime

- `config/reverb.php` `allowed_origins` comes from `REVERB_ALLOWED_ORIGINS`
  (comma list of hostnames, wildcards allowed), defaulting to the `APP_URL`
  host. Reverb rate limiting is off by default (`REVERB_APP_RATE_LIMITING_ENABLED`
  is false); `.env.example` documents setting it to `true` in production, and
  `app:production-check` does not verify it.

#### Headers

- `AddSecurityHeaders` is global middleware (the panel has its own stack) and
  sets `X-Content-Type-Options: nosniff`, `Referrer-Policy:
  strict-origin-when-cross-origin`, `X-Frame-Options: DENY`,
  `Permissions-Policy: camera=(), microphone=(), geolocation=()` and, only in
  production over HTTPS, `Strict-Transport-Security: max-age=31536000` (no
  `includeSubDomains` or `preload`). `SECURITY_HEADERS_SKIP` (comma list,
  case-insensitive) skips headers nginx already sets.
- `AddContentSecurityPolicy` (in the `web` group, so not on the Filament
  panel; skipped when Vite runs hot or when `Content-Security-Policy-Report-Only`
  is listed in `SECURITY_HEADERS_SKIP`) sends
  `Content-Security-Policy-Report-Only` with a per-request nonce
  (`Vite::useCspNonce()`; the inline script in `landing-essentials.blade.php`
  carries it), `connect-src` including the Reverb websocket host, and
  `report-uri /csp-report`. Enforcing it is a later owner decision after a
  clean report period.
- `POST /csp-report` (`CspReportController`, outside the `web` group: no
  session, no CSRF; `throttle:30,1,csp-report`) always answers 204, ignores
  bodies over 8192 bytes, invalid JSON or without a `csp-report` object, keeps only `document-uri`,
  `blocked-uri`, `violated-directive`, `effective-directive`, `source-file`
  and `line-number`, strips query strings and fragments, truncates strings to
  255 characters and logs `CSP violation` at warning level.

#### Production check

- `php artisan app:production-check` reads `config()` only, never prints a
  value, and exits 1 on any failure. Failures: `APP_ENV` not production,
  `APP_DEBUG` true, empty `APP_KEY`, `APP_URL` not https,
  `SESSION_SECURE_COOKIE` not true, mailer `log`/`array`, queue `sync`,
  billing enabled without `STRIPE_KEY`, `STRIPE_SECRET` or
  `STRIPE_WEBHOOK_SECRET`, Reverb origins containing `*`, debug log level on
  the default channel (or any channel of its stack), missing `OPENAI_API_KEY`,
  missing Google OAuth client id or secret. Warnings (exit code unaffected):
  empty `TRUSTED_PROXIES` ("expected when the server is reached directly"),
  `PRICE_FREE_*` not 0, `ADMIN_PANEL_PATH` = `admin`.
- The admin panel is detected from `filament.admin_panel_path` (not a
  hardcoded `admin`) when deciding between Filament's and the client's error
  pages, so a non-default path keeps Filament's error pages.

#### Admin access

- Filament app authentication (TOTP) is required for every admin with
  recovery codes (`AdminPanelProvider::multiFactorAuthentication(...,
  isRequired: true)` plus a full `profile()` page where admins regenerate
  codes). `User` implements `HasAppAuthentication` and
  `HasAppAuthenticationRecovery`; `app_authentication_secret` and
  `app_authentication_recovery_codes` live in the users create migration,
  are encrypted and hidden, and are not fillable. There is no in-app reset
  for an admin who loses both the authenticator and the codes: the owner
  clears both columns on the server.

#### Secrets, repository and dependencies

- `.env.example` has a placeholder for `CONTACTS_SMTP_MAIL_FROM` (single key)
  and a production notes block (`APP_ENV`, `APP_DEBUG=false`,
  `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`, a real `MAIL_MAILER`).
  Git history was not rewritten (D3).
- `.github/workflows/tests.yml` has a non-blocking `audit` job running
  `composer audit --locked` and `yarn audit`. On 2026-10-30 they reported
  `livewire/livewire` 4.4.6 (GHSA-mhf7-xp6r-6qrc) and `symfony/mime` 8.1.7
  (CVE-2026-45070), both medium, plus Yarn advisories in build and dev
  tooling (1 critical, 2 high). No package was changed; upgrades need the
  owner's request.

## 3. Open points

- None. Topology was answered in D2.

## Pending changes

### Rate limits that belong to other features

- **P2 (rest).** Add the rows whose routes do not exist yet when their
  feature ships: email verification resend (`signup`) 6/min, company opt-out
  form (`landing-and-legal`) 5/min per IP, SMTP connection test
  (`email-providers`) 5/min per user; `signup` P15 adds the per-day limit on
  `POST /register`.

### Launch blockers outside the code (owner tasks, listed so they are not forgotten)

- **P18.** Google OAuth app verification for the `gmail.send` scope (sensitive
  scope) before going past test users; privacy policy URL and domain
  verification are prerequisites (`landing-and-legal`).
- **P19.** Microsoft app registration publisher verification (multi-tenant),
  when `email-providers` Microsoft ships.
- **P25.** Make the GitHub repository private (D3). Git history is not rewritten.

## Decisions

- **D1.** (2026-10-30) Validate the app for production: rate limits plus
  anything that allows intrusion or data leaks. This spec is that list.
- **D2.** (2026-10-30, answers O1) Production is a single server with no proxy
  or CDN in front. `TRUSTED_PROXIES` stays empty unless that changes.
- **D3.** (2026-10-30, answers O2) The repository goes private (P25). The seed
  data in `database/seeders/data/client` stays as it is.
- **D4.** (2026-10-30) Admin accounts are rejected at the client login (P20).
