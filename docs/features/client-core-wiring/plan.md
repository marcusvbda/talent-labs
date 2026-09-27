# Plan — client-core-wiring (real auth, invite-only sign-up, account, dashboard, jobs, applications, realtime)

Source spec: `docs/features/client-core-wiring/spec.md` · SHA-256
`70aacd0b1e8f2ff07e65fbec23069844bcbac0273ffd2751d3aa2cb3fbe7cccc`
Product truth: the spec above (Part 0 decisions are final); the client
contracts are `resources/js/types/contracts.ts` +
`docs/features/client-app-screens/spec.md` B.2–B.3, B.7.
Run phases with
`/execute-phases docs/features/client-core-wiring/plan.md <phases>` — one or a
few per session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                                          | Role             | Depends on | Size | Status  |
| ----- | -------------------------------------------------------------- | ---------------- | ---------- | ---- | ------- |
| 1     | Plans + regions: dependency, config, enums, catalog            | laravel-backend  | none       | M    | PENDING |
| 2     | User columns, seeder, `PlanCatalog` replaces the constant      | laravel-backend  | 1          | M    | PENDING |
| 3     | Job language detection + pool rule + `applications.language`   | laravel-backend  | none       | M    | PENDING |
| 4     | `invitations` table, model, token helper                       | laravel-backend  | 1          | S    | PENDING |
| 5     | Admin: `InvitationResource` + `UserResource` fields            | filament-admin   | 2, 4       | M    | PENDING |
| 6     | Register + Closed backend (single-use invite)                  | laravel-backend  | 2, 4       | M    | PENDING |
| 7     | Password reset backend + branded localized mail                | laravel-backend  | 1          | M    | PENDING |
| 8     | Auth pages submit for real (register, forgot, reset)           | inertia-frontend | 6, 7       | M    | PENDING |
| 9     | `/internal` group + `AccountStatus` + onboarding basics        | laravel-backend  | 2, 3       | M    | PENDING |
| 10    | Jobs endpoints (list + detail)                                 | laravel-backend  | 3, 9       | M    | PENDING |
| 11    | Applications endpoints + client-safe errors and body           | laravel-backend  | 9          | M    | PENDING |
| 12    | Dashboard + chart endpoints                                    | laravel-backend  | 10, 11     | M    | PENDING |
| 13    | Notifications + account read/update/password                   | laravel-backend  | 9          | M    | PENDING |
| 14    | Account delete + data export                                   | laravel-backend  | 13         | M    | PENDING |
| 15    | Initial Inertia props for dashboard, jobs, applications        | laravel-backend  | 10, 11, 12 | M    | PENDING |
| 16    | Gmail connect returns to Account or Onboarding                 | laravel-backend  | none       | S    | PENDING |
| 17    | Realtime events and their dispatch points                      | laravel-backend  | 9, 11      | M    | PENDING |
| 18    | Client database notifications (`GmailReauthorizationRequired`) | laravel-backend  | 17         | S    | PENDING |
| 19    | Frontend: per-hook fixture gate                                | inertia-frontend | none       | M    | PENDING |
| 20    | Frontend: Wayfinder endpoints + public `jobs` channel          | inertia-frontend | 15, 17, 19 | M    | PENDING |
| 21    | Verification and report                                        | qa-tester        | 1–20       | S    | PENDING |

## Audit — 2026-09-27

| Check | Result |
| -------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Framework versions | Laravel 13.33.0, PHP 8.4.3, Filament ^5.0, Inertia ^3.0, React 19, Tailwind 4, Reverb ^1.0, Wayfinder ^0.1.14. PHPStan level 7. |
| `resend/resend-php` | **Not installed** (`vendor/resend` absent). Needed by the `resend` transport. |
| Laravel `resend` mail transport | Present — `MailManager` line 323 builds `Resend::client($config['key'] ?? config('services.resend.key'))`; `config/mail.php` has the `resend` mailer. |
| `.env` / `.env.example` mail keys | `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `RESEND_API_KEY` already present in both (commit `f0697a3`). `config/services.php` already maps `resend.key`. |
| `.env` keys still missing | `PLAN_DEFAULT`, `PLAN_FREE_DAILY_LIMIT`, `PLAN_STARTER_DAILY_LIMIT`, `PLAN_PRO_DAILY_LIMIT`, `INVITE_EXPIRY_DAYS` (append only). |
| `migrate:status` | 16 migrations, all Ran, batch 1. No `alter` migrations exist — the create-migration convention holds. |
| `users` table | Has `locale`, `timezone`. **Missing** `country`, `region`, `plan_key`. |
| `job_posting_profiles` | **Missing** `language`. Has `status`, `schema_version`, `normalized_title`, `seniority`, `stack` (jsonb + gin), `locations`, `is_remote`, `summary(300)`. |
| `applications` | **Missing** `language`. Has `recipient_email`, `subject`, `body`, `origin`, `status`, `attempts`, `last_error`, `queued_at`, `scheduled_for`, `sent_at`; unique `(user_id, company_id)`. |
| `invitations` table | **Does not exist.** |
| `notifications` table | Exists (uuid id, type, morphs, json data, read_at, timestamps) — reuse as the spec says. |
| `config/talent.php` | Has `brand`, `client.use_fixtures`, `locales`, `seed`, `outreach`, `contacts`, `collection`. **Missing** `plans`, `regions`, `invitations`. |
| `App\Enums` | 14 enums; **no** `PlanKey`, **no** `Region`. `ApplicationStatus::countedTowardsQuota()` exists. |
| `App\Plans\*`, `App\Support\RegionResolver` | **Do not exist.** |
| `OutreachLimits::DAILY_SEND_LIMIT` | Exists (25). Referenced in 4 places: `CanSendApplications` (×2), `Filament/App/Pages/Jobs.php` (×2), `Filament/App/Pages/Preferences.php` (×1). |
| `App\Http\Resources`, `App\Http\Controllers\Client`, `App\Events`, `App\Notifications` | **None of these directories exist.** Everything in B.6/B.8 is new code. |
| `routes/web.php` | Client pages are prop-less `Route::inertia(...)`. No `/internal` group. Guest routes exist as `Route::inertia` placeholders for register/closed/forgot/reset. |
| `routes/channels.php` + `/broadcasting/auth` | `App.Models.User.{id}` private channel exists; `withRouting(channels: ...)` is set in `bootstrap/app.php`, so `/broadcasting/auth` is registered with `web`+`auth`. |
| Realtime frontend | `useUserChannel` already picks Echo vs dev emitter by `VITE_USE_FIXTURES`; `useRealtimeCache` already maps all five events including `jobs.collected`. **Missing:** subscription to the public `jobs` channel. `laravel-echo` + `pusher-js` + `@laravel/echo-react` installed. |
| Frontend data layer | `endpoints.ts` holds typed placeholders for every `/internal/*` path; every hook already has a `real` function. `source.ts` is `fromSource({real, fixture})` with no per-hook gate. `define-query.ts` provides `initialDataFrom`. |
| `useApplicationCounts` | Hits `GET /internal/applications/counts` (`{all, in_progress, sent, attention}`) — **not listed in spec B.6**. See **D1**. |
| `useInviteCheck` | Exists, `real` rejects with 501. Spec B.5 replaces it with the `invite` prop on `GET /register`, so the hook becomes dead code. |
| Gmail connect links | `gmail-card.tsx` and `step-gmail.tsx` pass `?redirect=<url>`; B.9 requires `?return=account                                                                                                                                                                                                                                       | onboarding`. `ConnectedIntegrationOAuthController::returnUrl()`hardcodes the Filament`/app` Preferences URL. |
| `ConnectedIntegrationTokenManager::markReauthorizationRequired` | Already sends a Filament database notification pointing at `/app` Preferences. B.8 adds a `App\Notifications\Client\GmailReauthorizationRequired` on top. |
| `MatchingJobPostings::forUser` | Single source of truth for the pool (verified company, profile Done, target role family, smtp-verified priority contact, no prior application, preference filters). **Missing** the language rule. |
| Known `last_error` values | `Client account is not active.`, `Company is no longer verified for outreach.`, `Recipient is not a verified contact of this company.`, `Gmail is not connected.`, `CV file is missing.`, `Worker stopped mid-send.`, `Sending failed.`, and `class_basename($e).': '.Str::limit($e->getMessage(), 180)` for anything unexpected. |
| `ClientSafeText::redact` | Exists (emails → `[email]`, links/bare domains → `[link]`). No `{{ job_url }}` token replacement yet. |
| i18n | `lang/en.json` and `lang/pt.json` both 802 keys, already covering `auth.*`, `account.*`, `notifications.*` from spec 2. `lang/en/{auth,passwords,validation,pagination}.php` exist; **`lang/pt` only has `validation.php`**. |
| Tests | Only `tests/{Unit,Feature}/ExampleTest.php` + `Pest.php`. Nothing to regress; no new tests are written (CLAUDE.md). |
| Verification commands that exist | `composer lint:check`, `composer types:check`, `composer test`, `vendor/bin/pint --dirty --format agent`, `yarn run check`, `yarn run types:check`, `php artisan test`. No `yarn lint`, no `yarn test`. |
| **`yarn check` is a trap** | Yarn Classic shadows it with its own integrity check ("success Folder in sync") and never runs `vp check`. The spec's Verification section says `yarn check`; the command that actually runs the gate is **`yarn run check`** (and `yarn run types:check`). Every phase in this plan uses the `yarn run …` form. |
| Frontend formatting baseline | `yarn run check` already fails on four files before this plan starts — `resources/js/components/patterns/{page-header,top-bar}.tsx`, `resources/js/components/ui/menu.tsx`, `resources/js/data/fixtures/state.ts`. Pre-existing, unrelated to this spec, and **out of scope**: no phase fixes them. Phase 21 must report them separately so they are not read as a regression. `vp check --fix <paths>` fixes a single path if the owner wants them cleaned. |
| Uncommitted owner work (2026-09-27) | Besides the deleted `client-app-screens/plan.md`, the working tree has the owner's own in-progress edits to `lang/{en,pt}.json`, `resources/js/components/patterns/top-bar.tsx`, `resources/js/data/fixtures/dev-state.ts` and `resources/js/features/dashboard/live-sending-card.tsx`. Nothing in this plan was derived from them; phases touching `lang/*.json` (7, 8, 11, 16) must merge rather than overwrite. |
| `vp` formatter scope | `fmt` in `vite.config.ts` covers `docs/**/*.md` (print width 80, tab width 4) and ignores `resources/views/mail/*` — relevant to Phase 7's mail Blade view, which the formatter will leave alone. |
| Filament realtime convention | `->socket(channel: 'x', event: 'XUpdated')` used in 9 tables; `RealtimeEvent::dispatch` from model `booted()`; `BroadcastsRealtime` trait wraps it in try/catch. No `->poll()` anywhere. |
| Git working tree | `docs/features/client-app-screens/plan.md` is deleted but not committed. Git stays read-only — nothing in this plan touches it. |

## Owner decisions

### D1 — Is `GET /internal/applications/counts` in scope?

Blocks: nothing (Phase 11 implements option A by default) · Covers: AC09
Options:

- **A (recommended)** — implement it in Phase 11 as
  `GET /internal/applications/counts` → `{ all, in_progress, sent, attention }`
  (counts over the user's whole history, ignoring `language` and `q`).
  `useApplicationCounts` already calls this exact path and the Applications
  screen renders the numbers in its status tabs; leaving it on fixtures would
  show a real list next to invented tab counters once
  `VITE_USE_FIXTURES=false`. Cost: one controller method, no new files.
- **B** — leave it out of scope. Phase 19 then also gates
  `useApplicationCounts` to fixtures, and the owner accepts fake tab counters
  on the Applications screen until a later spec.

Why: spec B.6 lists `GET /internal/applications` and
`GET /internal/applications/{id}` but not `/counts`, while
`client-app-screens` shipped a hook for it. The spec's B.6 list is binding, so
the gap is a product call, not a silent addition. Phase 11 assumes **A**; say
so in the phase message if you want **B**.

## Global constraints (every phase)

- **Git is read-only.** No `add`, `commit`, `push`, `branch`, `checkout`,
  `stash`, `reset` or any other write, in the orchestrator or in a subagent.
- **Never destroy data.** No `migrate:fresh|refresh|reset|rollback`, no
  `db:wipe`, no `DROP`/`TRUNCATE`. The owner runs
  `php artisan migrate:fresh --seed` himself; a phase may run forward
  `php artisan migrate` only, and seeders stay idempotent.
- **Migrations:** edit the existing `create_*` migrations (Part 0.7). The only
  new migration in this plan is `create_invitations_table`. No `alter`
  migrations.
- **No new dependencies** beyond `resend/resend-php`, which the spec
  pre-approves for Phase 1 only. No npm/yarn package changes at all.
- **No tests** are written or modified. Running existing ones is fine.
- **English everywhere** — code, comments, docs, UI copy.
- **Realtime, not polling.** Filament surfaces use `Table::socket()` /
  `<x-filament-realtime-driver::listener>`; never `->poll()` or `wire:poll`.
- **The client never receives** `url`, `apply_url`, `company_website`,
  `domain`, `recipient_email`, contact data, `raw`, `description_html`, or raw
  exception text. Every client-facing payload goes through an
  `App\Http\Resources\Client\*` resource.
- **Contracts are binding.** Response shapes match `contracts.ts` exactly,
  camelCase JSON, no extra keys, no `data` envelope (set
  `JsonResource::withoutWrapping()` once, in Phase 9).
- **Only the current user's data.** Every `/internal` query is scoped to
  `$request->user()`; `{id}` lookups 404 (jobs) or go through a policy
  (applications) when the record is not theirs.
- **Scope:** sending modes, spacing, stages, pause/resume, review drafts,
  preferences semantics, application profiles, billing and removing Filament
  `/app` are all out of scope. Filament `/app` keeps working.
- **Docs:** this file and `spec.md` are the only docs touched; never edit
  `spec.md` to match the code.
- After every phase: `vendor/bin/pint --dirty --format agent`,
  `composer lint:check`, `composer types:check`, and for frontend changes
  `yarn run check` + `yarn run types:check`. Then `code-reviewer` on the phase diff.

## Acceptance-criteria coverage

| AC   | Phases                    |
| ---- | ------------------------- |
| AC01 | 4, 5                      |
| AC02 | 6, 8                      |
| AC03 | 6, 8                      |
| AC04 | 7, 8                      |
| AC05 | 9, 10, 11, 12, 13, 14, 21 |
| AC06 | 10, 11, 21                |
| AC07 | 1, 2                      |
| AC08 | 3                         |
| AC09 | 9–15, 19, 20, 21          |
| AC10 | 17, 20                    |
| AC11 | 16, 18                    |
| AC12 | 14                        |

## Phases

### Phase 1 — Plans and regions become data

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: AC07 · Size: M
Spec: Part 0.5, B.2, B.4

**Goal.** Install the Resend transport package, turn plans and regions into
config-backed data, and expose them through `PlanCatalog` and
`RegionResolver`.

**Contract.**

- **Dependency (pre-approved by the spec, this phase only):**
  `composer require resend/resend-php`. Verify afterwards that
  `config/mail.php` still lists the `resend` mailer and
  `config/services.php` maps `resend.key` to `RESEND_API_KEY` (both already
  do — do not re-add them). Local development keeps `MAIL_MAILER=log`; never
  overwrite existing `.env` values.
- **`.env.example` (append only, never reorder or overwrite):**
    ```
    PLAN_DEFAULT=free
    PLAN_FREE_DAILY_LIMIT=25
    PLAN_STARTER_DAILY_LIMIT=50
    PLAN_PRO_DAILY_LIMIT=150
    INVITE_EXPIRY_DAYS=14
    ```
    `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` and `RESEND_API_KEY`
    are already there — leave them alone.
- **`config/talent.php`** gains exactly the three blocks from spec B.4.
  `regions.eu.countries` is the EU-27 plus IS, LI, NO, CH, GB, written out in
  full:
  `['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','IS','LI','NO','CH','GB']`.
  `regions.br.countries` is `['BR']`; `regions.row.countries` is `[]`.
- **`App\Enums\PlanKey: string`** — `Free = 'free'`, `Starter = 'starter'`,
  `Pro = 'pro'`. Implements `Filament\Support\Contracts\HasLabel` with
  `getLabel()` returning the catalog name (`Free`, `Starter`, `Pro`), matching
  the style of `App\Enums\UserStatus`.
- **`App\Enums\Region: string`** — `Br = 'br'`, `Eu = 'eu'`, `Row = 'row'`.
  Implements `HasLabel` (`Brazil`, `Europe`, `Rest of world`) and exposes
  `public function currency(): string` reading
  `config("talent.regions.{$this->value}.currency")`.
- **`App\Plans\Plan`** — `final readonly class` with promoted constructor
  properties `PlanKey $key`, `string $name`, `string $mode`,
  `int $dailyLimit`. `$mode` is one of `auto|select|review` (a plain string
  here; `plans-and-sending-modes` turns it into an enum).
- **`App\Plans\PlanCatalog`** — `final class`, registered as a singleton in
  `AppServiceProvider::register()`. API exactly:
    - `public function for(User $user): Plan` — resolves `$user->plan_key`;
      an unknown or null key falls back to `config('talent.plans.default')`,
      and if that is unknown too, to the first catalog entry.
    - `public function get(string|PlanKey $key): Plan` — same fallback rule.
    - `public function all(): array` — `list<Plan>` in catalog order
      (free, starter, pro).
      `plan_key` does not exist on `users` yet, so `for()` must read it
      defensively this phase (`$user->getAttribute('plan_key')`); Phase 2 adds the
      column and the cast, after which `for()` can keep working unchanged.
- **`App\Support\RegionResolver`** — `final class` with
  `public static function fromCountry(?string $country): Region`: uppercases
  and trims the ISO-3166-1 alpha-2 code, returns the region whose
  `countries` list contains it, otherwise `Region::Row`. Null/blank →
  `Region::Row`.
- Prices are **not** added here (`regional-pricing-and-billing` owns them).

**Steps.**

1. `composer require resend/resend-php`, then confirm the transport is
   resolvable (`php artisan tinker` is not needed — reading
   `config/mail.php` + `config/services.php` is enough).
2. Append the five keys to `.env.example`.
3. Add `plans`, `regions`, `invitations` to `config/talent.php`.
4. Add `App\Enums\PlanKey` and `App\Enums\Region`.
5. Add `App\Plans\Plan` and `App\Plans\PlanCatalog`; bind the catalog as a
   singleton in `AppServiceProvider`.
6. Add `App\Support\RegionResolver`.

**Done when.**

- `composer.json` requires `resend/resend-php` and `vendor/resend` exists.
- `config('talent.plans.catalog.starter.daily_limit')` is `50` and
  `config('talent.invitations.default_expiry_days')` is `14`.
- `RegionResolver::fromCountry('pt')` → `Region::Eu`;
  `fromCountry('BR')` → `Region::Br`; `fromCountry(null)` → `Region::Row`.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** `users.plan_key` / `users.country` / `users.region`
(Phase 2), replacing `OutreachLimits::DAILY_SEND_LIMIT` (Phase 2), plan
prices and `GET /internal/plans` (out of scope).

---

### Phase 2 — User plan, country and region; the catalog drives the daily limit

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: AC07 · Size: M
Spec: B.3 (users), B.4 (replace constant), B.10

**Goal.** Give users a plan, country and region, and make
`PlanCatalog::for($user)->dailyLimit` the only daily limit in the codebase.

**Contract.**

- **`0001_01_01_000000_create_users_table.php`** (edit the create migration),
  after `timezone`:
    ```php
    $table->string('country', 2)->nullable();
    $table->string('region', 8)->default('row');
    $table->string('plan_key', 32)->default('free');
    ```
- **`App\Models\User`**: add `country`, `region`, `plan_key` to `#[Fillable]`;
  add casts `'region' => Region::class`, `'plan_key' => PlanKey::class`; add
  phpdoc `@property string|null $country`, `@property Region $region`,
  `@property PlanKey $plan_key`.
- **`App\Outreach\OutreachLimits`**: delete `DAILY_SEND_LIMIT` entirely (also
  drop the `// para testes` comment with it). Keep `MAX_POSTING_AGE_DAYS` and
  `RECIPIENT_PRIORITY` unchanged.
- **`App\Outreach\Actions\CanSendApplications::check()`**: resolve the limit
  with `app(PlanCatalog::class)->for($user)->dailyLimit` (constructor-inject
  `PlanCatalog` rather than using the container inline). The unmet message
  keeps its shape: `'Daily limit of '.$limit.' applications reached.'`.
- **`app/Filament/App/Pages/Jobs.php`** (lines ~65 and ~199) and
  **`app/Filament/App/Pages/Preferences.php`** (line ~161): replace the
  constant with the current user's plan limit. In `Preferences` the sentence
  becomes `'Emails are sent from your own Gmail account, up to '.$limit.' per day.'`.
- **`UserSeeder`**: keep the existing idempotent `updateOrCreate` and the
  production guard; add to the attribute array, per seeded user —
  admin: `country` `'BR'`, `region` `Region::Br`, `locale` `'en'`,
  `timezone` `'America/Sao_Paulo'`, `plan_key` `PlanKey::Free`;
  client: `country` `'BR'`, `region` `Region::Br`, `locale` `'pt'`,
  `timezone` `'America/Sao_Paulo'`, `plan_key` `PlanKey::Starter` (spec
  B.10 fixes the client plan to `starter`). Derive `region` through
  `RegionResolver::fromCountry()` rather than hardcoding it.
- No `InvitationSeeder` is created (spec B.10).

**Steps.**

1. Edit the users create migration.
2. Update `User` (fillable, casts, phpdoc).
3. Remove `DAILY_SEND_LIMIT`; update `CanSendApplications` to inject
   `PlanCatalog`.
4. Update the two Filament `/app` pages.
5. Update `UserSeeder`.
6. Run `php artisan migrate` (forward only — the new columns land on the
   owner's next `migrate:fresh --seed`; if the columns are absent locally,
   report it instead of dropping anything).

**Done when.**

- `grep -rn "DAILY_SEND_LIMIT" app/ resources/` returns nothing.
- `php artisan db:seed --class=UserSeeder` runs twice with no duplicates and
  leaves the client user on `plan_key = starter`, `region = br`.
- Filament `/app` Jobs and Preferences still render and show the plan's limit.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** `AccountStatus` (Phase 9), plan gating of send modes
(out of scope), admin plan editing (Phase 5).

---

### Phase 3 — Job language detection and the language pool rule

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: AC08 · Size: M
Spec: B.3 (`job_posting_profiles`, `applications`), B.7

**Goal.** The one-time AI extraction also returns the posting's language;
postings whose language is unknown or not `en`/`pt` never reach a client.

**Contract.**

- **`2026_09_24_100000_create_job_posting_profiles_table.php`**: add
  `$table->string('language', 8)->nullable()->index();` after `is_remote`.
  Values: `en`, `pt`, `other`; `null` means not extracted.
- **`App\Models\JobPostingProfile`**: add `language` to `#[Fillable]` and
  `@property string|null $language` to the phpdoc. No cast (plain string).
- **`2026_09_24_100005_create_applications_table.php`**: add
  `$table->string('language', 8)->nullable();` after `origin`.
- **`App\Models\Application`**: add `language` to `#[Fillable]` and
  `@property string|null $language` to the phpdoc.
- **`App\Ai\Agents\ExtractJobPostingProfile`**:
    - `public const CACHE_SCHEMA_VERSION = 'posting-profile-v2';`
    - `public const LANGUAGES = ['en', 'pt', 'other'];`
    - `schema()` gains
      `'language' => $schema->string()->enum(self::LANGUAGES)->required(),`
    - `instructions()` gains one sentence, verbatim: `language: the language the
posting is written in — "en" for English, "pt" for Portuguese, "other" for
anything else (including Spanish).`
- **`App\Ai\Jobs\ExtractJobPostingProfileJob::persist()`**: write
  `'language' => in_array($data['language'] ?? null, ExtractJobPostingProfile::LANGUAGES, true) ? $data['language'] : 'other'`.
  The `failed()` path keeps writing only `status` + `schema_version`.
- **`App\Console\Commands\ExtractPostingProfiles`**: signature becomes
  `postings:extract-profiles {--limit= : Only queue this many postings} {--missing-language : Re-extract profiles whose language is null}`.
  With `--missing-language` the command selects postings whose profile exists
  and has `language IS NULL` (instead of the default `needingProfile` scope)
  and queues `ExtractJobPostingProfileJob` for each, honouring `--limit`.
  Keep the existing positive-integer validation for `--limit`.
- **`App\Outreach\Queries\MatchingJobPostings::forUser()`**: add
  `->whereIn('job_posting_profiles.language', ['en', 'pt'])` to the base pool,
  next to the `status = done` condition, and extend the class docblock with
  one sentence: postings with no language, or a language other than `en`/`pt`,
  are never in a client's pool because no application profile can match them.
- The contract type is `JobLanguage = 'en' | 'pt'` — `other` exists only in the
  database and is never serialised to a client.

**Steps.**

1. Edit the two create migrations.
2. Update `JobPostingProfile` and `Application` (fillable + phpdoc).
3. Update the agent (schema, instructions, `CACHE_SCHEMA_VERSION`,
   `LANGUAGES`).
4. Update the job's `persist()`.
5. Add `--missing-language` to the command.
6. Add the language condition to `MatchingJobPostings::forUser()`.
7. Run `php artisan migrate` (forward only).

**Done when.**

- `ExtractJobPostingProfile::CACHE_SCHEMA_VERSION === 'posting-profile-v2'`,
  so every existing `posting-profile-v1` profile is re-extracted on its next
  pass and the response cache misses.
- `php artisan postings:extract-profiles --missing-language --limit=1` queues
  at most one job and exits 0.
- `MatchingJobPostings::forUser($user)->toSql()` contains the
  `job_posting_profiles.language in (?, ?)` clause.
- `composer lint:check` and `composer types:check` pass.
- The phase report states, for the owner: after `migrate:fresh --seed` every
  profile is re-extracted anyway; on a kept database run
  `php artisan postings:extract-profiles --missing-language`.

**Not in this phase.** `lockedByLanguage` counts and the profile-based
language rule (spec 5), `applications.language` being filled on send
(spec 5 — it stays null and the resources fall back, see Phase 11).

---

### Phase 4 — `invitations`: table, model, token helper

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: AC01, AC03 · Size: S
Spec: B.3 (`invitations`), B.5

**Goal.** A single-use invitation record with a hashed token and a derived
status, plus the helper that mints and looks up tokens.

**Contract.**

- **New migration** `database/migrations/2026_09_27_100000_create_invitations_table.php`:
    ```php
    $table->id();
    $table->string('token_hash', 64)->unique();
    $table->string('email')->nullable();
    $table->string('note', 200)->nullable();
    $table->string('plan_key', 32)->nullable();
    $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
    $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('used_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('revoked_at')->nullable();
    $table->timestamps();
    ```
- **`App\Models\Invitation`** — `#[Fillable(['token_hash', 'email', 'note', 'plan_key', 'created_by', 'used_by', 'used_at', 'expires_at', 'revoked_at'])]`,
  casts `plan_key` → `PlanKey::class`, `used_at`/`expires_at`/`revoked_at` →
  `datetime`; full `@property` phpdoc; relations
  `creator(): BelongsTo<User, $this>` (`created_by`) and
  `usedBy(): BelongsTo<User, $this>` (`used_by`).
  Methods:
    - `public function isUsed(): bool` — `used_at !== null`
    - `public function isRevoked(): bool` — `revoked_at !== null`
    - `public function isExpired(): bool` — `expires_at !== null && expires_at->isPast()`
    - `public function isUsable(): bool` — `! isUsed() && ! isRevoked() && ! isExpired()`
    - `public function planKeyOrDefault(): PlanKey` — `plan_key` or
      `PlanKey::from(config('talent.plans.default'))` via `PlanCatalog::get()`.
    - `#[Scope] protected function usable(Builder $query): void` — the three
      negative conditions, for queries.
- **`App\Invitations\InvitationTokens`** — `final class`:
    - `public const TOKEN_LENGTH = 40;`
    - `public static function generate(): string` — `Str::random(40)`
      (URL-safe alphanumerics).
    - `public static function hash(string $token): string` —
      `hash('sha256', $token)` (64 hex chars).
    - `public static function find(?string $token): ?Invitation` — null/blank →
      null; otherwise `Invitation::query()->where('token_hash', self::hash($token))->first()`.
    - `public static function link(string $token): string` —
      `route('register', ['invite' => $token])`.
      The raw token is never stored and never logged.
- Realtime: the model needs no `BroadcastsRealtime` of its own; Phase 5 wires
  the table to the `invitations` socket channel and dispatches
  `RealtimeEvent` from `booted()` there.

**Steps.**

1. Add the migration.
2. Add `App\Models\Invitation`.
3. Add `App\Invitations\InvitationTokens`.
4. Run `php artisan migrate`.

**Done when.**

- `php artisan migrate` creates `invitations` with the unique `token_hash`.
- `InvitationTokens::hash('x')` is 64 chars; `find()` on an unknown token
  returns null.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** The admin UI (Phase 5), registration consumption
(Phase 6).

---

### Phase 5 — Admin: `InvitationResource` and the new `UserResource` fields

Status: PENDING
Role: filament-admin · Depends on: 2, 4 · Covers: AC01 · Size: M
Spec: B.5 (admin bullet)

**Goal.** The admin mints an invitation, sees the full link exactly once,
revokes links, and can set a user's plan; the list refreshes live.

**Contract.**

- **`app/Filament/Resources/Invitations/InvitationResource.php`** —
  model `Invitation`, navigation icon `Heroicon::OutlinedTicket`, label
  `Invitations`, grouped with the other admin resources; only
  `Pages\ListInvitations` (no create/edit pages — creation is a header
  action).
- **`.../Tables/InvitationsTable.php`** — columns:
  `note` (searchable, placeholder `—`), `email` (searchable, placeholder
  `Any email`), `plan_key` (badge, placeholder `Default`),
  `status` (badge, not a DB column — computed), `creator.name`
  (label `Created by`), `created_at` (dateTime, sortable).
  Status text and colour:
    - revoked → `Revoked`, `danger`
    - used → `Used by {usedBy.name} on {used_at->toFormattedDateString()}`, `gray`
    - expired → `Expired`, `warning`
    - otherwise → `Unused`, `success`
      Filters: `SelectFilter` on `plan_key` (options `PlanKey::class`) and a
      `TernaryFilter`-style select for status (`unused`, `used`, `expired`,
      `revoked`) applied with the model's scopes.
      Record action **Revoke**: `requiresConfirmation()`, visible only when
      `isUsable()`, sets `revoked_at = now()`, then a success
      `Notification::make()->title('Invitation revoked.')`.
      Realtime: `->socket(channel: 'invitations', event: 'InvitationsUpdated')` —
      never `->poll()`.
- **`App\Models\Invitation`** gains the `BroadcastsRealtime` trait and a
  `booted()` that dispatches
  `RealtimeEvent::dispatch('invitations', 'InvitationsUpdated', ['id' => $invitation->id])`
  on `saved` and `deleted`, exactly like `Application` does.
- **Create action** (header action on `ListInvitations`, label
  `New invitation`), form fields:
    - `TextInput::make('note')->label('Note')->maxLength(200)->placeholder('e.g. Wife')`
    - `TextInput::make('email')->email()->nullable()->helperText('When set, the person must register with this exact address.')`
    - `Select::make('plan_key')->options(PlanKey::class)->nullable()->placeholder('Use the default plan')`
    - `TextInput::make('expiry_days')->numeric()->minValue(1)->maxValue(365)->default(fn () => config('talent.invitations.default_expiry_days'))->label('Expires in (days)')->required()`
      On submit: generate the raw token, create the invitation with
      `token_hash`, `created_by = auth()->id()`,
      `expires_at = now()->addDays($expiryDays)`, then show the link **once**.
      The link is surfaced by a persistent modal/section on the list page holding
      a read-only, full-width, copyable `TextInput` with the value
      `InvitationTokens::link($raw)`, label `Sign-up link`, and the helper text
      exactly `This link is shown only once`. Use Filament's
      `->copyable()`/`->suffixAction(CopyAction)` affordance so the admin can copy
      it; never persist or log the raw token.
- **`app/Filament/Resources/Users/Schemas/UserForm.php`**: add
  `Select::make('plan_key')->options(PlanKey::class)->default(PlanKey::Free)->required()`,
  `Select::make('country')` (ISO alpha-2 options; a simple searchable select
  over the union of the three region country lists plus a free-text-safe
  `null` placeholder) with a `->live()` `afterStateUpdated` that fills
  `region` via `RegionResolver::fromCountry()`,
  `TextInput::make('region')->disabled()->dehydrated(false)->label('Region (derived)')`,
  and `Select::make('locale')->options(fn () => collect(config('talent.locales'))->mapWithKeys(fn (string $l) => [$l => strtoupper($l)])->all())->required()`.
- **`app/Filament/Resources/Users/Tables/UsersTable.php`**: add a
  `plan_key` badge column (sortable) and a `SelectFilter` on `plan_key`.
  Leave the existing columns, block/unblock actions and `AdminGuard` logic
  untouched.
- The panel already registers `FilamentRealtimeDriverPlugin` — do not
  duplicate any panel configuration.

**Steps.**

1. Create the resource, table, form schema and `ListInvitations` page.
2. Add `BroadcastsRealtime` + `booted()` to `Invitation`.
3. Add the create action with the show-once link.
4. Add the revoke record action.
5. Extend `UserForm` and `UsersTable`.

**Done when.**

- `/admin/invitations` lists invitations with the four status badges and
  refreshes without polling when a row changes in another window.
- Creating an invitation shows a copyable full link with
  `This link is shown only once`; reopening the page never shows it again.
- Revoke flips the row to `Revoked` and the invitation is no longer usable.
- `/admin/users` edits `plan_key`, `country` (region follows, read-only) and
  `locale`.
- `grep -rn "poll()" app/Filament/Resources/Invitations` returns nothing.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** Registration (Phase 6), invitation emails (no invite
email is sent — the admin shares the link manually).

---

### Phase 6 — Register and Closed, backed by single-use invitations

Status: PENDING
Role: laravel-backend · Depends on: 2, 4 · Covers: AC02, AC03 · Size: M
Spec: B.5 (Register, Store, Closed)

**Goal.** `GET /register?invite=…` opens the form only for a usable
invitation; `POST /register` creates exactly one user per link, even under
concurrent submits.

**Contract.**

- **Routes** (inside the existing `Route::middleware('guest')` group, replacing
  the two `Route::inertia` placeholders for register):
    ```php
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:10,1')->name('register.store');
    Route::inertia('/register/closed', 'auth/closed')->name('register.closed');
    ```
    `register.closed` keeps its current static `Route::inertia` form.
- **`App\Http\Controllers\Auth\RegisterController::create(Request $request)`**:
  `InvitationTokens::find($request->query('invite'))`; when the invitation is
  missing or `! isUsable()`, `redirect()->route('register.closed')`. Otherwise
  `Inertia::render('auth/register', ['invite' => ['email' => $invitation->email]])`.
  The prop carries **only** the email — never the token, never the hash,
  never the plan.
- **`App\Http\Requests\Auth\RegisterRequest`** (`authorize(): true`), rules:
    - `name` → `required|string|min:2|max:80`
    - `email` → `required|string|email|max:255|unique:users,email`
    - `password` → `required|string|min:10|confirmed`
    - `invite` → `required|string|size:40`
    - `timezone` → `required|string|timezone` (Laravel's `timezone` rule =
      valid IANA identifier)
      A `withValidator`/`after` closure resolves the invitation and adds an error
      on `email` (`validation.custom.email.invite_mismatch`, English text
      `This invitation is bound to a different email address.`) when
      `$invitation->email !== null` and
      `mb_strtolower($invitation->email) !== mb_strtolower($request->email)`.
      An unusable or unknown invite adds an error on `invite` so `store()` can
      redirect to Closed.
- **`RegisterController::store(RegisterRequest $request)`**, inside
  `DB::transaction(...)`:
    1. `Invitation::query()->where('token_hash', InvitationTokens::hash($token))->lockForUpdate()->first()`
    2. Re-check `isUsable()`; if not, abort the transaction and
       `redirect()->route('register.closed')`.
    3. Create the user: `name`, `email`, `password` (the model's `hashed` cast
       does the hashing), `is_admin = false`, `status = UserStatus::Active`,
       `email_verified_at = now()`, `plan_key = $invitation->planKeyOrDefault()`,
       `locale = app()->getLocale()`, `timezone = $validated['timezone']`,
       `country = null`, `region = Region::Row` (Onboarding basics sets them).
    4. `$invitation->forceFill(['used_by' => $user->id, 'used_at' => now()])->save();`
       Then, outside the transaction: `Auth::login($user)`,
       `$request->session()->regenerate()`, `redirect()->route('onboarding')`.
       The unique index on `invitations.token_hash` plus `lockForUpdate` guarantees
       that two simultaneous submits with one link produce exactly one user; the
       loser lands on Closed.
- `RegisterRequest` must not leak whether an email is already registered
  beyond Laravel's standard `unique` message (the invite gate already makes
  enumeration pointless).

**Steps.**

1. Add `RegisterRequest`.
2. Add `RegisterController` (`create`, `store`).
3. Replace the register routes in `routes/web.php`.
4. Add the two new validation strings to `lang/en/validation.php` and create
   `lang/pt/validation.php`'s matching entry (the file already exists).

**Done when.**

- `GET /register` with no `invite`, an unknown token, a used, revoked or
  expired one → 302 to `/register/closed`.
- `GET /register?invite=<usable>` returns the `auth/register` page with
  `invite.email` (null or the bound address) and nothing else.
- `POST /register` with a valid payload creates an active non-admin user with
  the invitation's plan, marks the invitation used and redirects to
  `/onboarding`; a second `POST` with the same token redirects to
  `/register/closed` and creates no user.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** The register page itself still posts nothing (Phase 8
wires it), password reset (Phase 7).

---

### Phase 7 — Password reset: broker routes and a branded, localized mail

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: AC04 · Size: M
Spec: B.5 (Password reset), Part 0.3

**Goal.** Forgot/reset password works through Laravel's password broker, with
one branded email rendered in the user's locale and no user enumeration.

**Contract.**

- **Routes** (guest group; replace the two `Route::inertia` placeholders):
    ```php
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.update');
    ```
- **`App\Http\Controllers\Auth\ForgotPasswordController`**:
    - `create()` → `Inertia::render('auth/forgot-password')`.
    - `store()` validates `email` → `required|email`, calls
      `Password::sendResetLink($request->only('email'))`, and **always**
      redirects back with the same flash, whatever the broker returns:
      `->with('success', __('passwords.sent'))`. No error is ever put on the
      `email` field, so an unknown address is indistinguishable from a known
      one.
- **`App\Http\Controllers\Auth\ResetPasswordController`**:
    - `create(Request $request, string $token)` →
      `Inertia::render('auth/reset-password', ['token' => $token, 'email' => $request->query('email')])`.
    - `store()` validates `token` → `required|string`, `email` →
      `required|email`, `password` → `required|string|min:10|confirmed`; calls
      `Password::reset(...)` setting `password` and
      `remember_token = Str::random(60)`, then `event(new PasswordReset($user))`.
      Success → `redirect()->route('login')->with('success', __('passwords.reset'))`;
      failure → back with an error on `email` from the broker status string.
- **`App\Notifications\Client\ResetPassword`** (extends
  `Illuminate\Auth\Notifications\ResetPassword`):
    - `via()` → `['mail']`; sent through the **default** mailer (`resend` in
      production, `log` locally).
    - `toMail($notifiable)` returns a `MailMessage` whose `view` is
      `mail.client.reset-password` with data
      `['brand' => config('talent.brand.name'), 'url' => $this->resetUrl($notifiable), 'user' => $notifiable, 'expireMinutes' => config('auth.passwords.users.expire')]`,
      subject `__('mail.reset.subject', ['brand' => $brand])`.
    - Wrapped in `Illuminate\Support\Facades\App::setLocale()` for the
      notifiable's locale: implement `toMail` with
      `$notifiable->locale` and the notification's `locale()` method
      (`ResetPassword` is `Illuminate\Contracts\Translation\HasLocalePreference`
      on the user side — add
      `public function preferredLocale(): ?string { return $this->locale; }` to
      `App\Models\User`, which is the idiomatic way to localize the mail).
- **`App\Models\User::sendPasswordResetNotification(string $token): void`** →
  `$this->notify(new ResetPassword($token));`
- **`resources/views/mail/client/reset-password.blade.php`** — a plain, brand
  styled HTML mail (no Filament, no Markdown mailable): the brand wordmark as
  text, a greeting, one paragraph, one accent-coloured anchor button labelled
  `__('mail.reset.button')`, a fallback plain URL line, and the expiry
  sentence. Accent colour is inlined (mail clients strip `<style>`); reuse the
  brand accent already used by the app shell.
- **i18n**: add to `lang/en.json` **and** `lang/pt.json` (same key set) —
  `mail.reset.subject` (`Reset your {brand} password` /
  `Redefina sua senha do {brand}`), `mail.reset.greeting`,
  `mail.reset.body`, `mail.reset.button` (`Reset password` /
  `Redefinir senha`), `mail.reset.fallback`, `mail.reset.expiry`,
  `mail.reset.ignore`. Create **`lang/pt/passwords.php`** and
  **`lang/pt/auth.php`** mirroring the English files (Brazilian Portuguese),
  since only `lang/pt/validation.php` exists today.
- `config/auth.php` password broker settings stay as they are.

**Steps.**

1. Add the two controllers.
2. Add `App\Notifications\Client\ResetPassword` and the Blade mail view.
3. Add `sendPasswordResetNotification` + `preferredLocale` to `User`.
4. Replace the routes.
5. Add the `mail.reset.*` keys to both JSON lang files; add
   `lang/pt/passwords.php` and `lang/pt/auth.php`.

**Done when.**

- `POST /forgot-password` with a known and with an unknown address both
  redirect back with the identical success flash and no field errors.
- With `MAIL_MAILER=log`, `storage/logs/laravel.log` holds one mail whose
  subject and button are in the user's locale and carry the brand name.
- `GET /reset-password/{token}` renders; `POST /reset-password` with a
  matching token and a 10+ character confirmed password logs the change in
  and redirects to `/login` with a success flash.
- `lang/en.json` and `lang/pt.json` have the same key count.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** The three auth pages still use local state (Phase 8);
no other transactional mail is added.

---

### Phase 8 — Auth pages submit for real

Status: PENDING
Role: inertia-frontend · Depends on: 6, 7 · Covers: AC02, AC03, AC04 · Size: M
Spec: B.5 (last bullet), `client-app-screens` B.6 auth screens

**Goal.** Register, Forgot password and Reset password post to the new
endpoints with Inertia `<Form>`, exactly like the existing Login page.

**Contract.**

- **`resources/js/pages/auth/register.tsx`**:
    - Props: `{ invite: { email: string | null } }`. Delete the
      `useInviteCheck` call, the `RegisterSkeleton`, the redirect-to-closed
      effect and the fake `setDone` success branch — the server already
      guarantees the invite is usable before the page renders.
    - Read the raw token from the URL once
      (`new URLSearchParams(window.location.search).get('invite')`) and submit
      it as a hidden `invite` field.
    - Use `<Form {...store()} resetOnSuccess={['password', 'password_confirmation']}>`
      with `store` from
      `@/actions/App/Http/Controllers/Auth/RegisterController`, mirroring
      `pages/auth/login.tsx`: `{({ errors, processing }) => …}`, `Field` +
      `Input`, server errors on `name`, `email`, `password`,
      `password_confirmation`, `invite`.
    - Fields: `name`, `email` (value defaults to `invite.email`,
      `readOnly` when `invite.email !== null`), `password`,
      `password_confirmation`, hidden `invite`, hidden `timezone` populated from
      `Intl.DateTimeFormat().resolvedOptions().timeZone` with an `UTC`
      fallback.
    - Remove the now-unused client-side `EMAIL_PATTERN` / `FieldErrors` logic.
- **`resources/js/pages/auth/forgot-password.tsx`**: `<Form {...store()}>`
  from `ForgotPasswordController`; keep the "check your inbox" panel but drive
  it from the `flash.success` shared prop (via the existing
  `useFlashToasts`/`usePage` pattern) instead of local `sent` state — the
  server always answers the same way.
- **`resources/js/pages/auth/reset-password.tsx`**: props
  `{ token: string; email: string | null }`; `<Form {...store()}>` from
  `ResetPasswordController` with hidden `token`, `email` (read-only when
  provided), `password`, `password_confirmation`.
- **Delete** `resources/js/data/hooks/use-invite-check.ts` and the now-unused
  `keys.invite` entry in `resources/js/data/keys.ts`, plus the `checkInvite`
  fixture handler export in `data/fixtures/handlers/account.ts` if nothing else
  references it (grep before deleting).
- **i18n**: reuse the existing `auth.register.*`, `auth.forgot.*`,
  `auth.reset.*` keys. Remove keys that become dead (`auth.register.errors.*`
  are replaced by server messages, `auth.register.success_*` by the redirect)
  from **both** `lang/en.json` and `lang/pt.json`, keeping the key sets
  identical. Add `auth.register.invite_locked_hint`
  (`This invitation is bound to this email address.` /
  `Este convite está vinculado a este e-mail.`) for the read-only email field.
- Wayfinder action modules for the new controllers are generated by
  `yarn dev`/`yarn build`; run a build (or `yarn dev` once) so
  `resources/js/actions/App/Http/Controllers/Auth/RegisterController.ts` etc.
  exist before importing them.

**Steps.**

1. Regenerate Wayfinder output so the new action modules exist.
2. Rewrite `register.tsx` against the `invite` prop and `<Form>`.
3. Rewrite `forgot-password.tsx` and `reset-password.tsx` against `<Form>`.
4. Delete `use-invite-check.ts`, `keys.invite` and the dead fixture handler.
5. Prune/add the lang keys in both JSON files.

**Done when.**

- Opening a real invitation link shows the form with the email prefilled and
  read-only when bound; submitting lands on `/onboarding` logged in.
- Reusing the same link shows the Closed page.
- Forgot password shows the same confirmation for any address; the reset link
  from the mail log completes the reset and lands on `/login`.
- `grep -rn "use-invite-check\|keys.invite" resources/js/` returns nothing.
- `lang/en.json` and `lang/pt.json` have the same key count.
- `yarn run check` and `yarn run types:check` pass.

**Not in this phase.** Any `/internal` wiring (Phases 19–20).

---

### Phase 9 — The `/internal` group, `AccountStatus` and onboarding basics

Status: PENDING
Role: laravel-backend · Depends on: 2, 3 · Covers: AC05, AC09 · Size: M
Spec: B.6 (group, `account/status`, `onboarding/basics`)

**Goal.** The internal JSON API exists, and the one endpoint every screen
depends on returns the exact `AccountStatus` contract.

**Contract.**

- **Route group** in `routes/web.php`:
    ```php
    Route::middleware(['auth', 'client', 'throttle:120,1'])
        ->prefix('internal')->name('internal.')
        ->group(function (): void { /* … */ });
    ```
    Controllers live in `App\Http\Controllers\Client\Internal\`, resources in
    `App\Http\Resources\Client\`.
- **`AppServiceProvider::boot()`**: `JsonResource::withoutWrapping();` so no
  response is wrapped in `data` (the contracts have no envelope). Verify this
  does not change any existing response — no API resources exist today.
- **`App\Client\AccountStatusPresenter`** — `final class`, constructor-injects
  `PlanCatalog`, with `public function forUser(User $user): array` returning
  the `AccountStatus` array. Field by field:
    - `plan` → `{ key: $plan->key->value, name, mode, dailyLimit }`
    - `quota.usedToday` → `$user->applications()->countedToday()->count()`;
      `limit` → `$plan->dailyLimit`;
      `remaining` → `max(0, limit - usedToday)`;
      `resetsAt` → `now()->addDay()->startOfDay()->toIso8601String()` in the
      **app** timezone (`config('app.timezone')`), per spec B.6.
    - `gmail.state` → from `$user->gmailIntegration`: null → `disconnected`,
      else map `ConnectedIntegrationStatus::Connected` → `connected`,
      `ReauthorizationRequired` → `reauthorization_required`,
      `Disconnected` → `disconnected`.
      `gmail.accountEmail` → `account_email` when the state is `connected`,
      otherwise `null`.
    - `sending` → `{ paused: false, autoPausedReason: null }` (spec 6 owns it).
    - `onboarding.steps` → in this exact order, each `{ key, done }`:
      `basics` = `country !== null && timezone !== null`;
      `gmail` = state is `connected`;
      `profile` = the legacy CV + template check — reuse
      `CanSendApplications`'s private predicates by extracting them into two
      public methods on that class (`hasCv(?JobPreference)` and
      `hasValidTemplate(?JobPreference)`) rather than duplicating the logic;
      `preferences` = `$user->jobPreference()->exists()`.
      `onboarding.complete` = all four `done`.
    - `profiles.activeLanguages` → `['en','pt']` filtered to the languages that
      have the legacy CV, i.e. `hasCv()` true → `['en','pt']`, false → `[]`
      (spec 5 replaces this with real per-language profiles). Document the
      temporary rule in a comment citing spec B.6.
    - `region` → `$user->region->value`; `country` → `$user->country`;
      `timezone` → `$user->timezone`.
    - `unreadNotifications` → `$user->unreadNotifications()->count()`.
- **`App\Http\Resources\Client\AccountStatusResource`** — wraps the presenter
  output; `toArray()` returns it unchanged. (Keeping a resource here means
  every client payload goes through `Resources\Client\*`, which AC06's grep
  relies on.)
- **`GET /internal/account/status`** → `AccountStatusController@__invoke` →
  `AccountStatusResource`. Route name `internal.account.status`.
- **`PUT /internal/onboarding/basics`** →
  `OnboardingBasicsController@__invoke` with
  `App\Http\Requests\Client\UpdateOnboardingBasicsRequest`:
  `country` → `required|string|size:2`, `locale` →
  `['required', Rule::in(config('talent.locales'))]`, `timezone` →
  `required|string|timezone`. Saves `country` (uppercased),
  `region = RegionResolver::fromCountry($country)`, `locale`, `timezone`, then
  returns the **same** `AccountStatusResource`. Route name
  `internal.onboarding.basics`.
- No endpoint returns `url`, `apply_url`, `company_website`, `domain`,
  `recipient_email`, contact data, `raw` or `description_html`.

**Steps.**

1. Add the `/internal` route group and `JsonResource::withoutWrapping()`.
2. Extract `hasCv` / `hasValidTemplate` to public methods on
   `CanSendApplications` (behaviour unchanged).
3. Add `AccountStatusPresenter` and `AccountStatusResource`.
4. Add `AccountStatusController` and `OnboardingBasicsController` +
   `UpdateOnboardingBasicsRequest`.

**Done when.**

- `GET /internal/account/status` as the seeded client returns exactly the 10
  top-level keys of `AccountStatus` (`plan`, `quota`, `gmail`, `sending`,
  `onboarding`, `profiles`, `region`, `country`, `timezone`,
  `unreadNotifications`) with no `data` wrapper.
- Unauthenticated → 302 to `/login`; a blocked user → the `client`
  middleware's behaviour, unchanged.
- `PUT /internal/onboarding/basics` with `{country:'PT',locale:'pt',timezone:'Europe/Lisbon'}`
  stores `region = eu` and returns the updated `AccountStatus`.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** Every other endpoint; `sending` staying false is
deliberate.

---

### Phase 10 — Jobs endpoints

Status: PENDING
Role: laravel-backend · Depends on: 3, 9 · Covers: AC05, AC06, AC09 · Size: M
Spec: B.6 (jobs bullet), contracts `JobCard`, `JobDetail`, `JobsPage`

**Goal.** The Jobs screen's list and detail run on the client's real pool,
one row per company, with nothing leaky in the payload.

**Contract.**

- **`App\Client\JobPoolQuery`** — `final class` building on
  `MatchingJobPostings::forUser($user)` (the single source of truth; do not
  re-implement the pool). Adds, from validated filters:
    - `q` → `where(fn => orWhere('job_postings.title','ilike',%q%)->orWhere('companies.name','ilike',%q%))`
    - `language` → `where('job_posting_profiles.language', $language)` when it
      is `en` or `pt` (`all`/absent → no condition)
    - `seniority[]` → `whereIn('job_posting_profiles.seniority', $values)`
    - `remote` → `remote` → `is_remote = true`; `not_remote` →
      `where(fn => whereNull('is_remote')->orWhere('is_remote', false))`;
      `any`/absent → nothing. Read `is_remote` from `job_posting_profiles`,
      falling back to `job_postings.is_remote` with `coalesce`.
    - `today` → `first_seen_at >= now()->startOfDay()` (user timezone, falling
      back to `config('app.timezone')`)
    - `stack[]` → `whereRaw("job_posting_profiles.stack ??| array[…]::text[]")`
      with `StackNormalizer::normalize()` applied first, exactly like
      `MatchingJobPostings` does.
    - **One row per company:** keep only the newest posting per company. Use a
      `whereNotExists` on a correlated sub-select ("no other pool posting for
      this company is newer"), ordering by `first_seen_at desc, id desc`, so the
      result stays a `Builder` that cursor pagination can page.
    - Ordering: `first_seen_at desc, id desc`.
- **`App\Http\Resources\Client\JobCardResource`** → exactly:
  `id`, `company` (`{id, name, initials}` — initials from the first letters of
  the first and last word of the company name, uppercased),
  `title`, `location`, `isRemote`, `language`, `seniority` (profile value,
  `unknown` when null), `stack` (profile stack, first 6),
  `summary` (`ClientSafeText::redact($profile->summary)`, `null` when empty),
  `firstSeenAt` (ISO-8601), `collectedToday` (`first_seen_at` is today in the
  user's timezone).
- **`App\Http\Resources\Client\JobDetailResource`** extends the card fields and
  adds `locations` (profile `locations`), `employmentType`, `department`,
  `publishedAt`, `sourceLabel` (`$posting->source->name` — **text only**).
- **`GET /internal/jobs`** → `JobsController@index`, validated by
  `App\Http\Requests\Client\JobFiltersRequest` (`q` string max 120,
  `language` in `en,pt,all`, `seniority.*` in the six seniority values,
  `remote` in `any,remote,not_remote`, `today` boolean, `stack.*` string max
  40, `cursor` nullable string). Cursor pagination, **20 per page**, response:
    ```
    { data: JobCard[], meta: { nextCursor, total }, summary: { total, collectedToday, lockedByLanguage: [] } }
    ```
    `meta.total` and `summary.total` are the filtered company-distinct count;
    `summary.collectedToday` is that count restricted to today.
    `summary.lockedByLanguage` is `[]` until spec 5. `nextCursor` is the
    `cursor()` string of the next page or `null`.
- **`GET /internal/jobs/{id}`** → `JobsController@show`: look the posting up
  **through** `JobPoolQuery` for the current user (no filters) and `abort(404)`
  when it is not there. Returns `JobDetailResource`.
- Route names `internal.jobs.index`, `internal.jobs.show`.
- **Never serialise** `url`, `apply_url`, `company_website`,
  `companies.domain`, `description_html`, `description_text`, `raw`, contacts.

**Steps.**

1. Add `JobFiltersRequest`.
2. Add `JobPoolQuery`.
3. Add `JobCardResource` and `JobDetailResource`.
4. Add `JobsController` (`index`, `show`) and the two routes.

**Done when.**

- `GET /internal/jobs` returns at most 20 cards, one per company, newest
  first, with `meta.nextCursor` paging correctly and `summary.lockedByLanguage`
  `[]`.
- Each filter narrows the list; `language=pt` returns only `pt` cards and no
  card ever has `language: "other"` or `null`.
- `GET /internal/jobs/{id}` for a posting outside the caller's pool → 404.
- `grep -nE "'(url|apply_url|company_website|domain|description_html|raw)'" app/Http/Resources/Client/` returns nothing.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** Queueing applications, review drafts, per-language
locking (all out of scope).

---

### Phase 11 — Applications endpoints, client-safe errors and bodies

Status: PENDING
Role: laravel-backend · Depends on: 9 · Covers: AC05, AC06, AC09 · Size: M
Spec: B.6 (applications bullet), **D1 option A**

**Goal.** The Applications screen's list, detail and tab counters run on real
data, with every error sentence translated and every job URL replaced by a
token.

**Contract.**

- **`App\Outreach\Support\ClientErrorMessage`** — `final class`,
  `public static function for(?string $lastError): ?string`. Null/blank →
  null. Maps the known `last_error` values written by
  `SendApplicationEmail` (match on prefix, case-sensitive):
    | `last_error` starts with                                                | translation key                  | English                              |
    | ----------------------------------------------------------------------- | -------------------------------- | ------------------------------------ |
    | `Gmail is not connected.`                                               | `applications.error.gmail`       | Your Gmail account is not connected. |
    | `CV file is missing.`                                                   | `applications.error.cv`          | Your CV file could not be read.      |
    | `Recipient is not a verified contact`                                   | `applications.error.recipient`   | We could not confirm the recipient.  |
    | `Company is no longer verified`                                         | `applications.error.company`     | This company is no longer available. |
    | `Client account is not active.`                                         | `applications.error.account`     | Your account is not active.          |
    | `Worker stopped mid-send.`                                              | `applications.error.unconfirmed` | We could not confirm delivery.       |
    | `ConnectionException`, `RequestException`, `TransportException`         | `applications.error.unconfirmed` | We could not confirm delivery.       |
    | anything else                                                           | `applications.error.default`     | Sending failed.                      |
    | Raw `last_error` text is never returned, and the class never echoes the |
    | input. Add all eight keys to `lang/en.json` and `lang/pt.json`.         |
- **`App\Outreach\Support\ClientSafeText`** gains
  `public static function tokenizeJobUrl(string $text, ?string $jobUrl): string`:
  when `$jobUrl` is filled, replace every literal occurrence of it with
  `{{ job_url }}`; then run the existing `redact()` over the result so any
  other link or address still becomes `[link]`/`[email]`. Order matters — the
  token is produced before redaction, and `redact()` must leave
  `{{ job_url }}` untouched (it does: no scheme, no dot-TLD-slash).
- **`App\Http\Resources\Client\ApplicationItemResource`** → exactly:
  `id`, `company` (`{id, name, initials}`), `title`
  (`jobPosting?->title`, may be null), `language`, `origin`
  (`auto`/`manual` from `ApplicationOrigin`), `status`, `stage` → `null`,
  `subStep` → `null`, `lastError` (`ClientErrorMessage::for`), `queuedAt`,
  `scheduledFor`, `sentAt` (ISO-8601 or null).
  `language` is non-nullable in the contract, so resolve it deterministically:
  `applications.language` → else the job posting profile's `language` when it
  is `en`/`pt` → else the owner's `locale` when it is `en`/`pt` → else `en`.
  Put that fallback in one private helper and comment that spec 5 fills the
  column on send.
- **`App\Http\Resources\Client\ApplicationDetailResource`** adds
  `subject` (`ClientSafeText::tokenizeJobUrl`), `body` (same),
  `cvFileName` (`user->jobPreference->cv_original_name`, null when absent),
  `timeline` — derived until spec 6: `queued_at` → no entry (the contract's
  timeline holds `SendStage`s), so emit
  `[{stage:'sending', at: sent_at ?? queued_at}]`-style entries only for what
  is known: when `sent_at` is set →
  `[{stage:'sent', at: sent_at}]`; when the status is `failed` →
  `[{stage:'failed', at: updated_at}]`; otherwise `[]`. Never invent
  intermediate stages.
- **`GET /internal/applications`** → `ApplicationsController@index`, validated
  by `App\Http\Requests\Client\ApplicationFiltersRequest` (`status` in
  `all,in_progress,sent,attention`, `language` in `en,pt,all`, `q` string max
  120, `cursor` nullable string). Scope: `$request->user()->applications()`.
  Status groups: `in_progress` → `[queued, sending]`, `sent` → `[sent]`,
  `attention` → `[failed, ambiguous]`, `all` → no filter. `q` → ilike on the
  company name or the posting title. `language` filters the stored column and,
  when it is null, the fallback chain's first source (join
  `job_posting_profiles`). Order `updated_at desc, id desc`; cursor
  pagination, 20 per page. Response `{ data, meta: { nextCursor, total } }`.
- **`GET /internal/applications/{application}`** →
  `ApplicationsController@show`, authorised with the existing
  `ApplicationPolicy` (`$this->authorize('view', $application)`); a foreign
  record 403/404s rather than leaking. Returns
  `ApplicationDetailResource`.
- **`GET /internal/applications/counts`** (D1 option A) →
  `ApplicationsController@counts` → `{ all, in_progress, sent, attention }`
  over the user's whole history, ignoring `language` and `q`. Register it
  **before** the `{application}` route so `counts` is never taken as an id.
  _If the owner chose D1 option B, skip this bullet and tell Phase 19 to gate
  `useApplicationCounts` to fixtures._
- Route names `internal.applications.index`, `internal.applications.counts`,
  `internal.applications.show`.
- **Never serialise** `recipient_email`, `contact`, `provider_message_id`, the
  raw `last_error`, or the un-tokenized body.

**Steps.**

1. Add `ClientErrorMessage` + the eight lang keys in both JSON files.
2. Add `ClientSafeText::tokenizeJobUrl`.
3. Add the two resources.
4. Add `ApplicationFiltersRequest`.
5. Add `ApplicationsController` (`index`, `counts`, `show`) and the routes in
   the right order.

**Done when.**

- `GET /internal/applications` returns only the caller's applications, 20 per
  page, `stage` and `subStep` null, `lastError` a translated sentence or null.
- A failed application whose `last_error` is
  `RequestException: 401 Unauthorized …` surfaces as
  `We could not confirm delivery.` and the raw text appears nowhere in the
  response.
- `GET /internal/applications/{id}` for another user's application does not
  return the record; the detail body shows `{{ job_url }}` where the job link
  was.
- `GET /internal/applications/counts` returns the four integers.
- `grep -rn "recipient_email\|provider_message_id" app/Http/Resources/Client/` returns nothing.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** Stages, sub-steps, a real timeline, `origin` semantics
beyond the existing enum (spec 6).

---

### Phase 12 — Dashboard and chart endpoints

Status: PENDING
Role: laravel-backend · Depends on: 10, 11 · Covers: AC05, AC09 · Size: M
Spec: B.6 (dashboard and chart bullets), contracts `DashboardData`, `ChartData`

**Goal.** The Dashboard's KPIs, hero, matches and activity, plus the chart,
computed in the user's timezone from real rows.

**Contract.**

- **`App\Client\DashboardPresenter`** — `final class`, injects `PlanCatalog`
  and `JobPoolQuery`. `public function forUser(User $user, string $period): array`.
  Period boundaries use `$user->timezone ?: config('app.timezone')`:
  `today` → start of today → now; `week` → `startOfWeek()` → now; `month` →
  `startOfMonth()` → now. The **previous** period is the immediately
  preceding window of equal length.
    - `period` → echoed back.
    - `kpis.collected` → `{ value, previous }`: count of the user's pool
      (`JobPoolQuery` with no filters, company-distinct) whose `first_seen_at`
      falls in the window / the previous window.
    - `kpis.sent` → applications with `status = sent` and `sent_at` in the
      window / previous window.
    - `kpis.totalSent` → all-time `status = sent` count.
    - `kpis.firstSentAt` → `min(sent_at)` or null.
    - `hero.sentToday` → `status = sent` with `sent_at` today;
      `hero.failedToday` → `status = failed` with `updated_at` today;
      `hero.queued` → `status in (queued, sending)`;
      `hero.nextSendAt` → `min(scheduled_for)` over queued, or null;
      `hero.lastDays` → the **5 days before today**, oldest first, each
      `{ date: 'YYYY-MM-DD', count }` of sent applications on that day in the
      user's timezone (zero-filled).
    - `matches` → `{ total, newToday, items }`: `total` is the pool count,
      `newToday` the pool count with `first_seen_at` today, `items` the 4 newest
      pool cards through `JobCardResource`.
    - `activity` → the 5 newest applications by `updated_at` through
      `ApplicationItemResource`.
- **`GET /internal/dashboard`** → `DashboardController@__invoke`; `period`
  validated `in:today,week,month` and defaulting to `today`. Returns
  `App\Http\Resources\Client\DashboardResource`.
- **`App\Client\ChartPresenter`** / **`GET /internal/dashboard/chart`** →
  `range` validated `in:14d,30d`, default `14d`. Returns
  `{ range, days: [{date, count}], averagePerActiveDay, limit }` where `days`
  is oldest first **including today**, zero-filled, counting sent
  applications per day in the user's timezone;
  `averagePerActiveDay` = sent total ÷ days with `count > 0`, rounded to one
  decimal (`0` when there are none); `limit` = the plan's `dailyLimit`.
- Route names `internal.dashboard`, `internal.dashboard.chart`.
- All date-only strings are `YYYY-MM-DD` in the user's timezone;
  all date-times are ISO-8601.

**Steps.**

1. Add `DashboardPresenter` and `ChartPresenter`.
2. Add `DashboardResource` and `ChartResource`.
3. Add `DashboardController` and `ChartController` + routes.

**Done when.**

- `GET /internal/dashboard?period=week` returns exactly the four
  `DashboardData` keys with `kpis`, `hero`, `matches`, `activity` populated
  and `hero.lastDays` holding 5 entries, oldest first, none of them today.
- `GET /internal/dashboard/chart?range=30d` returns 30 day entries ending
  today and `limit` equal to the caller's plan limit.
- A user in `Europe/Lisbon` and one in `America/Sao_Paulo` get different day
  boundaries for the same rows.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** `LiveSending` / `/internal/sending` (spec 6).

---

### Phase 13 — Notifications, account read/update, password change

Status: PENDING
Role: laravel-backend · Depends on: 9 · Covers: AC05, AC09 · Size: M
Spec: B.6 (notifications and account bullets)

**Goal.** The bell popover and the Account screen's profile, language/region
and password cards run on real data.

**Contract.**

- **`App\Http\Resources\Client\NotificationResource`** — maps a
  `DatabaseNotification` to `NotificationItem`: `id` (uuid string),
  `type` (the contract's `NotificationType` slug, taken from the stored
  `data['type']`; fall back to `Str::snake(class_basename($notification->type))`
  for the Filament notifications already in the table), `data`
  (`Record<string, string|number|null>` — cast every value to string, int or
  null; drop nested arrays, and never pass through a URL or address),
  `readAt`, `createdAt`.
- **`GET /internal/notifications`** → `NotificationsController@index`: the
  caller's 30 newest notifications, newest first, as a bare JSON array (the
  hook types it `NotificationItem[]`).
- **`POST /internal/notifications/read-all`** →
  `NotificationsController@readAll`: `$user->unreadNotifications->markAsRead()`,
  returns `{}` with 200.
- **`GET /internal/account`** → `AccountController@show` →
  `App\Http\Resources\Client\AccountResource`: `name`, `email`, `locale`,
  `timezone`, `country`, `region` (value). Nothing else.
- **`PUT /internal/account`** → `AccountController@update` with
  `App\Http\Requests\Client\UpdateAccountRequest`: `name` →
  `required|string|min:2|max:80`, `locale` →
  `['required', Rule::in(config('talent.locales'))]`, `timezone` →
  `nullable|string|timezone`, `country` → `nullable|string|size:2`.
  `email` is **not** editable here. Saves the fields, re-derives `region` from
  `country` via `RegionResolver`, and returns the updated `AccountResource`.
- **`PUT /internal/account/password`** → `AccountController@updatePassword`
  with `App\Http\Requests\Client\UpdatePasswordRequest`:
  `current_password` → `['required', 'current_password']`, `password` →
  `required|string|min:10|confirmed`. On success saves the new password
  (the `hashed` cast hashes it), calls
  `Auth::logoutOtherDevices($request->password)` **only if** the session
  driver supports it — otherwise just regenerates the current session token —
  and returns `{}`.
- Route names `internal.notifications.index`,
  `internal.notifications.read-all`, `internal.account.show`,
  `internal.account.update`, `internal.account.password`.
- Register `internal.account.status` (Phase 9) **before** the
  `internal.account.show` route only if paths could collide; they do not
  (`/internal/account/status` vs `/internal/account`), but keep the status
  route first for clarity.

**Steps.**

1. Add `NotificationResource` and `NotificationsController` + routes.
2. Add `AccountResource`.
3. Add `UpdateAccountRequest` and `UpdatePasswordRequest`.
4. Add `AccountController` (`show`, `update`, `updatePassword`) + routes.

**Done when.**

- `GET /internal/notifications` returns an array whose items have exactly
  `id`, `type`, `data`, `readAt`, `createdAt`, and no URL anywhere in `data`.
- `POST /internal/notifications/read-all` zeroes
  `AccountStatus.unreadNotifications`.
- `PUT /internal/account` changing `country` to `US` moves `region` to `row`.
- `PUT /internal/account/password` rejects a wrong current password with a
  422 on `current_password`.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** Delete and export (Phase 14).

---

### Phase 14 — Account delete and data export

Status: PENDING
Role: laravel-backend · Depends on: 13 · Covers: AC05, AC12 · Size: M
Spec: B.6 (`DELETE /internal/account`, `GET /internal/account/export`)

**Goal.** A client can delete their account — user row, CV files and Gmail
tokens — and download their data as JSON without recipient addresses.

**Contract.**

- **`App\Actions\Users\DeleteClientAccount`** — `final class`, injects
  `DisconnectConnectedIntegration`. `public function run(User $user): void`:
    1. Best-effort token revocation + row removal for every connected
       integration through the existing `DisconnectConnectedIntegration`
       action, each call wrapped in try/catch with `report($e)` so a dead
       provider never blocks the deletion.
    2. Delete the user's CV folder: `Storage::disk('local')->deleteDirectory('cvs/'.$user->id)`.
       Never touch another user's folder — the path is built from the id only.
    3. `$user->delete()` — `applications.user_id` is `cascadeOnDelete`, so
       applications go with it; `invitations.used_by` is `nullOnDelete`, so the
       invitation history survives with a null `used_by`;
       `invitations.created_by` is `restrictOnDelete`, so an admin who minted
       invitations cannot be deleted this way — that is fine, clients never
       create invitations, but the action must surface a clear error instead of
       a raw QueryException if it ever happens.
       This is a destructive operation **triggered by the account owner**, not by
       the executor: never call it from a phase, a seeder or a script.
- **`DELETE /internal/account`** → `AccountController@destroy` with
  `App\Http\Requests\Client\DeleteAccountRequest`
  (`password` → `['required', 'current_password']`). Order: validate, run
  `DeleteClientAccount`, `Auth::logout()`,
  `$request->session()->invalidate()`, `$request->session()->regenerateToken()`,
  return `{}` with 200 (the frontend then navigates to `home`).
- **`GET /internal/account/export`** → `AccountExportController@__invoke`:
  a streamed JSON download, `Content-Disposition: attachment`, filename
  `talent-labs-export-{userId}-{Y-m-d}.json`, body:
    ```json
    {
      "exportedAt": "<ISO-8601>",
      "account": { "name", "email", "locale", "timezone", "country", "region", "planKey", "createdAt" },
      "preferences": { "titles", "keywords", "stack", "locations", "acceptsRemote", "cvOriginalName", "emailSubject", "emailBody" },
      "applications": [ { "company", "title", "language", "origin", "status", "subject", "body", "queuedAt", "scheduledFor", "sentAt" } ]
    }
    ```
    `subject`/`body` go through `ClientSafeText::tokenizeJobUrl`.
    **`recipient_email`, `contact`, `provider_message_id` and `last_error` are
    not in the export at all.** Route name `internal.account.export`.
- Register `internal.account.export` **before** `internal.account.show` is
  irrelevant (different paths), but keep the export route next to the other
  account routes.

**Steps.**

1. Add `DeleteClientAccount`.
2. Add `DeleteAccountRequest` and `AccountController@destroy` + route.
3. Add `AccountExportController` + route.

**Done when.**

- `GET /internal/account/export` downloads valid JSON with the three sections
  and `grep -c recipient` on the file is 0.
- `DELETE /internal/account` with a wrong password → 422; with the right one
  the user row is gone, `storage/app/private/cvs/{id}` (or the configured
  local root) no longer exists, the Gmail row is gone, and the session is
  logged out.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** Any admin-side deletion changes (`AdminGuard` stays as
it is).

---

### Phase 15 — Initial Inertia props for Dashboard, Jobs and Applications

Status: PENDING
Role: laravel-backend · Depends on: 10, 11, 12 · Covers: AC05, AC09 · Size: M
Spec: B.6 (Initial props)

**Goal.** The three data-heavy screens render their first paint from props
instead of a client fetch, and later refreshes go through the same hooks.

**Contract.**

- **`routes/web.php`**: replace three `Route::inertia` entries with controller
  actions, keeping the **same route names and paths**:
    - `GET /dashboard` → `App\Http\Controllers\Client\DashboardPageController`,
      name `dashboard`, middleware `auth`, `client`. Props:
      `dashboard` (the `today` period payload) and `chart` (the `14d` payload) —
      the same arrays the internal endpoints return, built by reusing
      `DashboardPresenter` and `ChartPresenter`.
    - `GET /jobs` → `App\Http\Controllers\Client\JobsPageController`, name
      `jobs`. Prop `jobs`: the **first page** for the filters in the query
      string, validated by the same `JobFiltersRequest`, built by reusing the
      `JobsController@index` payload builder (extract that into a small shared
      method or a presenter so there is exactly one implementation).
    - `GET /applications` → `App\Http\Controllers\Client\ApplicationsPageController`,
      name `applications`. Prop `applications`: the first page for the query
      string's filters.
      `/onboarding`, `/profiles`, `/preferences`, `/plans`, `/account` stay
      prop-less `Route::inertia`.
- **Frontend**, reading the props as `initialData` through the existing
  `initialDataFrom` helper (foundation B.7 pattern already used by
  `useAccountStatus`):
    - `pages/dashboard.tsx` — `usePage<…>().props.dashboard` / `.chart` passed
      into `useDashboard(period, initial)` and `useChart(range, initial)`; the
      prop is only used when `period === 'today'` / `range === '14d'`.
    - `pages/jobs.tsx` — the `jobs` prop into `useJobs(filters, initial)`,
      honoured only when the current filters equal the ones the page was loaded
      with.
    - `pages/applications.tsx` — same shape for `useApplications`.
      Extend those three hooks with an optional `initial` argument
      (`useInfiniteQuery` takes `initialData: { pages: [initial], pageParams: [null] }`).
- Wayfinder route helpers keep the same names, so no import in
  `resources/js/routes` changes.
- No screen may end up fetching twice on first paint.

**Steps.**

1. Extract the jobs-page and applications-page payload builders so the
   controller and the internal endpoint share one implementation.
2. Add the three page controllers and swap the routes.
3. Add the optional `initial` argument to `useDashboard`, `useChart`,
   `useJobs`, `useApplications`.
4. Read the props in the three pages.

**Done when.**

- `/dashboard`, `/jobs?language=pt`, `/applications?status=sent` render with
  data on first paint and issue no duplicate XHR for the same key (check the
  network panel or `mcp__laravel-boost__browser-logs`).
- Changing period/range/filters still refetches through the hook.
- `composer lint:check`, `composer types:check`, `yarn run check`,
  `yarn run types:check` pass.

**Not in this phase.** Props for any other screen.

---

### Phase 16 — Gmail connect returns to Account or Onboarding

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: AC11 · Size: S
Spec: B.9

**Goal.** Connecting or reconnecting Gmail from the new UI comes back to the
page it started from, with a flash message; the legacy Filament link keeps
working.

**Contract.**

- **`ConnectedIntegrationOAuthController`**:
    - `connect()` and `reconnect()` read `?return=` and store the resolved
      target in the session under the key `integrations.return`:
      `private const RETURN_ROUTES = ['account' => 'account', 'onboarding' => 'onboarding'];`
      Anything not in that whitelist (including a missing value and the legacy
      `?redirect=<url>`) resolves to `account`. The raw query value is never
      used as a URL.
    - `returnUrl()` becomes
      `route(self::RETURN_ROUTES[$request->session()->pull('integrations.return', 'account')] ?? 'account')`
      — pull, so the target is consumed once; keep the existing docblock line
      that the target is never taken from user input verbatim.
    - The `callback()` success path adds
      `->with('success', __('account.gmail.connected_flash'))` and the
      `failed()` path `->with('error', __('account.gmail.failed_flash'))`, in
      addition to the existing Filament `Notification::make()` calls (which the
      legacy `/app` page still shows). `disconnect()` keeps its current
      behaviour plus `->with('success', __('account.gmail.disconnected'))`.
    - `OAuthConnectionStateManager::issue()` already receives a return URL —
      keep passing `$this->returnUrl($request)`; do not change the state
      manager's contract.
- **Frontend**: `resources/js/features/account/gmail-card.tsx` and
  `resources/js/features/onboarding/step-gmail.tsx` change their query from
  `{ redirect: account().url }` / `{ redirect: onboarding().url }` to
  `{ return: 'account' }` / `{ return: 'onboarding' }`. Disconnect keeps using
  the existing DELETE route through `useDisconnectGmail`.
- **i18n**: add `account.gmail.connected_flash`
  (`Gmail connected.` / `Gmail conectado.`) and
  `account.gmail.failed_flash`
  (`We could not connect Gmail. Please try again.` /
  `Não conseguimos conectar o Gmail. Tente novamente.`) to both JSON files.
  `account.gmail.disconnected` already exists.
- The flashes surface through the existing shared `flash` prop and
  `useFlashToasts`.

**Steps.**

1. Add the whitelist, session store and `returnUrl()` change.
2. Add the flashes to the callback, failure and disconnect paths.
3. Update the two frontend call sites.
4. Add the two lang keys to both JSON files.

**Done when.**

- Connecting from `/account` lands back on `/account` with a success toast;
  connecting from `/onboarding` lands back on `/onboarding`.
- `?return=https://evil.example` or `?return=admin` lands on `/account`.
- The Filament `/app` Preferences connect link still completes and lands on
  `/account` (acceptable until spec 6).
- `composer lint:check`, `composer types:check`, `yarn run check`,
  `yarn run types:check` pass.

**Not in this phase.** Removing the Filament `/app` page (out of scope).

---

### Phase 17 — Realtime events and their dispatch points

Status: PENDING
Role: laravel-backend · Depends on: 9, 11 · Covers: AC10 · Size: M
Spec: B.8 (events)

**Goal.** Four broadcast events whose names and payloads are exactly the
contract's, dispatched from the writes that cause them, never able to fail a
write.

**Contract.**

- All four are `final class`, `implements ShouldBroadcastNow`, in
  `App\Events\Client\`, with `broadcastAs()` returning the contract name and
  `broadcastWith()` building the payload with the **same resources** as the
  HTTP endpoints (no parallel serialisation):
    | Class                                                                        | Channel                                      | `broadcastAs`            | `broadcastWith`                                |
    | ---------------------------------------------------------------------------- | -------------------------------------------- | ------------------------ | ---------------------------------------------- |
    | `ApplicationProgressed`                                                      | `PrivateChannel('App.Models.User.'.$userId)` | `application.progressed` | `['application' => ApplicationItemResource]`   |
    | `AccountStatusUpdated`                                                       | `PrivateChannel('App.Models.User.'.$userId)` | `account.updated`        | `['status' => AccountStatusResource]`          |
    | `NotificationCreated`                                                        | `PrivateChannel('App.Models.User.'.$userId)` | `notification.created`   | `['notification' => NotificationResource]`     |
    | `JobsCollected`                                                              | `Channel('jobs')` (**public**)               | `jobs.collected`         | `['collectionRunId' => int, 'newJobs' => int]` |
    | `JobsCollected`'s payload is ids and counts only — never a title, company or |
    | URL.                                                                         |
- **Dispatching is best-effort.** Add
  `App\Events\Client\Concerns\DispatchesClientEvent` (or reuse the shape of
  `BroadcastsRealtime`): a static helper that wraps
  `event(...)` in `try { … } catch (BroadcastException $e) { report($e); }`.
  Every dispatch below goes through it.
- **`App\Models\Application::booted()`** — in the existing `saved` hook,
  **after** the current `broadcastUpdated()` call (keep the public admin
  broadcast untouched), dispatch `ApplicationProgressed` for
  `$application->user_id`. When the `status` attribute was among the changed
  ones (`$application->wasChanged('status')`), also dispatch
  `AccountStatusUpdated` (the quota moved).
- **`App\Models\ConnectedIntegration`** — add a `booted()` `saved` hook that
  dispatches `AccountStatusUpdated` for its `user_id` when `status` was
  changed. The model has no `booted()` today; add one and give it the same
  best-effort wrapper. Note that
  `ConnectedIntegrationTokenManager::markReauthorizationRequired` writes
  through the **query builder** (`->update([...])`), which skips model events,
  so that method must dispatch explicitly as well (same convention as the
  `project-core` skill's "explicitly after query-builder writes").
- **Debounce for `AccountStatusUpdated`:** at most one per user per second.
  Implement inside the helper: `Cache::lock("account-updated:{$userId}", 1)`
  — `get()` without blocking; when the lock is not acquired, skip the
  dispatch. Never `block()`, so a write is never slowed down.
- **`App\Actions\Collection\FinalizeCollectionRun::handle()`** — after the run
  is saved and its status is final, when `$run->jobs_new > 0`, dispatch
  `JobsCollected($run->id, $run->jobs_new)`. This happens regardless of
  whether `triggered_by` is set (the current early `return` for a null user
  must not skip it — move the dispatch above that `return`).
- **`/broadcasting/auth`** is already registered via
  `withRouting(channels: ...)` with `web` + `auth`; confirm and do not
  duplicate it. `routes/channels.php` already authorises
  `App.Models.User.{id}`; the `jobs` channel is public and needs no entry.
- Reverb must be running for a manual check (`php artisan reverb:start`, or
  `composer dev`).

**Steps.**

1. Add the dispatch helper with the cache-lock debounce.
2. Add the four event classes.
3. Hook `Application::booted()`.
4. Add `ConnectedIntegration::booted()` and the explicit dispatch in
   `ConnectedIntegrationTokenManager`.
5. Move/add the `JobsCollected` dispatch in `FinalizeCollectionRun`.

**Done when.**

- Saving an application with a changed status emits
  `application.progressed` and `account.updated` on
  `App.Models.User.{id}`, and saving it with the broadcaster down only
  reports the exception (the row is still saved).
- Two saves within the same second produce at most one `account.updated`.
- Finishing a collection run with `jobs_new > 0` emits `jobs.collected` on the
  public `jobs` channel with only `collectionRunId` and `newJobs`.
- `grep -rn "broadcastAs" app/Events/Client/` shows exactly the four contract
  names.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** `sending.updated` (spec 6), the client notification
classes (Phase 18), the frontend public-channel subscription (Phase 20).

---

### Phase 18 — Client database notifications

Status: PENDING
Role: laravel-backend · Depends on: 17 · Covers: AC11 · Size: S
Spec: B.8 (notifications bullet)

**Goal.** A forced Gmail reauthorization writes a client notification whose
`type` is the contract slug and pushes it live.

**Contract.**

- **`App\Notifications\Client\ClientNotification`** — `abstract class`
  extending `Illuminate\Notifications\Notification`:
    - `abstract public function notificationType(): string;` returning a
      `NotificationType` slug from the contract
      (`gmail_reauthorization_required`, `application_failed`,
      `daily_limit_reached`, `jobs_collected`, `sending_auto_paused`).
    - `abstract public function payload(): array;` →
      `array<string, string|int|null>`, client-safe (no URLs, no addresses).
    - `public function via(object $notifiable): array` → `['database']`.
    - `public function toDatabase(object $notifiable): array` →
      `['type' => $this->notificationType(), ...$this->payload()]` so
      `NotificationResource` (Phase 13) reads `data['type']` directly.
    - No `toMail` — these are in-app only.
- **`App\Notifications\Client\GmailReauthorizationRequired`** extends it:
  `notificationType()` → `gmail_reauthorization_required`;
  `payload()` → `['provider' => 'Gmail']` (the UI copy already lives in
  `lang/*.json` under `notifications.gmail_reauthorization_required`).
- **Dispatching the live event:** after `$user->notify(...)`, read back the
  newly created `DatabaseNotification` and dispatch
  `App\Events\Client\NotificationCreated` through the Phase 17 helper. Put
  that in one place — a `public static function send(User $user, ClientNotification $notification): void`
  on the base class — so every future client notification gets the live push
  for free.
- **`ConnectedIntegrationTokenManager::markReauthorizationRequired()`**: inside
  the existing `if ($transitioned)` branch, after the current Filament
  `sendToDatabase(...)` call (keep it — the legacy `/app` page reads it),
  send `GmailReauthorizationRequired` to `$integration->user`. Do not change
  the `throw` at the end of the method.
- The other four notification types are created by spec 6 — do not add them.

**Steps.**

1. Add `ClientNotification` with `send()`.
2. Add `GmailReauthorizationRequired`.
3. Send it from `markReauthorizationRequired()`.

**Done when.**

- Forcing a reauthorization (e.g. clearing the refresh token and calling
  `accessToken()`) creates one `notifications` row with
  `data->type = 'gmail_reauthorization_required'` and emits
  `notification.created` on the user's private channel.
- `AccountStatus.unreadNotifications` goes up by one and
  `GET /internal/notifications` returns the new item with the contract type.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** `application_failed`, `daily_limit_reached`,
`jobs_collected` and `sending_auto_paused` notifications (spec 6).

---

### Phase 19 — Frontend: per-hook fixture gate

Status: PENDING
Role: inertia-frontend · Depends on: none · Covers: AC09 · Size: M
Spec: B.6 (Per-hook fallback)

**Goal.** With `VITE_USE_FIXTURES=false`, only the hooks whose endpoint this
spec implements go to the server; every other hook keeps its fixture.

**Contract.**

- **`resources/js/data/source.ts`** — `real` becomes optional, which is
  exactly the spec's rule ("uses its real function when
  `VITE_USE_FIXTURES !== 'true'` **and** the real function exists"):
    ```ts
    export function fromSource<F>(pair: { real?: F; fixture: F }): F {
        return useFixtures || pair.real === undefined
            ? pair.fixture
            : pair.real;
    }
    ```
    Keep the existing comment about only `data/` importing the flag, and add one
    line: a hook whose endpoint is not implemented yet simply omits `real`.
- **Hooks that drop their `real` function** (and the now-unused
  `endpoints`/`apiFetch` imports) because their endpoints are out of scope for
  this spec — 11 one-line-ish edits, nothing else changes:
  `use-live-sending.ts`, `use-pause-sending.ts`, `use-queue-applications.ts`,
  `use-review-drafts.ts`, `use-queue-reviewed.ts`, `use-preferences.ts`,
  `use-preferences-preview.ts`, `use-profiles.ts`, `use-cv.ts`,
  `use-template-preview.ts`, `use-plans.ts`.
  _If the owner chose D1 option B, also `use-applications.ts`'s
  `useApplicationCounts`._
- **Hooks that keep their `real`** (endpoints implemented in Phases 9–14):
  `use-account-status.ts`, `use-account.ts`, `use-dashboard.ts`,
  `use-chart.ts`, `use-jobs.ts`, `use-job.ts`, `use-applications.ts`,
  `use-application.ts`, `use-notifications.ts`, `use-onboarding.ts`,
  `use-connect-gmail.ts`, `use-set-locale.ts`.
- Do not touch `use-default-templates.ts`, `use-preference-options.ts` or
  `use-fixture-plan.ts` — they never call an endpoint.
- The `endpoints.ts` entries for the dropped endpoints stay in place; later
  specs re-attach them.

**Steps.**

1. Change `fromSource`'s signature in `source.ts`.
2. Drop `real` (and its imports) from the 11 hooks.

**Done when.**

- `grep -rn "fromSource({ real" resources/js/data/hooks/ | wc -l` equals the
  12 implemented hooks (13 with `useApplicationCounts`).
- `yarn run check` and `yarn run types:check` pass with no unused-import warnings.
- With `VITE_USE_FIXTURES=false` the app still renders every screen: the
  Profiles, Preferences and Plans screens show fixture data, the rest hit the
  server.

**Not in this phase.** Wayfinder helpers in `endpoints.ts` (Phase 20).

---

### Phase 20 — Frontend: Wayfinder endpoints and the public `jobs` channel

Status: PENDING
Role: inertia-frontend · Depends on: 15, 17, 19 · Covers: AC09, AC10 · Size: M
Spec: B.6 (Wayfinder), B.8 (frontend bullet)

**Goal.** Implemented endpoints are addressed through generated Wayfinder
helpers instead of hand-written strings, and the client also listens on the
public `jobs` channel.

**Contract.**

- Regenerate Wayfinder output first (`yarn dev` once, or `yarn build`) so
  `resources/js/actions/App/Http/Controllers/Client/Internal/*` and the
  `resources/js/routes/internal/*` modules exist.
- **`resources/js/data/endpoints.ts`** — replace the body of every entry whose
  route now exists with the Wayfinder helper, keeping the exported
  `Endpoint = { url, method }` shape and the exported function signatures
  unchanged so no hook has to change:
  `accountStatus`, `dashboard`, `chart`, `jobs`, `job`, `applications`,
  `applicationCounts` (D1 option A), `application`, `notifications`,
  `markAllNotificationsRead`, `account`, `saveAccount`, `changePassword`,
  `deleteAccount`, `saveOnboardingBasics`, `accountExport`.
  Query parameters keep going through the existing `withQuery`/`omitDefault`
  helpers where Wayfinder does not model them; the **path and method** come
  from the helper. Leave the remaining entries (`sending`, `pauseSending`,
  `resumeSending`, `queueApplications`, `reviewDrafts`, `queueReviewed`,
  `preferences`, `savePreferences`, `preferencesPreview`, `profiles`,
  `createProfile`, `saveProfile`, `deleteProfile`, `uploadCv`, `deleteCv`,
  `templatePreview`, `plans`) as the placeholders they are, with the existing
  comment updated to say which spec will replace them.
- **`resources/js/data/realtime/use-user-channel.ts`** — in the real
  transport, in addition to `echo().private('App.Models.User.'+userId)`,
  subscribe to `echo().channel('jobs')` and route `jobs.collected` to the same
  handler map. Keep the fixture transport untouched (the dev emitter already
  delivers `jobs.collected`). Both subscriptions must be torn down in the
  effect's cleanup, and the hook must keep choosing its transport once per
  build so hook order never changes at runtime.
- **`resources/js/data/realtime/use-realtime-cache.ts`** — no change is
  expected; update its comment to say the public channel is now wired.
- Echo must be configured (`VITE_REVERB_*` are already in `.env.example`);
  `echoIsConfigured()` already guards the real transport.

**Steps.**

1. Regenerate Wayfinder output.
2. Rewrite the implemented entries in `endpoints.ts`.
3. Add the public `jobs` subscription to `use-user-channel.ts`.
4. Refresh the comment in `use-realtime-cache.ts`.

**Done when.**

- `grep -n "'/internal" resources/js/data/endpoints.ts` only matches the
  still-unimplemented placeholders.
- With Reverb running and `VITE_USE_FIXTURES=false`, an application status
  change in one window updates the Applications list, the Dashboard and the
  account quota in a second window with no reload, and a finished collection
  run with new jobs refreshes Jobs and Dashboard.
- `yarn run check` and `yarn run types:check` pass.

**Not in this phase.** Any change to the handler functions in
`realtime/handlers.ts` (they already match the contract).

---

### Phase 21 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–20 · Covers: AC05, AC06, AC09 · Size: S
Spec: Acceptance criteria, Verification

**Goal.** Run the full gate, prove every acceptance criterion, and hand the
owner a short manual checklist.

**Contract.**

- **Deterministic gate**, in this order:
  `composer lint:check`, `composer types:check`, `yarn run check`,
  `yarn run types:check`, `php artisan test --compact` (only the two example
  tests exist — no new tests are written). Use `yarn run check`, never
  `yarn check` — Yarn Classic shadows the latter with its own integrity check
  and reports success without running anything.
- **Known pre-existing failure:** `yarn run check` flags formatting in
  `resources/js/components/patterns/{page-header,top-bar}.tsx`,
  `resources/js/components/ui/menu.tsx` and
  `resources/js/data/fixtures/state.ts`. They already failed before Phase 1
  started (`page-header.tsx`, `menu.tsx` and `state.ts` as committed;
  `top-bar.tsx` through the owner's own uncommitted edit) and are out of scope.
  Report them as a pre-existing baseline, separately from anything this feature
  introduced, and do not fix them.
- **Forbidden-pattern greps**, each expected to return nothing:
    - `grep -rn "DAILY_SEND_LIMIT" app/ resources/` (AC07)
    - `grep -rnE "'(url|apply_url|company_website|domain|recipient_email|raw|description_html)'" app/Http/Resources/Client/` (AC06)
    - `grep -rn "poll()\|wire:poll\|pollingInterval" app/Filament/` (CLAUDE.md)
    - `grep -rn "last_error" app/Http/Resources/Client/` — only via
      `ClientErrorMessage` (AC06)
    - `grep -rn "lang/es" .` and `ls lang | grep es` (no Spanish locale)
- **Contract key comparison (AC05):** for each implemented endpoint, call it
  as the seeded client and diff the response's key set against the matching
  type in `resources/js/types/contracts.ts`. Record the result per endpoint in
  a table: `/internal/account/status`, `/internal/dashboard`,
  `/internal/dashboard/chart`, `/internal/jobs`, `/internal/jobs/{id}`,
  `/internal/applications`, `/internal/applications/counts`,
  `/internal/applications/{id}`, `/internal/notifications`,
  `/internal/account`, `/internal/account/export`,
  `/internal/onboarding/basics`.
- **AC walkthrough:** one line per AC01–AC12 stating how it was verified and
  the outcome. Anything not verifiable without the owner (two browser
  windows, a real Gmail account, a real Resend key) is listed as an owner
  check, not claimed as passing.
- **Owner manual checklist** (for the report, in this order):
    1. `php artisan migrate:fresh --seed` (owner only — the executor never runs
       it), then `composer dev` with Reverb up.
    2. `/admin/invitations` → create an invitation, copy the link, open it in a
       private window, register, land on `/onboarding`.
    3. Reopen the same link → Closed. Revoke another one → Closed.
    4. `/forgot-password` with a seeded address → check
       `storage/logs/laravel.log` for the branded, localized mail; complete the
       reset.
    5. Set `VITE_USE_FIXTURES=false`, `yarn dev`, walk Dashboard → Jobs (list +
       detail) → Applications (list + detail) → Account → bell → Onboarding
       basics.
    6. Two windows: change an application's status from `/admin/applications`
       and watch the other window update; run a collection that produces new
       jobs and watch Jobs refresh.
    7. Connect/reconnect Gmail from `/account` and from `/onboarding`; force a
       reauthorization and watch the notification arrive live.
    8. Download the data export, delete a throwaway account.
    9. Optional: run `php artisan postings:extract-profiles --missing-language`
       on a kept database.
- The report also records: the new dependency (`resend/resend-php`), the
  `.env` keys the owner must add, and the `--missing-language` command.

**Steps.**

1. Run the gate and record every command's exact output status.
2. Run the greps.
3. Call every implemented endpoint and diff key sets against `contracts.ts`.
4. Write the AC walkthrough and the owner checklist into the phase report.
5. Update this file's status board.

**Done when.**

- Every gate command passes, or each failure is reported with its output and
  the phase that owns it.
- Every grep returns nothing.
- All twelve endpoints match their contract key sets, or the mismatches are
  listed with the phase to fix.
- AC01–AC12 each have a verdict (verified / owner check), with nothing marked
  passing on the strength of reading the code alone.

**Not in this phase.** Fixing what it finds — report and let the owner pick
the phase to re-run.
