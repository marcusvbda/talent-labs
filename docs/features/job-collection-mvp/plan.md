# Plan — talent-labs bootstrap (job collection MVP)

Source spec: `docs/features/job-collection-mvp/spec.md` · SHA-256 `d688ebdc5ad6b8fdf1d8fb432e364d8a81f8d3037e6e6b52150b22d8cc7b00f7`
The full bootstrap spec (Parts 0, A, B) is the single spec file. Part B is the
product truth.
Run phases with `/execute-phases docs/features/job-collection-mvp/plan.md <phases>`.
Run one or a few phases per session. Phase status is updated in place in this
file.

## Status board

| Phase | Title                                              | Role                              | Depends on       | Size | Status       |
| ----- | -------------------------------------------------- | --------------------------------- | ---------------- | ---- | ------------ |
| 0     | Audit + Claude harness (Part 0.2 + Part A)         | orchestrator                      | none             | M    | DONE         |
| 1     | Infra: notifications json, dev processes, env docs | laravel-backend                   | 0                | S    | PENDING      |
| 2     | User access model + UserSeeder                     | laravel-backend                   | 1                | S    | PENDING      |
| 3     | Users admin resource + admin guards                | filament-admin                    | 2                | M    | PENDING      |
| 4     | Sources data model + SourceSeeder                  | laravel-backend                   | 2                | S    | PENDING      |
| 5     | Collection data model (runs, source runs, posts)   | laravel-backend                   | 4                | M    | PENDING      |
| 6     | Sources admin resource                             | filament-admin                    | 5                | M    | PENDING      |
| 7     | Adapter contract + Greenhouse, Lever, Ashby        | laravel-backend                   | 4                | M    | PENDING      |
| 8     | Remotive adapter + adapter resolution              | laravel-backend                   | 7                | S    | PENDING      |
| 9     | Runs admin resource (read-only, realtime)          | filament-admin                    | 5                | M    | PENDING      |
| 10    | Collection flow (start, fetch, finalize, fail)     | laravel-backend                   | 1, 8, 9          | M    | PENDING      |
| 11    | Runs actions: Collect jobs now, Mark as failed     | filament-admin                    | 10               | S    | PENDING      |
| 12    | Job postings admin resource                        | filament-admin                    | 9                | M    | PENDING      |
| 13    | User-app auth: Fortify login, active-user guard    | laravel-backend, inertia-frontend | 2, D1            | M    | BLOCKED (D1) |
| 14    | User `/dashboard` — Today's jobs                   | laravel-backend, inertia-frontend | 5, 8, 13         | M    | PENDING      |
| 15    | Final verification, smoke test, report            | orchestrator, qa-tester           | 1–14             | M    | PENDING      |

Parallel-safe once their dependencies are done: Phase 3 can run next to
4–12, and Phase 13 can run any time after Phase 2 once D1 is answered.

## Audit — 2026-09-22 (Part 0.2)

| Check                                                   | Result                                                                                                                                                                                                                       |
| ------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel 13.x                                            | ✅ 13.33.0 (PHP 8.4)                                                                                                                                                                                                         |
| Filament 5.x + admin panel provider                     | ✅ 5.8.4, `app/Providers/Filament/AdminPanelProvider.php` (`/admin`, `->login()`)                                                                                                                                             |
| Inertia + React                                         | ⚠️ Inertia 3.3.4 + `@inertiajs/react` 3 are installed, but **it's not the starter kit**: `resources/js/pages` only has `welcome.tsx`. There are no auth pages, layout, sidebar, dashboard or settings pages.                  |
| Fortify                                                 | ❌ **Not installed** (no package, no `config/fortify.php`, no auth routes). The spec says stop → **D1**.                                                                                                                      |
| Reverb, `BROADCAST_CONNECTION=reverb`                   | ✅ 1.12.0, `.env` = reverb (`.env.example` still says `log`, fixed in Phase 1)                                                                                                                                               |
| realtime driver + `->socket()`                          | ✅ 0.1.2, `FilamentRealtimeDriverPlugin::make()->socket()->databaseNotifications()` and `$panel->databaseNotifications()` are already set. No backend listener callback, so `filament-realtime-driver:listen` is skipped.       |
| DB                                                      | ✅ PostgreSQL reachable (`talent-labs`). ⚠️ `.env` defines `DB_CONNECTION` twice (`sqlite`, then `pgsql`). pgsql is effective; the owner may want to remove the stray line (agents never edit existing `.env` values).        |
| `QUEUE_CONNECTION`                                      | ✅ `database`                                                                                                                                                                                                                 |
| `notifications`, `jobs`, `job_batches`, `failed_jobs`   | ✅ all exist (`notifications` was created earlier via `make:notifications-table`)                                                                                                                                             |
| `notifications.data` type                               | ❌ `text`. Filament queries `data->>'format'`, so it must be `json` → Phase 1                                                                                                                                                 |
| `php artisan dev` + `DevCommands`                       | ✅ available. `app/Console/Commands/DevCommand.php` overrides `dev` to use local `concurrently`, because Yarn Classic has no `dlx` for `@laravel/multiplex`. It still reads every `DevCommands` registration.                 |
| Tooling                                                 | Pint (`composer lint`, `lint:check`, `pint --dirty --format agent`), PHPStan/Larastan level 7 (`composer types:check`), Pest (`composer test`), Vite+ `yarn check` (fmt + lint + types), `yarn types:check` (tsc), `composer ci:check`. No standalone ESLint/Prettier. |
| App timezone                                            | `config/app.php` hardcodes `'timezone' => 'UTC'`, so "today" is a UTC day → **D2**                                                                                                                                         |
| `SEED_*` keys                                           | Present in `.env` (owner values) and in `.env.example` (owner placeholders `xxxx`) → **D3**                                                                                                                                  |
| `DatabaseSeeder`                                        | Default stub: `User::factory()->create(['email' => 'test@example.com'])` under `WithoutModelEvents`. Not idempotent; replaced in Phase 2.                                                                                   |
| Existing users                                          | 1 (the owner's Filament user). The Phase 2 backfill makes them an admin.                                                                                                                                                     |

## Owner decisions

### D1 — Auth for the Inertia user app (Fortify is missing)

Blocks: Phase 13 (and therefore 14, plus the `/login` parts of AC01, AC02 and AC05).
The spec assumes the Laravel 13 React starter kit (Fortify, login page,
layout/sidebar, settings pages). None of it exists, and rule 0.1.3 forbids
installing dependencies without asking.

- **A (recommended):** approve `composer require laravel/fortify`. Phase 13
  builds a minimal auth UI: login page, logout, a simple authenticated layout
  and a `/dashboard` shell. All Fortify features are disabled (no
  registration, password reset, email verification, 2FA, or profile/password
  pages). This matches the spec and recruiter-labs.
- **B:** no new dependency. Hand-roll session login (login controller plus a
  rate-limited `LoginRequest`, logout) with the same UI. Fewer moving parts,
  but it diverges from the spec's "Fortify".
- **C:** the owner scaffolds auth manually (for example, copying the
  starter-kit auth from recruiter-labs), then Phase 13 only adapts it.

Settings pages (B.9 "leave as they are"): they don't exist, so nothing is built
under any option.

### D2 — Which timezone defines "today"?

Needed before Phase 5 (run label) and Phase 14 (today's jobs). **Default if
unanswered: keep UTC** (the current app timezone, as the spec says). The
alternative is `'timezone' => env('APP_TIMEZONE', 'UTC')` plus
`APP_TIMEZONE=America/Sao_Paulo`. Otherwise, a run started at 22:00 in Brazil
counts as the next day.

### D3 — `.env.example` seed values

The spec lists `admin@talent-labs.test` / `password` and so on. The owner's
`.env.example` has placeholders (`xxxx`). **Default if unanswered: keep the
owner's placeholders** (the keys are already documented) and add only the
`# Local seed users (db:seed)` comment. Never touch `.env` values.

## Global constraints (every phase)

- **No git writes.** Never commit, stage, push, branch, stash, reset or
  restore. `.claude/settings.json` denies most of these and asks before
  add/commit/push. Finishing a phase is not a commit request.
- No new/removed/upgraded packages (the only exception is `laravel/fortify`
  in Phase 13, and only after D1 = A). Use `yarn`, never `npm install`.
- Forward `php artisan migrate` and `php artisan db:seed` only. Never
  fresh/refresh/reset/rollback/wipe/DROP/TRUNCATE. Seeders stay idempotent.
- No tests written or modified. Running existing ones is fine.
- English everywhere. `env()` only inside `config/`.
- No `->poll()`, `wire:poll` or `$pollingInterval`. Use the realtime driver
  (`project-core` skill).
- Public realtime channels carry ids only.
- Model/table for postings is `JobPosting` / `job_postings`, never `Job`.
- Scope is Part B only. Out of scope (B.12): email, contact discovery,
  matching, profiles/filters on the user app, AI extraction, scheduling,
  user-app realtime, registration, billing, scraping, widgets, tests, deploy.
- Filament 5 APIs: use the Boost `filament-development` skill and `search-docs`,
  and generate with `php artisan make:filament-resource` (Filament 5 layout:
  `app/Filament/Resources/<Plural>/{<Name>Resource.php, Pages/, Schemas/, Tables/}`).
- Each phase ends with Pint on dirty files plus `composer types:check`, and,
  when frontend changed, `yarn check` plus `yarn types:check`.

## Acceptance-criteria coverage

| AC   | Phases                     |
| ---- | -------------------------- |
| AC01 | 3, 13, 15                  |
| AC02 | 2, 13, 15                  |
| AC03 | 3                          |
| AC04 | 4, 6                       |
| AC05 | 1, 2, 4, 13, 15            |
| AC06 | 10, 11                     |
| AC07 | 5, 7, 8, 10, 15            |
| AC08 | 10, 15                     |
| AC09 | 1, 10                      |
| AC10 | 4, 5, 9, 10, 12, 15        |
| AC11 | 12                         |
| AC12 | 10, 11                     |
| AC13 | 14                         |
| AC14 | 1, 15                      |
| AC15 | 0, 15                      |

## Phases

### Phase 0 — Audit + Claude harness

Status: DONE
Role: orchestrator · Depends on: none · Covers: AC15 · Size: M
Spec: 0.2, Part A

Evidence: the audit is recorded above. Created or updated: `CLAUDE.md`
(replaces the Boost guidelines dump), `.claude/settings.json` (deny list for
destructive git and DB commands; `ask` for `git add/commit/push`, per the
owner's instruction "never commit unless I explicitly ask"),
`enabledMcpjsonServers`, `.mcp.json` (laravel-boost, context7, playwright), and
`boost.json` (`guidelines: false`, `packages: [filament/filament]`, stack
skills; `boost:update` synced `filament-development`). Also created: agents
`laravel-backend`, `filament-admin`, `inertia-frontend`, `code-reviewer`,
`qa-tester` (each ends with the no-git-writes line); skills `project-core`,
`execute-feature`, `job-collection`; commands `/plan-spec` and
`/execute-phases`; and `docs/features/README.md`. On the owner's request, all
docs are centralized in `docs/features/job-collection-mvp/`. The original
spec moved there as `spec.md`, replacing the separate Part-B-only copy that
A.7 asks for, so no content is duplicated. `docs/specs/` was removed.
In `.gitignore`: `/.claude/state/`, `/.claude/settings.local.json` and
`.playwright-mcp` were added, and `/.mcp.json` and `/boost.json` are un-ignored
so the harness can be committed. Nothing was committed.

Deviation to note for AC15: git `add`/`commit`/`push` are `ask` rules, not
`deny`. The owner asked that commits happen only on explicit request, which a
hard deny would make impossible. To enforce the spec literally, move those
six rules from `ask` to `deny`.

---

### Phase 1 — Infra: notifications json, dev processes, env docs

Status: PENDING
Role: laravel-backend · Depends on: 0 · Covers: AC05 (config), AC09 (prereq), AC14 · Size: S
Spec: 0.2 (PostgreSQL note), B.4.1, B.6

**Goal.** Make database notifications work on PostgreSQL, make `composer dev`
run Reverb (debug) and a `collection` queue worker, and document the env keys.

**Contract.**
- Forward migration `change_notifications_data_to_json`:
  `up()` → `$table->json('data')->change();` (PostgreSQL needs
  `USING data::json`; check with `php artisan migrate --pretend` and, if the
  grammar doesn't emit `USING`, use
  `DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json')`).
  `down()` → back to `text`.
- `config/talent.php`:
  `['seed' => ['admin' => ['name' => env('SEED_ADMIN_NAME'), 'email' => env('SEED_ADMIN_EMAIL'), 'password' => env('SEED_ADMIN_PASSWORD')], 'client' => [... SEED_CLIENT_*]]]`.
- `AppServiceProvider::boot()`, inside `if ($this->app->runningInConsole())`:
  `DevCommands::artisan('reverb:start --debug', 'reverb');`
  `DevCommands::artisan('queue:work database --queue=collection,default', 'queue');`
  (userland priority replaces the default `queue` (`queue:listen`) and Reverb's own
  `reverb`). Don't register server/vite/logs (defaults), `schedule:work`, or
  `filament-realtime-driver:listen` (no callback is configured). Keep
  `app/Console/Commands/DevCommand.php` as is.
- `.env.example`: `BROADCAST_CONNECTION=reverb`, keep `QUEUE_CONNECTION=database`,
  keep `REVERB_*` / `VITE_REVERB_*`, add `FILAMENT_REALTIME_SERVER=localhost:8080`,
  and put `# Local seed users (db:seed)` above the `SEED_*` block (values per D3).
- `.env`: append `FILAMENT_REALTIME_SERVER=localhost:8080` **only if missing**.
  Change nothing else.

**Steps.**
1. Migration, then `php artisan migrate`.
2. `config/talent.php`, AppServiceProvider registrations.
3. Env files, then `php artisan config:clear`.

**Done when.**
- `Schema::getColumns('notifications')` reports `data` as `json`.
- `/admin` loads with the notifications bell and no SQL error.
- `php artisan dev` (run for ~8s, then stop) lists
  `[reverb] php artisan reverb:start --debug` and
  `[queue] php artisan queue:work database --queue=collection,default`, and
  no `queue:listen`.
- `config('talent.seed.admin.email')` resolves.
- `vendor/bin/pint --dirty --format agent` and `composer types:check` pass.

**Not in this phase.** Users columns, seeders.

---

### Phase 2 — User access model + UserSeeder

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: AC02 (admin side), AC05 (users) · Size: S
Spec: B.2, B.3 users, B.4.1

**Goal.** Users have `is_admin` and `status`. Only active admins can enter
`/admin`. The seed users are idempotent.

**Contract.**
- `App\Enums\UserStatus: string` with cases `Active = 'active'`,
  `Blocked = 'blocked'`, implementing Filament `HasLabel` + `HasColor`
  (active → success, blocked → danger).
- Migration `add_access_columns_to_users_table`:
  `is_admin` boolean default false, `status` string default `active`. In
  `up()`, backfill **all existing rows** to `is_admin = true, status = 'active'`.
  `down()` drops both columns.
- `User`: implements `Filament\Models\Contracts\FilamentUser`. Add
  `is_admin` and `status` to `#[Fillable]`. Casts: `is_admin` → boolean,
  `status` → `UserStatus`. `canAccessPanel(Panel $panel): bool` returns
  `$this->is_admin && $this->status === UserStatus::Active`. Add an
  `isActive(): bool` helper. Update the `@property` docblock.
- `UserSeeder` (B.4.1, exact rules): return with a warning if
  `app()->isProduction()`. For each of `admin` (`is_admin = true`) and
  `client` (`is_admin = false`) from `config('talent.seed.<key>')`: if the
  email or password is empty, `$this->command->warn(...)` and skip.
  Otherwise, `User::updateOrCreate(['email' => ...], ['name', 'password' => Hash::make(...), 'is_admin', 'status' => Active, 'email_verified_at' => now()])`.
- `DatabaseSeeder::run()` → `$this->call([UserSeeder::class]);`. Remove the
  factory stub and the unused imports. `SourceSeeder` is added in Phase 4.

**Done when.**
- `php artisan migrate` has been run. The owner's existing user has
  `is_admin = true`.
- `php artisan db:seed` has been run twice with an unchanged `users` count
  after the second run.
- In tinker, `Auth::attempt([...admin creds from config])` and the client
  equivalent both return true (then `Auth::logout()`). Admin
  `canAccessPanel` is true; client is false.
- Pint and PHPStan pass.

**Not in this phase.** Users resource, user-app login.

---

### Phase 3 — Users admin resource + admin guards

Status: PENDING
Role: filament-admin (guard class: laravel-backend style, same phase) · Depends on: 2 · Covers: AC01 (admin-only creation), AC03 · Size: M
Spec: B.8 Users, B.2

**Goal.** Admins manage users. Self-harm and removing the last admin are
impossible, both in the UI and on the server.

**Contract.**
- `App\Actions\Users\AdminGuard::violation(User $actor, User $target, string $change): ?string`,
  where `$change` is `'block'`, `'demote'` or `'delete'`. It returns a
  user-facing reason or null.
  - Self: "You can't block yourself." / "You can't remove your own admin access." / "You can't delete yourself."
  - Last active admin (target is an active admin and there are no other
    active admins): "The last active admin can't be blocked." / "... can't lose admin access." / "... can't be deleted."
- `UserResource`, navigation group **Access**.
  - Table: name, email, `is_admin` (icon), status (badge), created at.
    `SelectFilter` on status.
  - Form: name (required), email (required, email, unique ignoring the
    record), password (`required` on create, optional on edit, hashed via the
    cast, dehydrated only when filled), `is_admin` toggle, status select
    (default `active`).
  - Row actions: Block / Unblock (`requiresConfirmation()`, visible by current
    status, disabled with a tooltip when the guard returns a reason), Edit,
    Delete (disabled with a tooltip when the guard returns a reason).
  - Server-side enforcement: Block/Delete actions re-check the guard and send
    a danger notification when it fails. `EditUser` blocks a save when it
    would demote or block against the guard (validation error on the field).
- No Filament registration anywhere (the panel has `->login()` only, which is
  correct).

**Done when.**
- `/admin/users` loads for the admin (no errors in the Boost `last-error`
  tool). Create, edit, block and unblock work.
- In tinker: the guard returns the right reason for self and last-admin
  cases, and null for a normal target.
- Pint and PHPStan pass. `grep -rn "poll" app/Filament` returns nothing.

**Not in this phase.** User-app logout of blocked users (Phase 13).

---

### Phase 4 — Sources data model + SourceSeeder

Status: PENDING
Role: laravel-backend · Depends on: 2 · Covers: AC04 (data), AC05 (sources), AC10 (sources channel) · Size: S
Spec: B.3 sources, B.4 seeder, B.7

**Contract.**
- `App\Enums\SourceAdapter: string`: `Greenhouse = 'greenhouse'`,
  `Lever = 'lever'`, `Ashby = 'ashby'`, `Remotive = 'remotive'`. Implements
  `HasLabel` (Greenhouse / Lever / Ashby / Remotive) and `HasColor`.
  `requiresIdentifier(): bool` (false only for Remotive).
  `sourceLabel(): string` ("via Remotive" for Remotive, the label otherwise).
  The adapter class resolution is added in Phase 8.
- `App\Enums\SourceRunStatus: string`: pending, running, completed, failed,
  with `HasLabel` and `HasColor` (gray, info, success, danger).
- Migration `sources`: exactly the B.3 table. `unique(['adapter', 'identifier'])`.
- `Source` model: casts `adapter` → SourceAdapter, `settings` → array,
  `is_active` → bool, `interval_minutes` → int, `last_run_at` → datetime,
  `last_run_status` → SourceRunStatus. In `booted()`: `saved` and `deleted` →
  `RealtimeEvent::dispatch('sources', 'SourceUpdated', ['id' => $source->id])`.
  Scope `active()`.
- `SourceSeeder`: `updateOrCreate(['adapter' => ..., 'identifier' => ...], [...])`
  for Stripe/greenhouse/`stripe`, Palantir/lever/`palantir`,
  Linear/ashby/`linear`, and Remotive/remotive/null with settings `{"limit": 100}`.
  All `is_active = true`.
- `DatabaseSeeder` calls `[UserSeeder::class, SourceSeeder::class]` in that order.

**Done when.**
- `migrate` has been run. `db:seed` run twice gives `Source::count() === 4`
  (the Remotive null identifier isn't duplicated).
- Pint and PHPStan pass.

---

### Phase 5 — Collection data model

Status: PENDING
Role: laravel-backend · Depends on: 4 · Covers: AC07 (dedup schema), AC10 (run channels) · Size: M
Spec: B.3 collection_runs / source_runs / job_postings, B.7

**Contract.**
- `App\Enums\CollectionRunStatus: string`: pending, running, completed,
  partial, failed, with `HasLabel` and `HasColor` (gray, info, success,
  warning, danger). `isInProgress(): bool` returns true for pending and running.
- Migrations, in this order: `collection_runs`, `source_runs`, `job_postings`.
  Columns, FKs (`nullOnDelete`, `cascadeOnDelete`, `restrictOnDelete`) and
  uniques/indexes exactly as in B.3: index `collection_runs.started_at`;
  unique `(collection_run_id, source_id)`; unique `(source_id, external_id)`;
  indexes `job_postings.collection_run_id` and `published_at`;
  `last_seen_run_id` nullable `nullOnDelete`.
- `CollectionRun`: casts (status enum, datetimes, ints). Relations
  `triggeredBy()` (User), `sourceRuns()`, `jobPostings()` (by
  `collection_run_id`). Accessor `label`: `"Run #{id} · " . started_at->timezone(config('app.timezone'))->format('j M Y H:i')`,
  or `"Run #{id}"` when `started_at` is null. Scope `startedToday()`: between
  `now()->startOfDay()` and `now()->endOfDay()` in the app timezone.
  `scopeInProgress()`. In `booted()`, `saved`/`deleted` → dispatch
  `('collection_runs', 'CollectionRunUpdated', ['id'])` and
  `('collection_run_'.$id, 'CollectionRunUpdated', ['id'])`.
- `SourceRun`: casts, relations `collectionRun()` and `source()`. `saved` →
  dispatch `('collection_run_'.$collection_run_id, 'CollectionRunUpdated', ['id' => $collection_run_id])`.
- `JobPosting`: casts (`is_remote` → bool, `raw` → array, datetimes).
  Relations `source()`, `collectionRun()`, `lastSeenRun()`. No model broadcast
  (dispatched explicitly per source run in Phase 10).
- `Source`: add `sourceRuns()`, `jobPostings()` and `hasHistory(): bool`.

**Done when.**
- `migrate` has been run. The Boost `database-schema` output matches B.3
  (types, FKs, uniques, indexes).
- Tinker: `(new CollectionRun(['started_at' => now()]))->forceFill(['id' => 1])->label`
  prints `Run #1 · <date>`. `php artisan model:show CollectionRun` is OK.
- Pint and PHPStan pass.

---

### Phase 6 — Sources admin resource

Status: PENDING
Role: filament-admin · Depends on: 5 · Covers: AC04 · Size: M
Spec: B.8 Sources

**Contract.**
- `SourceResource`, navigation group **Collection**.
- Table: name, adapter (badge), identifier, `is_active` (`ToggleColumn`),
  `interval_minutes`, `last_run_at`, `last_run_status` (badge). Filters:
  adapter (select), active (ternary). Add
  `->socket(channel: 'sources', event: 'SourceUpdated')`.
- Form: name (required); adapter (select, `live()`); identifier
  (`required` and `visible` unless adapter = remotive, dehydrated as `null`
  for remotive; unique per adapter ignoring the record); settings
  (`KeyValue`, visible only for remotive, keys `category` / `search` /
  `limit`); `interval_minutes` (numeric, min 1, default 60, helper text
  **"Stored for future automatic collection; not used yet"**); `is_active`
  toggle.
- Delete (row and bulk) only when `! $source->hasHistory()`. Otherwise it's
  disabled with the tooltip "This source has collection history — deactivate
  it instead." Bulk delete skips sources with history and notifies.

**Done when.**
- Create, edit and toggle work for all four adapters. Remotive saves
  `identifier = null`. A greenhouse source without an identifier fails
  validation.
- Pint and PHPStan pass. No `poll`.

---

### Phase 7 — Adapter contract + Greenhouse, Lever, Ashby

Status: PENDING
Role: laravel-backend · Depends on: 4 · Covers: AC07 · Size: M
Spec: B.4

**Contract.**
- `App\Collection\Contracts\JobSourceAdapter`:
  `/** @return iterable<JobPostingData> */ public function fetch(Source $source): iterable;`
- `App\Collection\Data\JobPostingData`: `final readonly class`, constructor
  properties `externalId` (string), `title`, `companyName`, `?location`,
  `?isRemote`, `?department`, `?employmentType`, `url`, `?applyUrl`,
  `?descriptionHtml`, `?descriptionText`, `?CarbonImmutable publishedAt`,
  `array raw`.
- A shared HTTP client helper (one small trait or base class):
  `Http::timeout(20)->retry(2, 500)->acceptJson()->withUserAgent('talent-labs/0.1 (local)')->throw()`.
- `GreenhouseAdapter`: `GET https://boards-api.greenhouse.io/v1/boards/{identifier}/jobs?content=true` → `jobs[]`.
  Mapping: `id` → externalId (string), `title`, `company_name ?? source.name`,
  `location.name`, `departments[0].name`, `absolute_url` → url and applyUrl,
  `html_entity_decode(content)` → html, `strip_tags(html)` (whitespace
  normalized) → text, `first_published ?? updated_at` → publishedAt,
  item → raw.
- `LeverAdapter`: `GET https://api.lever.co/v0/postings/{identifier}?mode=json`
  → **top-level array**. Mapping: `id`, `text` → title, company = source name,
  `categories.location`, `categories.team` → department,
  `categories.commitment` → employmentType, `workplaceType === 'remote'` →
  isRemote (null when absent), `hostedUrl` → url, `applyUrl`. html =
  `description` + each `lists[]` as `<h3>{text}</h3>{content}` + `additional`.
  text = `descriptionPlain` + `additionalPlain`. `createdAt` is **Unix ms** →
  `CarbonImmutable::createFromTimestampMs`.
- `AshbyAdapter`: `GET https://api.ashbyhq.com/posting-api/job-board/{identifier}?includeCompensation=true`
  → `jobs[]`. Skip `isListed === false`. Mapping: `id`, `title`, company =
  source name, `location`, `department`, `employmentType`, `isRemote`,
  `jobUrl` → url, `applyUrl`, `descriptionHtml`, `descriptionPlain`,
  `publishedAt`.

**Done when.**
- For each seeded ATS source, in tinker, `iterator_to_array((new XAdapter)->fetch($source))`
  returns more than 0 items. Print the count plus the first DTO's key
  fields. **No DB writes.** One live sanity check per adapter is enough.
- Pint and PHPStan (level 7, typed arrays) pass.

**Not in this phase.** Remotive, enum → class resolution, persistence.

---

### Phase 8 — Remotive adapter + adapter resolution

Status: PENDING
Role: laravel-backend · Depends on: 7 · Covers: AC07 · Size: S
Spec: B.4 (remotive row)

**Contract.**
- First, **verify the live shape**: one `GET https://remotive.com/api/remote-jobs?limit=5`,
  then record the observed keys in this phase's Evidence line. Call it
  sparingly.
- `RemotiveAdapter`: query from `settings` (`category`, `search`, `limit`;
  send only the keys that are set) → `jobs[]`. Mapping: `id`, `title`,
  `company_name`, `candidate_required_location` → location, isRemote = true,
  `category` → department, `job_type` → employmentType, `url` → url (keep
  original), `description` → html, stripped → text, `publication_date` →
  publishedAt. If the live shape differs, map the real keys and note the
  difference in Evidence. If a spec field has no equivalent, **stop and ask**.
- `SourceAdapter::adapter(): JobSourceAdapter` →
  `app(match ($this) { ...four classes... })`.

**Done when.**
- The Remotive source fetches more than 0 items (no DB write).
  `SourceAdapter::from($x)->adapter()` resolves all four.
- Pint and PHPStan pass.

---

### Phase 9 — Runs admin resource (read-only, realtime)

Status: PENDING
Role: filament-admin · Depends on: 5 · Covers: AC10 (runs + run detail) · Size: M
Spec: B.8 Runs (without actions), B.7

**Contract.**
- `CollectionRunResource`, navigation group **Collection**, label "Run".
  Read-only: `canCreate()` is false, no edit page, pages are List and View.
- Table columns: label, status (badge), sources `"{succeeded} / {failed} / {total}"`
  (header "Sources (ok / failed / total)"), `jobs_fetched`, `jobs_new`,
  triggered by (name), `started_at`, finished at plus duration (human, e.g.
  `1m 12s`; `—` while unfinished). `defaultSort('id', 'desc')`. Add
  `->socket(channel: 'collection_runs', event: 'CollectionRunUpdated')`.
  Row action: View.
- View page: an infolist with the run summary (same fields), plus
  `SourceRunsRelationManager` with columns source name, adapter (badge),
  status (badge), `jobs_fetched`, `jobs_new`, `error_message` (wrap/limit
  with a tooltip), `started_at`, `finished_at`. Both refresh on
  `collection_run_{id}` / `CollectionRunUpdated`:
  - The relation manager table uses `->socket(channel: 'collection_run_'.$this->getOwnerRecord()->getKey(), event: 'CollectionRunUpdated')`.
  - The infolist uses `<x-filament-realtime-driver::listener channel="collection_run_{id}" event="CollectionRunUpdated" callback="$wire.$refresh()" />`
    rendered on the view page (a custom view component, footer or render
    hook; pick what Filament 5 supports via `search-docs`).
- Eager-load `triggeredBy` and `sourceRuns.source`.

**Done when.**
- `/admin/collection-runs` and the view page load (use a run created in
  tinker, then delete it; its source runs cascade). Updating that run's
  status in tinker while the page is open refreshes it without a reload
  (confirm with Playwright MCP, or tell the owner to check it in Phase 15).
- Pint and PHPStan pass. No `poll`.

**Not in this phase.** "Collect jobs now", "Mark as failed", "View jobs".

---

### Phase 10 — Collection flow (start, fetch, finalize, mark failed)

Status: PENDING
Role: laravel-backend · Depends on: 1, 8, 9 · Covers: AC06 (backend), AC07, AC08, AC09, AC12 (backend) · Size: M
Spec: B.5

**Contract.**
- `App\Exceptions\CollectionRunException extends RuntimeException`, carrying a
  user-facing message.
- `App\Actions\Collection\StartCollectionRun::handle(User $triggeredBy): CollectionRun`:
  1. `Cache::lock('collection-runs:start', 10)` (if it isn't acquired, throw
     "A collection run is already in progress."). Inside it:
     `DB::transaction`. Throw "A collection run is already in progress." if
     `CollectionRun::inProgress()->exists()`, and "There are no active
     sources to collect." if there are no active sources.
  2. Create the run (`pending`, `triggered_by`, `sources_total`), plus one
     `pending` `SourceRun` per active source.
  3. `Bus::batch($sourceRuns->map(fn ($sr) => new FetchJobsFromSource($sr->id)))->onQueue('collection')->allowFailures()->finally(fn (Batch $b) => app(FinalizeCollectionRun::class)->handle($runId))->name("collection-run-{$runId}")->dispatch()`.
     The closures capture only `$runId`.
  4. Update the run: `batch_id`, status `running`, `started_at = now()`.
- `App\Jobs\FetchJobsFromSource` (`ShouldQueue`, `Batchable`): `$tries = 1`,
  `$timeout = 120`, constructor `int $sourceRunId`.
  - `handle()`: return if the batch is cancelled. Set the source run to
    `running` with `started_at`. `$items = $source->adapter->adapter()->fetch($source)`.
    **De-duplicate by externalId** (keep the last; PostgreSQL `ON CONFLICT`
    can't touch a row twice). In chunks of 200: query the existing
    `external_id`s for this source, then
    `JobPosting::upsert($rows, ['source_id', 'external_id'], $mutable)`. Rows
    include `collection_run_id = run`, `last_seen_run_id = run`,
    `first_seen_at = last_seen_at = now`, `raw` as `json_encode` (query-builder
    writes skip casts), and timestamps. `$mutable` = title, company_name,
    location, is_remote, department, employment_type, url, apply_url,
    description_html, description_text, published_at, raw, last_seen_run_id,
    last_seen_at, updated_at. **Never** `collection_run_id`,
    `first_seen_at` or `created_at`. `jobs_new` = the count of ids not
    pre-existing. Then set the source run to `completed` with
    `jobs_fetched`/`jobs_new`/`finished_at`, set the source's
    `last_run_at`/`last_run_status` (saved through the model), and dispatch
    `RealtimeEvent::dispatch('job_postings', 'JobPostingsUpdated', ['source_run_id' => $id])`
    **once**.
  - `catch (Throwable $e)`: set the source run to `failed` with
    `Str::limit($e->getMessage(), 1000)` and `finished_at`, update the
    source's last status, `Log::warning(...)`. Don't rethrow.
  - `failed(Throwable $e)`: same marking if the source run isn't finished
    yet (timeouts and killed workers).
- `App\Actions\Collection\FinalizeCollectionRun::handle(int $runId): void`:
  idempotent (return if the run is already terminal with `finished_at` set,
  which also covers runs marked failed). Aggregate `sources_succeeded`,
  `sources_failed`, `jobs_fetched` and `jobs_new` from its source runs.
  Status: all completed → `completed`; some failed → `partial`; all failed →
  `failed`. Set `finished_at`. If `triggeredBy` exists, send
  `Filament\Notifications\Notification::make()->title("Run #{id} finished — {jobs_new} new jobs from {sources_total} sources" . ($failed ? " ({failed} failed)" : ''))`.
  Status color: success, warning or danger. Action "View run" →
  `CollectionRunResource::getUrl('view', ['record' => $run])`. Send it with
  `->sendToDatabase($user, isEventDispatched: true)`.
- `App\Actions\Collection\MarkCollectionRunFailed::handle(CollectionRun $run): void`:
  throw a `CollectionRunException` if the run isn't in progress. Set
  unfinished source runs → `failed` ("Marked as failed by an admin.",
  `finished_at`), each via `save()`. Set the run → `failed` with
  aggregated counters and `finished_at`. Then
  `Bus::findBatch($run->batch_id)?->cancel()`.

**Done when.**
- Tinker: `app(StartCollectionRun::class)->handle(User::where('email', config('talent.seed.admin.email'))->first())`,
  then `php artisan queue:work database --queue=collection,default --stop-when-empty`.
  The run ends `completed` (or `partial`, with a reason), the counters match
  the source runs, the postings exist per source, and the admin has 1 new
  notification.
- A second start while the first is still `pending` (before the worker runs)
  throws "already in progress".
- A second full run gives `jobs_new ≈ 0`, the postings count is unchanged,
  and `collection_run_id` still equals the first run for old postings.
- Pint and PHPStan pass.

**Not in this phase.** Filament buttons (Phase 11), the full smoke test
(Phase 15).

---

### Phase 11 — Runs actions: Collect jobs now, Mark as failed

Status: PENDING
Role: filament-admin · Depends on: 10 · Covers: AC06, AC12 · Size: S
Spec: B.8 Runs, B.5 stuck runs

**Contract.**
- List-page header action **"Collect jobs now"**: `requiresConfirmation()`
  with the description "This will collect jobs from {n} active sources."
  Disabled with a tooltip when a run is in progress ("A collection run is
  already in progress.") or no source is active ("There are no active
  sources."). It calls `StartCollectionRun` with `auth()->user()`. On
  success: notification "Run #{id} started", body "Collecting from {n}
  sources." On `CollectionRunException`: danger notification with its message.
- **"Mark as failed"** row action and view-page header action:
  `requiresConfirmation()`, visible only when `status->isInProgress()`. It
  calls `MarkCollectionRunFailed` and sends a success/danger notification.

**Done when.**
- Clicking the action creates exactly one run. It's disabled while that run
  is pending/running. A run stuck in `running` (set in tinker, no worker)
  can be marked failed, after which "Collect jobs now" is enabled again.
- Pint and PHPStan pass. No `poll`.

---

### Phase 12 — Job postings admin resource

Status: PENDING
Role: filament-admin · Depends on: 9 · Covers: AC11, AC10 (postings) · Size: M
Spec: B.8 Job postings

**Contract.**
- `JobPostingResource`, group **Collection**. Read-only (no create/edit).
- Table: `defaultGroup(Group::make('collection_run_id')->label('Run')->getTitleFromRecordUsing(fn ($r) => $r->collectionRun->label)->orderQueryUsing(fn ($q, $dir) => $q->orderBy('collection_run_id', 'desc')))`,
  then `defaultSort('published_at', 'desc')`. Columns: title (searchable,
  wrap), company_name (searchable), location, `is_remote` (icon), source
  name, adapter (badge via `source.adapter`), `published_at`,
  `first_seen_at`. Eager-load `collectionRun` and `source`.
- Filters: `collection_run` (select; options = the latest 50 runs as
  `id => label`), source (relationship select), adapter (select on
  `source.adapter` via `whereHas`), "Today's runs only" (toggle →
  `whereHas('collectionRun', fn ($q) => $q->startedToday())`).
- Row actions: View (modal infolist with all mapped fields plus
  `description_text`), "Open posting" (`url($record->url, shouldOpenInNewTab: true)`).
- `->socket(channel: 'job_postings', event: 'JobPostingsUpdated')`.
- Add a **"View jobs"** row action to the Runs table (Phase 9). It opens
  `JobPostingResource` index with the `collection_run` filter preset (check
  the Filament 5 filter query-string format via `search-docs`).

**Done when.**
- Postings from the Phase 10 runs are grouped per run, newest run first.
  Every filter works. "View jobs" lands already filtered.
- Pint and PHPStan pass. No `poll`.

---

### Phase 13 — User-app auth: Fortify login, active-user guard

Status: BLOCKED (D1)
Role: laravel-backend → inertia-frontend · Depends on: 2, D1 · Covers: AC01, AC02, AC05 (`/login`) · Size: M
Spec: B.2, B.9 (login)

The contract below assumes **D1 = A**. For B or C, rewrite this phase's
contract first (owner-approved) and keep the same Done-when items.

**Contract.**
- `composer require laravel/fortify`, then `php artisan fortify:install`.
  Then delete what registration/reset/profile would use:
  `app/Actions/Fortify/{CreateNewUser,ResetUserPassword,UpdateUserPassword,UpdateUserProfileInformation,PasswordValidationRules}.php`
  and their bindings, plus the published two-factor migration (before
  migrating). `config/fortify.php`: `features => []`, `views => true`,
  `home => '/dashboard'`, `guard => 'web'`.
- `FortifyServiceProvider`: keep the `login` rate limiter.
  `Fortify::loginView(fn () => Inertia::render('auth/login', ['status' => session('status')]))`.
  `Fortify::authenticateUsing`: find the user by email and check the
  password with `Hash::check` (return null on a mismatch). If the user is
  blocked, `throw ValidationException::withMessages(['email' => 'Your access is disabled.'])`.
  Otherwise return the user.
- `App\Http\Middleware\EnsureUserIsActive`: if the authenticated user isn't
  active, `Auth::guard('web')->logout()`, invalidate the session, regenerate
  the token, and
  `redirect()->route('login')->withErrors(['email' => 'Your access is disabled.'])`.
- `routes/web.php`: `Route::middleware(['auth', EnsureUserIsActive::class])->group(...)`
  containing `Route::inertia('/dashboard', 'dashboard')->name('dashboard')`
  (Phase 14 swaps this for the controller).
- `HandleInertiaRequests` shares `auth.user` as `{id, name, email}` or null.
- Frontend: `pages/auth/login.tsx` (email, password, remember; errors and
  status shown; Inertia `<Form>` with the Wayfinder `login` action),
  `layouts/app-layout.tsx` (app name, "Today's jobs" nav link, user name,
  "Log out" button via Wayfinder `logout`), `pages/dashboard.tsx` (layout plus
  the heading "Today's jobs"), `welcome.tsx` (add "Log in", or "Dashboard"
  when authenticated; **no register link**). `resources/js/types` gets
  `auth.user`.

**Done when.**
- `php artisan route:list` shows `login` and `logout`, with no `register`,
  password-reset or 2FA routes.
- Admin and client both log in at `/login` → `/dashboard`. The client at
  `/admin` is denied.
- A user blocked in tinker is logged out on their next request, redirected
  to `/login`, and shown "Your access is disabled.". A blocked login attempt
  shows the same message.
- Pint, PHPStan, `yarn check` and `yarn types:check` pass.

---

### Phase 14 — User `/dashboard` — Today's jobs

Status: PENDING
Role: laravel-backend → inertia-frontend · Depends on: 5, 8, 13 · Covers: AC13 · Size: M
Spec: B.9

**Contract.**
- `App\Http\Controllers\DashboardController` (invokable). The route replaces
  the Phase 13 `Route::inertia` with the same name and middleware. Props:
  - `summary`: `{ runsCount: int, newJobs: int, latestRunAt: string|null }`
    (ISO 8601; over `CollectionRun::startedToday()`).
  - `postings`: `JobPosting::whereHas('collectionRun', fn ($q) => $q->startedToday())->with('source:id,adapter')`.
    Order: `orderByRaw('published_at DESC NULLS LAST')->orderByDesc('id')`,
    `->paginate(25)->withQueryString()->through(...)` →
    `{ id, title, company, location, isRemote, sourceLabel, publishedAt (ISO|null), url }`.
    `sourceLabel` is `SourceAdapter::sourceLabel()`. Only these fields.
- Frontend (`pages/dashboard.tsx` composes components from
  `resources/js/components`):
  - A summary card (runs today, new jobs, latest run time).
  - A job card for each item: title, company, location, remote badge,
    source label, relative published date (`Intl.RelativeTimeFormat`, no new
    dependency), and an "Open posting" link
    (`target="_blank" rel="noopener noreferrer"`).
  - Pagination links.
  - Empty state: **"No jobs collected today yet."**
- No filters, search, actions, client fetching or realtime.

**Done when.**
- After a run today, `/dashboard` lists its postings 25 per page in the
  specified order. Postings first seen in earlier-day runs are absent. With
  no run today, the empty state shows.
- Pint, PHPStan, `yarn check` and `yarn types:check` pass.

---

### Phase 15 — Final verification, smoke test, report

Status: PENDING
Role: orchestrator + qa-tester · Depends on: 1–14 · Covers: all (esp. AC05, AC07, AC08, AC14, AC15) · Size: M
Spec: 0.3 steps 5–8, B.10, B.11

**Steps.**
1. Gate: `composer ci:check`. `grep -rnE "poll\(|wire:poll|pollingInterval" app resources`
   must return nothing. `git status` confirms no commits were made by agents
   (read-only).
2. Local DB: `php artisan config:clear`, `migrate`, `db:seed` twice. Row
   counts for users and sources are unchanged. `Auth::attempt` works for both
   seed users. Report the credential **keys** (`SEED_*`), never the values.
3. B.11.1 smoke test: start a run as the admin in tinker, then
   `queue:work database --queue=collection,default --stop-when-empty`, and
   report the run counters, source runs and postings per source. Repeat once
   and show `jobs_new ≈ 0` and no duplicates. For the failure path, create
   `Invalid (smoke test)` (greenhouse, `this-board-does-not-exist-talent-labs`),
   run again, and confirm `partial` plus an error message. Then **deactivate**
   it, and tell the owner it exists.
4. `php artisan dev` (brief) lists server, vite, logs, reverb (`--debug`),
   and queue (`queue:work ... collection,default`).
5. AC15: the no-git-writes rule is in `CLAUDE.md` and in all five agent
   files, plus the `.claude/settings.json` rules (note the `ask` deviation
   from Phase 0).
6. `execute-feature` §3: an integrated `code-reviewer` pass over the full
   diff and all ACs. Re-read `docs/features/job-collection-mvp/spec.md` end
   to end, and turn any gap into a new phase in this plan.
7. Final report: what was built, the AC01–AC15 status, checks run, blocked
   items, out-of-scope ideas, and the owner's manual checklist (B.11.2,
   verbatim). **Do not commit.**

**Done when.** Every AC is PASS or has an explicit owner-accepted reason, and
the report has been delivered.
