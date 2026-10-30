# Plan — Production Hardening

Spec: `docs/features/production-hardening/spec.md` · SHA-256 `4fbe2a9a1ae9d2621e90b3aea05c2ca8b56c8cbe42b919803cd055c444e7310d`
Run phases with `/exec-phase production-hardening <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — 2026-10-30

Implemented: 1 · Partial: 2 · Divergent: 0 · Missing: 18 · Unverifiable: 0
Not code in this feature: P9 (contract for later features, documented in Phase 6), P18, P19, P25 (owner tasks).

| Requirement | State | Evidence |
| ----------- | ----- | -------- |
| P1 Trusted proxies from env | Missing | `bootstrap/app.php` has no proxy configuration |
| P2 Rate limits | Partial | `routes/web.php:96,127-135` unthrottled; signup, opt-out and SMTP test rows belong to routes that don't exist yet; Filament limiter is on (`Login.php:70`) |
| P3 Webhook 404 when billing unavailable | Missing | `CashierServiceProvider::registerRoutes` always registers; `WebhookController` verifies only when the secret is set |
| P4 Webhook secret in production check | Missing | No command; `Billing::available()` already requires the secret |
| P5 Fillable guard on `User` | Missing | `User.php:57`; writes at `UsersTable.php:127,141`, `UserSeeder.php:47`, `CreateUser`, `EditUser`; test at `SendingGuardsHttpTest.php:22` |
| P6 Integration tokens internal only | Implemented | `CompleteConnectedIntegration`, `ConnectedIntegrationTokenManager`, `DisconnectConnectedIntegration`; no request data; tokens use the `encrypted` cast |
| P7 Shared public-network guard | Missing | No such class in `app/Support` |
| P8 Probe uses the guard | Missing | `SmtpProbe.php:52` connects to the raw host |
| P10 Reverb origins and rate limit | Missing | `config/reverb.php:85,92` |
| P11 Security headers | Missing | No middleware |
| P12 CSP report-only | Missing | No middleware; inline script at `landing-essentials.blade.php:12` |
| P13 Production check | Missing | No command |
| P14 Admin MFA | Missing | `AdminPanelProvider`; `pragmarx/google2fa` 9.1 is installed |
| P15 `.env.example` personal address | Missing | Lines 96 and 168 |
| P16 `.env.example` production notes | Missing | No notes |
| P17 Audits | Partial | Both run on 2026-10-30, not in CI (`.github/workflows/tests.yml`) |
| P20 Admins rejected at client login | Missing | `LoginRequest::authenticate` uses `Auth::attempt` for everyone |
| P21 Separate throttle counters | Missing | `ThrottleRequests.php:98`: key is `$prefix.signature`, independent of N |
| P22 Trusted hosts | Missing | `trustHosts` not enabled |
| P23 Admin path from config | Missing | `bootstrap/app.php:57` |
| P24 CSP report endpoint | Missing | No route |

### Audit results (P17, 2026-10-30)

- Composer: `livewire/livewire` 4.4.6 (GHSA-mhf7-xp6r-6qrc, fixed in 4.4.8; neither `app`, `resources` nor `vendor/filament` imports Livewire's `Authorize` attribute) and `symfony/mime` 8.1.7 (CVE-2026-45070, fixed in 8.1.10). Both medium.
- Yarn: critical/high advisories through `vite-plus` (tinypool, launch-editor, vite), `vite` (source-map-js, launch-editor), `concurrently` (shell-quote) and `@tanstack/react-query-devtools` (seroval, loaded only when `import.meta.env.DEV`). All are build or dev tooling; the production bundle was not inspected.

## Status board

| Phase | Title | Role | Depends on | Origin | Size | Status |
| ----- | ----- | ---- | ---------- | ------ | ---- | ------ |
| 1 | `.env.example` hygiene and production notes | laravel-backend | none | P15, P16 | S | DONE |
| 2 | Trusted proxies and trusted hosts | laravel-backend | 1 | P1, P22 | S | DONE |
| 3 | Rate limits and separate counters | laravel-backend | 2 | P2, P21 | S | DONE |
| 4 | Stripe routes gated by billing availability | laravel-backend | none | P3 | S | PENDING |
| 5 | Remove privileged fields from `User` fillable | laravel-backend | none | P5 | M | PENDING (needs test authorization, D1) |
| 6 | `PublicNetworkGuard` | laravel-backend | none | P7, P9 | S | PENDING |
| 7 | `SmtpProbe` uses the guard | laravel-backend | 6 | P8 | S | PENDING |
| 8 | Reverb origins and rate limit | laravel-backend | 1 | P10 | S | PENDING |
| 9 | Security headers | laravel-backend | 2 | P11 | S | PENDING |
| 10 | CSP report-only and report endpoint | laravel-backend | 9 | P12, P24 | M | PENDING |
| 11 | `app:production-check` and admin path | laravel-backend | 2, 4, 8 | P13, P4, P23 | M | PENDING |
| 12 | Reject admins at client login | laravel-backend | none | P20 | S | PENDING |
| 13 | Required admin MFA | filament-admin | 12 | P14 | M | PENDING |
| 14 | Audits in CI | laravel-backend | none | P17 | S | PENDING |
| 15 | Verification and report | qa-tester | 1-14 | spec x code gap | S | PENDING |

## Owner decisions

### D1 — Authorize the test edit for Phase 5

Blocks: Phase 5 · Options: A (recommended) include this sentence in the `/exec-phase` message: "may edit `tests/Feature/Outreach/SendingGuardsHttpTest.php`"; B drop P5 · Why: `CLAUDE.md` allows test edits only when asked in that message, and `new User([...])` at line 22 stops setting `status` and `plan_key` once they leave `#[Fillable]` · Answer: pending, given at execution.

### D2 — CI trigger branch

Blocks: nothing · Options: A (recommended) add `master` to `on.push.branches`; B leave it · Why: the workflow triggers on `main` but this repository uses `master`, so pushes never run CI and Phase 14's audits run on pull requests only · Answer: pending. Not changed by this plan.

### D3 — Dependency upgrades

Blocks: nothing · Options: A (recommended) ask for `composer update livewire/livewire symfony/mime` now and triage the Yarn tooling separately; B accept for now · Why: package changes need your explicit request (`CLAUDE.md`) · Answer: pending. Not changed by this plan.

### Resolved

Topology (spec D2), repository visibility (spec D3) and admin login (spec D4) are recorded in the spec.

## Global constraints (every phase)

- Git writes only as `/exec-phase` defines them; subagents never run them.
- Never run `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `DROP` or `TRUNCATE`. Seeders stay idempotent.
- No composer or npm package changes.
- No test changes, except the one named in D1 once authorized.
- Everything in the repository is English. Product locales are EN/PT only.
- No `->poll()`, `wire:poll` or `$pollingInterval`.
- Never read `.env`. Never edit `.env`; only `.env.example`.
- `.env.example` is append only, except the P15 lines in Phase 1.
- Read env values only in `config/*.php`, because `env()` returns nothing in app code or `bootstrap/app.php` under `config:cache`.
- Preserve the behaviours in spec section 2.1, especially `current_password` checks, `AdminGuard`, the invitation lock and the OAuth return whitelist.
- Do not touch `InteractsWithJobBoardApi` (P9).
- Do not rewrite git history (P15).

## Requirement coverage

| Requirement | Phases |
| ----------- | ------ |
| P1 | 2 |
| P2 | 3 (signup, opt-out and SMTP test rows bind those features) |
| P3 | 4 |
| P4 | 11 |
| P5 | 5 |
| P6 | none (implemented; re-checked in 15) |
| P7 | 6 |
| P8 | 7 |
| P9 | 6 (documented contract) |
| P10 | 8 |
| P11 | 9 |
| P12 | 10 |
| P13 | 11 |
| P14 | 13 |
| P15, P16 | 1 |
| P17 | 14, 15 |
| P18, P19, P25 | Owner tasks, listed in 15 |
| P20 | 12 |
| P21 | 3 |
| P22 | 2 |
| P23 | 11 |
| P24 | 10 |

## Phases

### Phase 1 — The personal address is gone from `.env.example` and production values are documented

Status: DONE
Evidence: `grep -c '^CONTACTS_SMTP_MAIL_FROM=' .env.example` = 1; `git grep` for the owner's address in `.env.example` finds nothing; `git diff --stat` shows only `.env.example`; production notes block appended.
Role: laravel-backend · Depends on: none · Covers: P15, P16 · Size: S
Origin: P15 "Replace the personal address in `.env.example` with a placeholder and remove the duplicated key"; P16 "`.env.example` production notes"

**Goal.** Stop publishing the owner's address in new commits and document production values.

**Gap -> desired.** `CONTACTS_SMTP_MAIL_FROM` appears at line 96 (empty) and line 168 (personal address) -> one key with a placeholder, plus a production notes block at the end of the file.

**Contract.**

- Line 96 becomes `CONTACTS_SMTP_MAIL_FROM=probe@example.com`.
- Delete the last two lines of the file (`# CONTACTS_SMTP_HELO=localhost` and the second `CONTACTS_SMTP_MAIL_FROM`).
- Append:

  ```
  # Production values (see `php artisan app:production-check`):
  # APP_ENV=production
  # APP_DEBUG=false
  # LOG_LEVEL=warning
  # SESSION_SECURE_COOKIE=true
  # MAIL_MAILER=resend (any real mailer, never log or array)
  ```

**Steps.**

1. Edit `.env.example` as above. Do not print the removed address in output or commit messages.

**Done when.**

- `grep -c '^CONTACTS_SMTP_MAIL_FROM=' .env.example` prints `1`.
- `git grep -n "$(git config user.email)" -- .env.example` finds nothing.
- `git diff --stat` shows only `.env.example`.

**Not in this phase.** History rewrite; `database/seeders/data/client` (spec D3).

### Phase 2 — The client IP and host are trustworthy

Status: DONE
Evidence: IP check passes (`TRUSTED_PROXIES` 10.0.0.1 / list / `*` -> `ip()` = 1.2.3.4; unset -> 10.0.0.1). `composer test` passes (pint, PHPStan, 33 tests) after fixing a baseline that was red at HEAD: pint on `config/filament.php` and `AdminPanelProvider.php`, PHPStan on `SourceSeeder.php`, and the `SendingDatabaseGuardsTest` dedup test rewritten for the per-job unique from 0519d83 (owner authorized fixing whatever the checks needed). `code-reviewer`: APPROVED.

Role: laravel-backend · Depends on: 1 · Covers: P1, P22 · Size: S
Origin: P1 "Configure trusted proxies from env"; P22 "Enable Laravel's trusted hosts"

**Goal.** Proxies are trusted only when configured, and forged `Host` headers are rejected.

**Gap -> desired.** No configuration -> `TRUSTED_PROXIES` env (empty on the planned single server, spec D2) and trusted hosts on.

**Contract.**

- `config/talent.php`: new top-level `'security' => ['trusted_proxies' => env('TRUSTED_PROXIES')]`.
- `App\Providers\AppServiceProvider::boot()`: when `config('talent.security.trusted_proxies')` is filled, call `Illuminate\Http\Middleware\TrustProxies::at($value)`. Read `TrustProxies::setTrustedProxyIpAddresses` first and confirm it accepts `*` and a comma list as a string.
- Assumption: this lives in `boot()` instead of `$middleware->trustProxies()` as P1 words it, because that closure runs before config loads and `env()` is empty under `config:cache`. Forwarded headers stay on the framework default.
- `bootstrap/app.php`, inside `withMiddleware`: `$middleware->trustHosts();`. The default allows the `APP_URL` host and its subdomains. Read `TrustHosts` first and confirm it skips local and unit tests.
- Append to `.env.example`:

  ```
  # Comma list of proxy IPs/CIDRs, or * only when the app is reachable exclusively through
  # the proxy. Leave empty on a server reached directly. nginx with php-fpm is not a proxy;
  # nginx with proxy_pass is (use 127.0.0.1).
  TRUSTED_PROXIES=
  ```

**Steps.**

1. Add the config key, provider call, middleware line and env note.
2. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- `TRUSTED_PROXIES=10.0.0.1 php artisan tinker --execute="..."` handling a request from `10.0.0.1` with `X-Forwarded-For: 1.2.3.4` shows `$request->ip()` = `1.2.3.4`; without the env it shows `10.0.0.1`.
- `composer test` passes.

**Not in this phase.** Rate limits; headers.

### Phase 3 — Every sensitive route has its own rate limit

Status: DONE
Evidence: `route:list -v` shows the prefixed throttles on internal, login, register, forgot-password, reset-password, oauth-callback, the three integration routes (shared `integrations` counter) and `locale` (named limiter, 30/min per IP); diff checked line by line against the contract; `composer test` passes (pint, PHPStan, 33 tests). Orchestrator-checked, no separate `code-reviewer` pass (mechanical change).
Role: laravel-backend · Depends on: 2 · Covers: P2, P21 · Size: S
Origin: P2 "Rate limits after P1"; P21 "Every `throttle:N,1` route … gets its own prefix"

**Goal.** Add the missing limits and stop routes sharing a counter.

**Gap -> desired.** `ThrottleRequests` builds its key from prefix plus user or IP, so unprefixed routes share one counter -> each has a prefix, with limits unchanged.

**Contract.** In `routes/web.php`:

| Route | Today | Target |
| ----- | ----- | ------ |
| `internal` group | `throttle:120,1` | `throttle:120,1,internal` |
| `POST /login` | `throttle:6,1` | `throttle:6,1,login` |
| `POST /register` | `throttle:10,1` | `throttle:10,1,register` |
| `POST /forgot-password` | `throttle:5,1` | `throttle:5,1,forgot-password` |
| `POST /reset-password` | `throttle:5,1` | `throttle:5,1,reset-password` |
| `GET /integrations/oauth/callback` | `throttle:20,1` | `throttle:20,1,oauth-callback` |
| `connect`, `reconnect`, `DELETE {plugin}` | none | `throttle:10,1,integrations` (one shared counter) |
| `PUT /locale` | none | `throttle:locale` |

- `AppServiceProvider::boot()`: `RateLimiter::for('locale', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));`. A named limiter is required because P2 says per IP even for signed-in users.
- Existing prefixed limits (`billing`, `preferences-preview`, `profiles-preview`) and `LoginRequest`'s lockout stay as they are.

**Steps.**

1. Edit the routes and add the limiter.
2. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- `php artisan route:list -v --path=integrations` and `--path=locale` show the throttles.
- `composer test` passes.

**Not in this phase.** Email resend, opt-out and SMTP test limits (their features add them).

### Phase 4 — Stripe routes answer 404 unless billing is available

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P3 · Size: S
Origin: P3 "When `Billing::available()` is false, the Stripe webhook route rejects every request (404)"

**Goal.** A forged webhook cannot reach Cashier when billing is off or the secret is missing.

**Gap -> desired.** Cashier always registers `POST stripe/webhook`, and verifies signatures only with a secret -> routes exist but return 404 unless `Billing::available()`.

**Contract.**

- Middleware rather than conditional registration, because it stays correct under `route:cache`.
- `AppServiceProvider::register()`: `Cashier::ignoreRoutes();`.
- `app/Http/Middleware/EnsureBillingAvailable.php`: `abort_unless(Billing::available(), 404);` then `$next($request)`.
- `routes/cashier.php`, keeping Cashier's exact URLs and names and no `web` group:

  ```php
  Route::prefix(config('cashier.path', 'stripe'))->name('cashier.')->middleware(EnsureBillingAvailable::class)->group(function (): void {
      Route::get('payment/{id}', [PaymentController::class, 'show'])->name('payment');
      Route::post('webhook', [WebhookController::class, 'handleWebhook'])->name('webhook');
  });
  ```

- `bootstrap/app.php`: add `then:` to `withRouting` that requires `routes/cashier.php`.
- Assumption: `payment/{id}` is gated too, since it only works with Stripe credentials.
- Keep `validateCsrfTokens(except: ['stripe/*'])`.

**Steps.**

1. Add the files and wiring.
2. `vendor/bin/pint --dirty --format agent`; `yarn types:check` (Wayfinder output may change).

**Done when.**

- `php artisan route:list --path=stripe` shows `cashier.payment` and `cashier.webhook`.
- With billing off, an unsigned `POST /stripe/webhook` returns 404 and `WebhookReceived` is not dispatched.
- With billing fully configured, an unsigned POST returns 403 (signature rejected).
- `composer test` passes.

**Not in this phase.** The production check (Phase 11).

### Phase 5 — Privileged `User` fields cannot be mass assigned

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P5 · Size: M
Origin: P5 "Remove `is_admin`, `status`, `plan_key` and `plan_source` from `User`'s `#[Fillable]`"

**Precondition.** The `/exec-phase` message must authorize editing `tests/Feature/Outreach/SendingGuardsHttpTest.php` (D1). If it doesn't, stop and report.

**Goal.** A future `update($request->validated())` can't become privilege escalation.

**Gap -> desired.** Four privileged fields are fillable -> removed, with every writer using `forceFill` or direct assignment.

**Contract.**

- `app/Models/User.php:57`: `#[Fillable(['name', 'email', 'password', 'locale', 'timezone', 'country', 'region'])]`.
- `UsersTable.php:127,141`: `$record->forceFill(['status' => UserStatus::Blocked])->save();` and the `Active` equivalent.
- `CreateUser`: override `handleRecordCreation(array $data): Model` with `$user = new User; $user->forceFill($data)->save(); return $user;`. Keep `mutateFormDataBeforeCreate`.
- `EditUser::handleRecordUpdate`: replace the final `parent::` call with `$record->forceFill($data)->save(); return $record;`. Keep all `AdminGuard` logic.
- `UserSeeder.php:47`: `$user = User::query()->firstOrNew(['email' => $email]); $user->forceFill([...same keys...])->save();`. It must stay idempotent. Side effect: `email_verified_at`, which mass assignment silently drops today, starts being saved.
- `SendingGuardsHttpTest.php:22`: build with `(new User)->forceFill([...same keys...])`. No other test change.
- No change needed: `RegisterController`, `BillingController`, `SyncPlanFromStripe`, `UserFactory` (factories are unguarded).

**Steps.**

1. Search again for every `create`, `update`, `fill` and `new User(` on `User` in `app`, `database` and `tests`.
2. Make the edits.
3. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- `(new User(['is_admin' => true]))->is_admin` is not true in tinker.
- `php artisan db:seed` runs twice with no error.
- Admin block, unblock, create and edit (including a plan change) still work in the panel.
- `composer test` passes.

**Not in this phase.** `ConnectedIntegration` (P6 is already implemented).

### Phase 6 — A shared guard rejects non-public hosts

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P7, P9 · Size: S
Origin: P7 "One shared guard"; P9 "P7 is mandatory for: the client SMTP transport and any fetch of company websites"

**Goal.** One class decides whether a host may be contacted.

**Gap -> desired.** Nothing exists -> `App\Support\PublicNetworkGuard`.

**Contract.**

```php
class PublicNetworkGuard
{
    /** @return list<string> Every resolved address, IPv4 first; empty when unresolved or when ANY address is not public. */
    public function publicAddresses(string $host): array;

    public function isPublicAddress(string $ip): bool;
}
```

- Non-final, so later features can inject and fake it.
- Normalise: trim, remove a trailing dot and IPv6 brackets. An IP literal is checked directly.
- Resolve with `dns_get_record($host, DNS_A | DNS_AAAA)`, using the same error handling as `DnsLookup::lookup`, plus `gethostbynamel($host)` for hosts-file names. If `dns_get_record` returns false, return `[]`.
- Reject IPv4: `0.0.0.0/8`, `10.0.0.0/8`, `100.64.0.0/10`, `127.0.0.0/8`, `169.254.0.0/16`, `172.16.0.0/12`, `192.0.0.0/24`, `192.0.2.0/24`, `192.168.0.0/16`, `198.18.0.0/15`, `198.51.100.0/24`, `203.0.113.0/24`, `224.0.0.0/4`, `240.0.0.0/4`.
- Reject IPv6: `::/128`, `::1/128`, `::/96`, `64:ff9b::/96`, `100::/64`, `2001::/32`, `2001:db8::/32`, `2002::/16`, `fc00::/7`, `fe80::/10`, `fec0::/10`, `ff00::/8`.
- IPv4-mapped (`::ffff:a.b.c.d`): convert with `inet_pton` and check the embedded IPv4.
- Invalid input is not public. Use `Symfony\Component\HttpFoundation\IpUtils::checkIp` for ranges.
- Class docblock states: callers connect to a returned address, not the hostname, with the TLS peer name set to the original host, so DNS rebinding cannot swap the address. It is mandatory for the client SMTP transport (`email-providers`) and company website fetches (`contact-quality`). `InteractsWithJobBoardApi` is exempt.

**Steps.**

1. Add the class.
2. `vendor/bin/pint --dirty --format agent`; `composer types:check`.

**Done when.**

- In tinker, `publicAddresses` returns `[]` for `localhost`, `127.0.0.1`, `169.254.169.254`, `100.64.0.1`, `::1`, `::ffff:10.0.0.1`, `fe80::1` and a nonexistent domain.
- It returns addresses for `8.8.8.8`.
- `composer types:check` passes.

**Not in this phase.** Any caller.

### Phase 7 — The SMTP probe never connects to a non-public MX

Status: PENDING
Role: laravel-backend · Depends on: 6 · Covers: P8 · Size: S
Origin: P8 "`SmtpProbe` uses P7 before connecting; a non-public MX makes the probe inconclusive"

**Goal.** Close the SSRF path through job-posting domains.

**Gap -> desired.** `stream_socket_client("tcp://{$host}:25")` -> connects only to a validated address.

**Contract.**

- `SmtpProbe::__construct(private PublicNetworkGuard $guard)`. The container already builds it for `DiscoverCompanyContacts`.
- At the start of `probe()`, after `$codes` is built:

  ```php
  $addresses = $this->guard->publicAddresses($host);

  if ($addresses === []) {
      return new SmtpProbeResult(false, false, 'host is not public or did not resolve', $codes);
  }
  ```

- Connect to `$addresses[0]`, in brackets when it contains `:`. There is no TLS in this probe, so no peer name is needed.
- `connected=false` already yields `catchAllVerdict() === null`, so existing inconclusive, pending and cooldown rules apply. Do not change `DiscoverCompanyContacts`.

**Steps.**

1. Edit `app/Contacts/Support/SmtpProbe.php`.
2. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- `app(SmtpProbe::class)->probe('127.0.0.1', ['a@example.com'])` returns `connected=false` with the new failure text, without opening a socket.
- `composer test` passes.

**Not in this phase.** Other outbound callers.

### Phase 8 — Reverb only accepts the app's origin

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: P10 · Size: S
Origin: P10 "Reverb `allowed_origins` from env; enable Reverb rate limiting in production"

**Goal.** Other sites can't open sockets to the app's Reverb.

**Gap -> desired.** `allowed_origins => ['*']` -> env list defaulting to the `APP_URL` host.

**Contract.**

- `config/reverb.php:85`:

  ```php
  'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('REVERB_ALLOWED_ORIGINS', parse_url((string) env('APP_URL'), PHP_URL_HOST) ?: ''))))),
  ```

- Reverb compares each entry to the origin's host with `Str::is`, so entries are hostnames and wildcards work.
- Leave `rate_limiting` as it is; it already reads `REVERB_APP_RATE_LIMITING_ENABLED`.
- Append to `.env.example`:

  ```
  # Hostnames allowed to open Reverb sockets (comma list, wildcards allowed). Default: the APP_URL host.
  # REVERB_ALLOWED_ORIGINS=app.example.com
  # In production: REVERB_APP_RATE_LIMITING_ENABLED=true
  ```

**Steps.**

1. Edit the config and env example.

**Done when.**

- `php artisan tinker --execute="dump(config('reverb.apps.apps.0.allowed_origins'))"` shows the `APP_URL` host.
- Realtime still works in the panel and client app after restarting `composer dev`.
- `composer test` passes.

**Not in this phase.** The production check (Phase 11).

### Phase 9 — Every response carries security headers

Status: PENDING
Role: laravel-backend · Depends on: 2 · Covers: P11 · Size: S
Origin: P11 "Middleware adding, on web responses"

**Goal.** Baseline browser protections on the client app and panel.

**Gap -> desired.** No headers -> global middleware.

**Contract.**

- `app/Http/Middleware/AddSecurityHeaders.php`, registered with `$middleware->append(AddSecurityHeaders::class)` in `bootstrap/app.php`. It must be global because the Filament panel has its own middleware stack and does not use the `web` group.
- Headers:
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `X-Frame-Options: DENY`
  - `Permissions-Policy: camera=(), microphone=(), geolocation=()`
  - `Strict-Transport-Security: max-age=31536000`, only when `app()->isProduction() && $request->secure()`
- Assumption: no `includeSubDomains` or `preload`, since they are hard to undo and affect other subdomains.
- Skip list: `config/talent.php` `security.headers_skip` => `env('SECURITY_HEADERS_SKIP', '')`, a case-insensitive comma list of header names not to set. This covers P11's "skip any header the proxy already sets" if nginx sets some.
- Append to `.env.example`:

  ```
  # Comma list of security headers nginx already sets, so the app does not duplicate them.
  SECURITY_HEADERS_SKIP=
  ```

**Steps.**

1. Add the middleware, registration, config and env note.
2. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- `curl -I` on `/`, `/login`, `/admin/login` and an unknown URL shows the four headers.
- There is no HSTS header locally.
- With `SECURITY_HEADERS_SKIP=X-Frame-Options`, that header is absent.
- The panel still loads and works.
- `composer test` passes.

**Not in this phase.** CSP.

### Phase 10 — The client app reports CSP violations without blocking anything

Status: PENDING
Role: laravel-backend · Depends on: 9 · Covers: P12, P24 · Size: M
Origin: P12 "Content Security Policy starts as `Content-Security-Policy-Report-Only`"; P24 "Throttled `POST /csp-report`"

**Goal.** Measure a CSP on the client app before anyone enforces it.

**Gap -> desired.** No CSP -> report-only header on the client app and a logging endpoint.

**Contract.**

- `app/Http/Middleware/AddContentSecurityPolicy.php`, appended to the `web` group. Filament doesn't use that group, so the panel gets no CSP. This is the "do not break the admin" choice in P12.
- Skip the header when `Vite::isRunningHot()`. Otherwise call `Vite::useCspNonce()` and set `Content-Security-Policy-Report-Only` to:

  ```
  default-src 'self'; script-src 'self' 'nonce-{nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self' {ws}; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; report-uri /csp-report
  ```

- `{ws}` comes from `config('reverb.apps.apps.0.options')`: `wss://` when scheme is `https`, else `ws://`, followed by `host:port`.
- Stripe Checkout and Google OAuth are `window.location.assign` navigations, so no directive is needed for them.
- `resources/views/partials/landing-essentials.blade.php:12`: `<script nonce="{{ Vite::cspNonce() }}">…`.
- `app/Http/Controllers/CspReportController.php` (invokable):
  - Return 204 and ignore bodies over 8192 bytes or invalid JSON.
  - Read the `csp-report` object and keep only `document-uri`, `blocked-uri`, `violated-directive`, `effective-directive`, `source-file` and `line-number`.
  - Strip query strings and fragments from URIs, because reset tokens can appear in URLs. Truncate strings to 255 characters.
  - `Log::warning('CSP violation', $fields)`.
- Route in the `then:` callback of `bootstrap/app.php`, outside the `web` group so reports create no sessions and need no CSRF token: `Route::post('/csp-report', CspReportController::class)->middleware('throttle:30,1,csp-report')->name('csp.report');`.

**Steps.**

1. Add the middleware, controller, route and nonce.
2. `vendor/bin/pint --dirty --format agent`; `yarn build`, then load the app without the Vite hot file.

**Done when.**

- `curl -I /login` shows the report-only header with a different nonce per request; `/admin/login` has none.
- A sample `POST /csp-report` returns 204 and logs only the allowed fields with no query string.
- The 31st POST in a minute returns 429.
- `composer test` and `yarn check` pass.

**Not in this phase.** Enforcing CSP. That is a later owner decision after a clean report period.

### Phase 11 — A read-only command says whether production is safe to launch

Status: PENDING
Role: laravel-backend · Depends on: 2, 4, 8 · Covers: P13, P4, P23 · Size: M
Origin: P13 "Read-only command `app:production-check`"; P4 "billing enabled without `cashier.webhook.secret` is a failure"; P23 "detects the admin panel from `filament.admin_panel_path`"

**Goal.** One command lists every unsafe production setting, and following its advice doesn't break the panel.

**Gap -> desired.** Nothing exists -> `app/Console/Commands/ProductionCheck.php`, signature `app:production-check`. Exit 1 on any failure, 0 otherwise. It never prints a secret or config value, only the setting's name and the problem.

**Contract.** Read `config()` only.

Failures:

| Check | Fails when |
| ----- | ---------- |
| `APP_ENV` | `! app()->isProduction()` |
| `APP_DEBUG` | `config('app.debug')` is true |
| `APP_KEY` | `config('app.key')` is blank |
| `APP_URL` | `config('app.url')` doesn't start with `https://` |
| `SESSION_SECURE_COOKIE` | `config('session.secure') !== true` |
| `MAIL_MAILER` | `config('mail.default')` is `log` or `array` |
| `QUEUE_CONNECTION` | `config('queue.default')` is `sync` |
| Stripe keys (P4) | `config('talent.billing.enabled')` and any of `cashier.key`, `cashier.secret`, `cashier.webhook.secret` is blank |
| `REVERB_ALLOWED_ORIGINS` | `reverb.apps.apps.0.allowed_origins` contains `*` |
| `LOG_LEVEL` | `config('logging.channels.single.level') === 'debug'` |
| `OPENAI_API_KEY` | `config('ai.providers.openai.key')` is blank |
| Google OAuth | `services.google.client_id` or `services.google.client_secret` is blank |

Warnings (listed separately, exit code unaffected):

- `TRUSTED_PROXIES` is empty, worded "expected when the server is reached directly (no proxy)".
- Any `talent.plans.prices.free.{br,eu,row}` is not 0.
- `filament.admin_panel_path` is `admin`.

P23, in `bootstrap/app.php:57`:

```php
$adminPath = trim((string) config('filament.admin_panel_path'), '/');
// ...
|| $request->is($adminPath, "{$adminPath}/*")
```

**Steps.**

1. Add the command and make the bootstrap change.
2. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- `php artisan app:production-check` locally lists the failures and exits 1 (`echo $?`).
- Its output contains no key or secret value.
- With `ADMIN_PANEL_PATH=control`, a 404 under `/control/...` still renders Filament's error page, not the client one.
- `composer test` passes.

**Not in this phase.** Reverb rate-limit check (not in P13's list).

### Phase 12 — Admins can only sign in at the panel

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P20 · Size: S
Origin: P20 "The client `POST /login` rejects admin accounts"

**Goal.** Close the MFA bypass before MFA ships.

**Gap -> desired.** `Auth::attempt` logs anyone in, and an admin session then reaches `/admin` with no challenge (Filament only challenges on its own login page) -> admin credentials fail at `/login`.

**Contract.**

- `App\Http\Requests\Auth\LoginRequest::authenticate()`:

  ```php
  $authenticated = Auth::attemptWhen(
      $this->only('email', 'password'),
      fn (User $user): bool => ! $user->is_admin,
      $this->boolean('remember'),
  );
  ```

- Keep the failure branch as it is: `RateLimiter::hit`, then `auth.failed`. No new copy, so the response doesn't reveal the account is an admin, and the attempt counts toward the lockout.
- Don't change Filament's login, `EnsureActiveClient`, or admins' ability to open client pages once signed in at the panel.

**Steps.**

1. Edit `LoginRequest`.
2. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- A valid admin at `/login` gets `auth.failed` and has no session.
- A valid client signs in normally, and the panel login still works for admins.
- `composer test` passes.

**Not in this phase.** MFA itself.

### Phase 13 — Every admin must use TOTP

Status: PENDING
Role: filament-admin · Depends on: 12 · Covers: P14 · Size: M
Origin: P14 "Turn on Filament's built-in multi-factor authentication, required for every admin. Recovery codes enabled."

**Goal.** An admin password alone can't open the panel.

**Gap -> desired.** Password only -> required TOTP with recovery codes.

**Contract.**

- `database/migrations/0001_01_01_000000_create_users_table.php`: add `$table->text('app_authentication_secret')->nullable();` and `$table->text('app_authentication_recovery_codes')->nullable();`.
- Assumption: columns are folded into the create migration, following the owner's recorded create-only preference. Two `add_*` migrations from 2026-10-10 are an exception to it. If the owner prefers a forward migration, use one instead.
- `App\Models\User`:
  - implements `Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication` and `HasAppAuthenticationRecovery`;
  - uses `InteractsWithAppAuthentication` and `InteractsWithAppAuthenticationRecovery`, which already encrypt and hide both columns;
  - gets PHPDoc `@property` lines. Do not add them to `#[Fillable]`.
- `AdminPanelProvider`:

  ```php
  ->multiFactorAuthentication([
      AppAuthentication::make()->recoverable(),
  ], isRequired: true)
  ->profile(isSimple: false)
  ```

- Assumption: `->profile()` is added because the panel has no profile page, and that is where admins regenerate recovery codes.
- Assumption: no in-app reset for an admin who loses both the authenticator and the codes. The owner clears both columns on the server.
- Confirm that account export and API resources don't expose the columns.

**Steps.**

1. Make the edits, checking the API with Boost `search-docs`.
2. `vendor/bin/pint --dirty --format agent`.
3. Do not run `migrate:fresh`. Tell the owner to run `php artisan migrate:fresh --seed` locally, because the panel can't complete setup until the columns exist. This wipes local data.

**Done when.**

- `composer test` passes (the test database migrates on its own).
- After the owner's migration: an admin is forced to the set-up page, sees recovery codes, and the next login asks for a code.
- A recovery code works once.
- `/admin` can't be reached without completing set-up.

**Not in this phase.** MFA for clients.

### Phase 14 — CI runs the dependency audits

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P17 · Size: S
Origin: P17 "Add both to CI if CI exists"

**Goal.** New advisories show up without anyone remembering to look.

**Gap -> desired.** `.github/workflows/tests.yml` has one `ci` job -> an added `audit` job.

**Contract.**

- New job `audit`, `runs-on: ubuntu-latest`, reusing the pinned checkout, setup-php and setup-node actions and `persist-credentials: false` from `ci`.
- Steps: `composer audit --locked --no-interaction` and `yarn audit --groups dependencies devDependencies`.
- Job-level `continue-on-error: true`.
- Assumption: non-blocking, because advisories are open today and fixing them needs owner approval (D3). A blocking job would turn every PR red.
- Don't change the `on:` branches (D2) or the `ci` job.

**Steps.**

1. Edit the workflow.

**Done when.**

- The YAML parses, for example `python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/tests.yml'))"`.
- `git diff` shows only the new job.

**Not in this phase.** Package upgrades.

### Phase 15 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1-14 · Covers: all · Size: S
Origin: spec x code gap

**Goal.** Prove the whole feature holds, and hand the owner what remains.

**Contract.** Read-only: no code, test or package changes. Report failures instead of fixing them.

**Steps.**

1. Run `composer ci:check`.
2. Grep for forbidden patterns in the change set: `->poll(`, `wire:poll`, `$pollingInterval`, Spanish copy, and git writes in new scripts.
3. Grep `database/seeders` and `app` for mass assignment of `is_admin`, `status`, `plan_key` and `plan_source` on `User`. Re-check P6: no request data reaches `ConnectedIntegration` tokens.
4. Run `composer audit` and `yarn audit`, and report the current results (P17).
5. Run `php artisan app:production-check` and list its output.
6. Walk through P1-P25 requirement by requirement.

**Owner's manual checklist.**

- [ ] Make the repository private (P25). Anyone who already cloned it keeps a copy, and the address and CVs stay in history.
- [ ] Run `php artisan migrate:fresh --seed` locally, then set up TOTP and store the recovery codes.
- [ ] On the server, set the Phase 1 production values, plus `REVERB_APP_RATE_LIMITING_ENABLED=true` and a non-`admin` `ADMIN_PANEL_PATH`.
- [ ] If nginx uses `proxy_pass` instead of php-fpm, set `TRUSTED_PROXIES=127.0.0.1`. Without it, HTTPS and client IPs are wrong.
- [ ] Don't set the same security headers in nginx unless they are listed in `SECURITY_HEADERS_SKIP`.
- [ ] Make uptime monitors call `/up` with the real hostname, since trusted hosts rejects others.
- [ ] Run `php artisan app:production-check` on the server until it has no failures.
- [ ] Watch `CSP violation` log entries, then decide whether to enforce CSP (P12).
- [ ] Google OAuth verification for `gmail.send` (P18) and Microsoft publisher verification (P19).
- [ ] Decide D2 (CI branch) and D3 (dependency upgrades).

**Done when.**

- `composer ci:check` passes, or each failure is reported with its output.
- Every requirement has a pass, fail or owner-task result in the report.

**Not in this phase.** Fixes; each failure goes back to the owner.
