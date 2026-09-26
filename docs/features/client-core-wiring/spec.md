# client-core-wiring — real auth, invite-only sign-up, account, dashboard, jobs, applications, realtime

> **Order:** 3 of 10. **Depends on:** `client-app-foundation` and
> `client-app-screens` DONE. **Kind:** backend + wiring. This spec makes the
> read side of the client app real and adds closed, invite-only sign-up.
> Sending modes, profiles and preferences semantics come in specs 4–6.
>
> **How to run:** `/plan-spec docs/features/client-core-wiring/spec.md`, then
> `/execute-phases`. New dependency in B.2 (approve in the phase message).

## Part 0 — Context and decisions (owner, final)

1. **Contracts are binding.** Every endpoint returns exactly the TypeScript
   shapes of `client-app-screens` B.2 (camelCase JSON). Use Laravel API
   Resources (`App\Http\Resources\Client\*`) to map models to contracts.
2. **Closed sign-up.** The site is public, but only invited people can
   register. The admin generates a **unique, single-use** sign-up link per
   person in Filament. A used, revoked, expired or unknown link sends the
   visitor to "Sign-ups are closed". This is an access gate, **not** a
   coupon. Plans are assigned by the admin (no billing yet).
3. **Transactional email: Resend** (password reset now; other mails later).
   Never the client's Gmail.
4. **Job language detection** moves here (needed by every contract):
   the existing one-time AI profile extraction also returns the job's
   language. AI stays limited to this single extraction.
5. **Plans become data here** (catalog + `users.plan_key`) so the account
   status is real. Sending _modes_ are enforced in `plans-and-sending-modes`.
6. **The client must never receive** job URLs, recipient email addresses or
   company domains in any response (`client-app-screens` Part 0).
7. Migrations: edit the existing `create_*` migrations (development mode,
   owner runs `migrate:fresh --seed`). No `alter` migrations.
8. `CLAUDE.md` hard rules apply. Filament `/app` keeps working until spec 6.

## Part B — Product spec

### B.1 Files to read first

`client-app-screens/spec.md` B.2–B.3 (contracts and endpoint map) and B.7
(realtime), `resources/js/types/contracts.ts`, `resources/js/data/*`,
`app/Models/{User,Application,JobPosting,JobPostingProfile,Company,CollectionRun}.php`,
`app/Outreach/**`, `app/Ai/Agents/ExtractJobPostingProfile.php`,
`app/Ai/Jobs/ExtractJobPostingProfileJob.php`,
`app/Console/Commands/ExtractPostingProfiles.php`,
`app/Actions/Collection/FinalizeCollectionRun.php`,
`app/Http/Controllers/ConnectedIntegrationOAuthController.php`,
`app/Services/ConnectedIntegrationTokenManager.php`,
`app/Filament/Resources/Users/**`, `routes/{web,channels}.php`,
`bootstrap/app.php`, `config/talent.php`, `.env.example`, the three
`create_*` migrations for users, job_posting_profiles and applications.

### B.2 Dependency

`resend/resend-php` (composer) — required by Laravel's built-in `resend`
mail transport. Verify the transport exists in the installed Laravel
version (`config/mail.php` / `MailManager`). `.env.example` (append only):
`MAIL_MAILER=resend`, `RESEND_API_KEY=`, `MAIL_FROM_ADDRESS`,
`MAIL_FROM_NAME="${BRAND_NAME}"`. Local development may keep `MAIL_MAILER=log`.

### B.3 Data model (edit create migrations)

**users** (`0001_01_01_000000_create_users_table.php`), add:
`country` string(2) nullable; `region` string(8) default `'row'`;
`plan_key` string(32) default `'free'`; (`locale`, `timezone` already
added by foundation). Casts: `region` → `App\Enums\Region`, `plan_key` →
`App\Enums\PlanKey`. Fillable + phpdoc.

**invitations** (new create migration):

| Column     | Type                                                                            |
| ---------- | ------------------------------------------------------------------------------- |
| id         | bigint                                                                          |
| token_hash | string(64) unique (sha256 of the raw token)                                     |
| email      | string nullable (when set, registration email must match, case-insensitive)     |
| note       | string(200) nullable (admin label, e.g. "Wife")                                 |
| plan_key   | string(32) nullable (plan given on registration; default from config when null) |
| created_by | foreignId users, restrictOnDelete                                               |
| used_by    | foreignId users nullable, nullOnDelete                                          |
| used_at    | timestamp nullable                                                              |
| expires_at | timestamp nullable                                                              |
| revoked_at | timestamp nullable                                                              |
| timestamps |                                                                                 |

Raw token: 40 random URL-safe chars (`Str::random(40)`), shown **once** in
the admin after creation. Link: `route('register', ['invite' => $token])`.

**job_posting_profiles**: add `language` string(8) nullable, indexed
(`en`/`pt`/`es`/`other`; null = not extracted).

**applications**: add `language` string(8) nullable (the language used to
send, filled from spec 5; until then the job's language). Stage columns
come in spec 6.

**notifications**: the existing `notifications` table (Laravel database
notifications) is reused.

### B.4 Config

`config/talent.php` additions:

```php
'plans' => [
    'default' => env('PLAN_DEFAULT', 'free'),
    'catalog' => [
        'free'    => ['name' => 'Free',    'mode' => 'auto',   'daily_limit' => (int) env('PLAN_FREE_DAILY_LIMIT', 25)],
        'starter' => ['name' => 'Starter', 'mode' => 'select', 'daily_limit' => (int) env('PLAN_STARTER_DAILY_LIMIT', 50)],
        'pro'     => ['name' => 'Pro',     'mode' => 'review', 'daily_limit' => (int) env('PLAN_PRO_DAILY_LIMIT', 150)],
    ],
],
'regions' => [
    'br'  => ['currency' => 'BRL', 'countries' => ['BR']],
    'eu'  => ['currency' => 'EUR', 'countries' => [/* EU-27 + IS, LI, NO, CH, GB */]],
    'row' => ['currency' => 'USD', 'countries' => []], // default for everything else
],
'invitations' => [
    'default_expiry_days' => (int) env('INVITE_EXPIRY_DAYS', 14),
],
```

Prices are added by `regional-pricing-and-billing`. `App\Plans\PlanCatalog`
(singleton) exposes `for(User): Plan` (readonly DTO: key, name, mode,
dailyLimit) and `all(): list<Plan>`; unknown keys fall back to `default`.
`App\Support\RegionResolver::fromCountry(?string): Region`.

**Replace `OutreachLimits::DAILY_SEND_LIMIT`** everywhere with
`PlanCatalog::for($user)->dailyLimit` (`CanSendApplications`, Filament
`/app` Jobs and Preferences pages). Keep `MAX_POSTING_AGE_DAYS` and
`RECIPIENT_PRIORITY`.

### B.5 Auth: invites, registration, password reset

- **Admin (Filament `/admin`)**: `InvitationResource` — list (note, email,
  plan, status badge: Unused / Used by <name> on <date> / Expired /
  Revoked, created by, created at), create action (note, optional email,
  optional plan, expiry days default from config; on save shows the full
  link in a copyable field with the text "This link is shown only once"),
  revoke action (sets `revoked_at`, confirm). Realtime refresh with the
  existing driver (`Table::socket()`), never poll. Admin `UserResource`
  gains `plan_key` (select), `country`, `region` (read-only, derived),
  `locale`.
- **Register** (`GET /register?invite=…`, guest): look up by
  `hash('sha256', $token)`; invalid (missing, used, revoked, expired) →
  redirect to `register.closed`. Valid → page `auth/register` with prop
  `invite: { email: string | null }` (never the token hash).
- **Store** (`POST /register`, guest, `throttle:10,1`): `RegisterRequest`
  (name 2–80, email unique + must equal invite email when set, password
  min 10 + confirmed, invite token, timezone valid IANA). In one DB
  transaction: `lockForUpdate` the invitation, re-check validity, create
  the user (non-admin, active, `email_verified_at = now()`, `plan_key` =
  invite plan or default, `locale` = current locale, `timezone`), set
  `used_by`/`used_at`. Log in, regenerate session, redirect `onboarding`.
  Concurrent use of the same link: only one succeeds, the other goes to
  Closed.
- **Closed** (`GET /register/closed`): static page from `client-app-screens`.
- **Password reset:** Laravel password broker. `POST /forgot-password`
  (`throttle:5,1`, always the same success message, no user enumeration),
  `GET /reset-password/{token}`, `POST /reset-password`. The reset
  notification mail is localized (user locale), branded (brand name, accent
  button) and sent through the default mailer.
- Auth pages keep Inertia `<Form>` (foundation B.9).

### B.6 Internal JSON API (read side)

Route group in `routes/web.php`: prefix `/internal`, name `internal.`,
middleware `auth`, `client`, `throttle:120,1`. Controllers in
`App\Http\Controllers\Client\Internal\`, resources in
`App\Http\Resources\Client\`. All responses are the contracts. Frontend:
replace the `data/endpoints.ts` placeholders with Wayfinder helpers for
every endpoint implemented here.

Per-hook fallback (small change to `data/source.ts`): a hook uses its real
function when `VITE_USE_FIXTURES !== 'true'` **and** the real function
exists; hooks whose endpoint is not implemented yet keep using fixtures.
After this spec the owner runs with `VITE_USE_FIXTURES=false`.

Implement:

- `GET /internal/account/status` → `AccountStatus`:
  plan from `PlanCatalog`; quota `usedToday` = `applications()->countedToday()`,
  `limit`, `remaining`, `resetsAt` = start of next day (app timezone);
  gmail from `gmailIntegration`; `sending` = `{ paused: false,
autoPausedReason: null }` until spec 6; onboarding steps: `basics` =
  country and timezone set, `gmail` = connected, `profile` = the legacy
  check `CanSendApplications` uses for CV + template (spec 5 switches it to
  profiles), `preferences` = a `job_preferences` row exists; `complete` =
  all four; `profiles.activeLanguages` = `['en','pt','es']` filter of
  languages that have the legacy CV (spec 5 replaces); `region`, `country`,
  `timezone`; `unreadNotifications`.
- `GET /internal/dashboard?period=today|week|month` → `DashboardData`
  (period boundaries in the user's timezone, fallback app timezone):
  `kpis.collected` = count of job postings in the client's pool
  (`MatchingJobPostings::forUser`) with `first_seen_at` in the period vs the
  previous equal period; `kpis.sent` = applications with `status = sent` and
  `sent_at` in the period vs previous; `totalSent`, `firstSentAt`; `hero`
  (sentToday, failedToday, queued, nextSendAt = min `scheduled_for` of
  queued, lastDays = 5 previous days of sent counts); `matches` (pool
  newest first, 4 items, total, newToday); `activity` (5 newest
  applications by `updated_at`).
- `GET /internal/dashboard/chart?range=14d|30d` → `ChartData`.
- `GET /internal/jobs` → `JobsPage` with filters (`q` ilike on title/company
  name, `language`, `seniority[]`, `remote`, `today`, `stack[]`), cursor
  pagination (20 per page, order `first_seen_at desc, id desc`), one row
  per company in the result (the newest posting per company, matching the
  existing one-application-per-company rule). `summary.lockedByLanguage` =
  `[]` until spec 5. `GET /internal/jobs/{id}` → `JobDetail` (404 if not in
  the client's pool). `summary` text passes `ClientSafeText::redact`.
- `GET /internal/applications` (filters, cursor) and
  `GET /internal/applications/{id}` (policy: owner only) →
  `ApplicationItem` / `ApplicationDetail`. `stage`/`subStep` null and
  `timeline` derived from `queued_at`/`sent_at` until spec 6. Subject and
  body pass through a client-safe filter that replaces the rendered job
  URL with the token `{{ job_url }}`. `lastError` is mapped to a
  translated, client-safe sentence by `App\Outreach\Support\ClientErrorMessage`
  (map known `last_error` prefixes: Gmail not connected, CV missing,
  recipient not verified, company no longer verified, connection error →
  "We could not confirm delivery", default "Sending failed"). Never return
  raw exception text.
- `GET /internal/notifications`, `POST /internal/notifications/read-all`.
- `GET|PUT /internal/account`, `PUT /internal/account/password` (current
  password required), `DELETE /internal/account` (password; deletes user,
  applications cascade per existing FKs; CV files under `cvs/{id}/` removed;
  Gmail tokens revoked best-effort then deleted; logs out),
  `GET /internal/account/export` (JSON download: account, preferences,
  applications without recipient addresses).
- `PUT /internal/onboarding/basics` → saves country (+ derived region),
  locale, timezone → returns `AccountStatus`.
- Resources never include: `url`, `apply_url`, `company_website`, `domain`,
  `recipient_email`, `contact` data, `raw`, `description_html`.

**Initial props:** the Inertia routes `dashboard`, `jobs`, `applications`
become controller actions passing `dashboard` + `chart`, `jobs` (first
page for the URL filters), `applications` (first page) as props, used as
`initialData` by the hooks. Other pages stay prop-less.

### B.7 Job language detection

- `ExtractJobPostingProfile` schema gains `language` (enum `en`, `pt`, `es`,
  `other`, required; "language the posting is written in"). Bump
  `CACHE_SCHEMA_VERSION` to `posting-profile-v2`. `ExtractJobPostingProfileJob`
  persists it (unknown values → `other`).
- `ExtractPostingProfiles` command: add `--missing-language` to re-extract
  profiles with `language IS NULL`. After `migrate:fresh` all profiles are
  new anyway; document the command in the phase report.
- Pool: jobs with `language` null or `other` never appear to clients (they
  cannot be matched to an application profile). Add this to
  `MatchingJobPostings::forUser` now.

### B.8 Realtime (server side)

- Private channel `App.Models.User.{id}` (exists in `routes/channels.php`).
  Confirm `/broadcasting/auth` is registered (`withRouting(channels: ...)`
  or `withBroadcasting`), with `web` + `auth`.
- Events (`ShouldBroadcastNow`, `broadcastAs` exactly the contract names,
  `broadcastWith` = contract payload built with the same Resources):
    - `ApplicationProgressed` → `application.progressed`; dispatched from
      `Application::booted` `saved` hook (in addition to the existing public
      admin broadcast), wrapped in the same best-effort try/catch as
      `BroadcastsRealtime`.
    - `AccountStatusUpdated` → `account.updated`; dispatched after an
      application changes quota (status saved) and when the Gmail integration
      status changes (`ConnectedIntegration` saved), debounced to at most once
      per second per user (cache lock).
    - `NotificationCreated` → `notification.created`.
    - `JobsCollected` on the **public** channel `jobs` → `jobs.collected`
      `{ collectionRunId, newJobs }`, dispatched by `FinalizeCollectionRun`
      when the run ends with `jobs_new > 0`. Public payload: ids and counts only.
- `App\Notifications\Client\*` base: stores a database notification with
  `type` from the contract and `data`, then dispatches `NotificationCreated`.
  Implement now: `GmailReauthorizationRequired` (sent from
  `ConnectedIntegrationTokenManager::requireReauthorization`). The other
  types are created by spec 6.
- Frontend: `useUserChannel` switches from the dev emitter to Echo when not
  in fixtures mode; subscribe to the public `jobs` channel too.

### B.9 Gmail connect from the new UI

`ConnectedIntegrationOAuthController::returnUrl()` currently returns the
Filament `/app` Preferences URL. Change it: `connect`/`reconnect` accept
`?return=account|onboarding` (whitelist, default `account`), store it in the
session, and the callback redirects to that route with a flash
(`gmail.connected` / `gmail.failed`). The legacy Filament page link keeps
working (it will land on the new Account page, acceptable until spec 6).
Disconnect from the new UI uses the existing DELETE route via `useMutation`.

### B.10 Seeders

`UserSeeder` keeps its rules and sets `country`, `region`, `locale`,
`timezone`, `plan_key` (client: `starter`) for the seeded users.
`InvitationSeeder` is **not** created (invites are made in the admin).

## Acceptance criteria

- **AC01** Admin creates an invitation, sees the link once, copies it; the
  list shows status changes live.
- **AC02** Opening the link as a guest shows the register form (email
  prefilled/read-only when bound); submitting creates an active non-admin
  user with the invite's plan, logs in and lands on Onboarding.
- **AC03** Reusing, revoking or expiring the link, or any unknown token,
  shows the Closed page; two simultaneous registrations with one link
  produce exactly one user.
- **AC04** Forgot/reset password works end to end with a localized, branded
  email (log mailer locally, Resend when configured); unknown emails get the
  same success message.
- **AC05** Every endpoint in B.6 returns the exact contract shape (checked
  against `contracts.ts` by comparing keys in a manual run) and only the
  current user's data.
- **AC06** No endpoint returns URLs, recipient emails, domains or raw
  errors (grep the Resources; manual check of responses).
- **AC07** `PlanCatalog` drives the daily limit everywhere;
  `OutreachLimits::DAILY_SEND_LIMIT` no longer exists.
- **AC08** New profiles get `language`; jobs with null/`other` language are
  excluded from every client pool.
- **AC09** With `VITE_USE_FIXTURES=false`: Dashboard, Jobs (list + detail),
  Applications (list + detail), Account, Notifications and Onboarding
  basics run on real data; screens not yet wired keep fixtures.
- **AC10** An application status change updates Dashboard, Applications and
  the account quota in another open window without reload; a finished
  collection run with new jobs refreshes Jobs and Dashboard.
- **AC11** Gmail connect/reconnect from Account and Onboarding returns to
  the page it started from with a flash message; a forced reauthorization
  creates a notification that appears live.
- **AC12** Delete account removes the user, their files and tokens; export
  downloads the JSON without recipient addresses.

## Verification

`composer lint:check`, `composer types:check`, `yarn check`,
`yarn types:check`, existing tests, manual walkthrough with two browser
windows (realtime), mail log for password reset.

## Out of scope

Sending modes, spacing, stages, pause/resume, review drafts (spec 6);
preferences semantics (spec 4); application profiles and the language pool
rule based on profiles (spec 5); billing; removing Filament `/app`.

## Owner decisions

None open. Dependency `resend/resend-php` pre-approved for this spec
(say so in the phase message).
