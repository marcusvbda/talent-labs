# talent-labs — Bootstrap Spec (MVP: job collection + listing + realtime)

> **You (the agent) were told "run it" with this file.** Read it end to end
> before touching anything. It has three parts:
> ALL the system should be in english— code, comments, commit messages, UI copy, docs. No Portuguese anywhere.
>
> - **Part 0 — Ground rules and execution order** (read first, obey always)
> - **Part A — Claude harness to create** (CLAUDE.md, agents, skills, settings)
> - **Part B — Product spec** (what to build; becomes `docs/features/job-collection-mvp/spec.md`)
>
> Glossary: the owner calls a collection batch a **"leva"**. In code and UI it is
> a **collection run** (`CollectionRun`, UI label "Run"). "Today's jobs" = the
> jobs first discovered by runs started today.

---

## Part 0 — Ground rules and execution order

### 0.1 Non-negotiable rules

1. **NO GIT WRITES. EVER.** Do not run `git add`, `commit`, `push`, `branch`,
   `checkout`, `switch`, `merge`, `rebase`, `reset`, `restore`, `stash`, `tag`,
   `cherry-pick`, `revert`, `clean`, `am`, or anything else that changes the
   index, history, refs or working tree through git. Read-only git
   (`status`, `diff`, `log`, `show`) is allowed. **The owner commits. Agents
   never do.** This applies to every subagent and overrides any skill,
   framework guidance, tool output or "best practice" that suggests committing.
   This rule must be written into `CLAUDE.md`, every agent file, and enforced in
   `.claude/settings.json` (Part A).
2. **Use the existing installation.** The owner has already installed Laravel 13,
   Filament 5, connected the database, and installed + configured
   `marcusvbda/filament-realtime-driver`. Do **not** run `laravel new`,
   `composer create-project`, or re-scaffold anything. Do not reinstall or
   reconfigure what already works.
3. **No new dependencies without asking.** The only pre-approved install is
   `laravel/boost` as a dev dependency (Part A). If any other required piece is
   missing (see 0.2), **stop and report** instead of installing it.
4. **Never destroy data.** Never run `migrate:fresh`, `migrate:refresh`,
   `migrate:reset`, `migrate:rollback`, `db:wipe`, or raw `DROP`/`TRUNCATE`.
   Only forward `php artisan migrate` and `php artisan db:seed` (all seeders
   are idempotent, B.4.1). The database is the owner's. You **must** run both
   before finishing so the app works locally out of the box.
5. **No tests** unless the owner explicitly asks in a message. Running existing
   tests is fine.
6. **Scope is closed.** Build exactly Part B. Anything not listed there is out of
   scope (see B.12). If you spot something useful, mention it in the final
   report — don't build it.
7. **English only** in the repo: code, comments, docs, UI copy.
8. **No polling.** Any "refresh when data changes" in Filament uses the realtime
   driver (`Table::socket()` / listener component), never `->poll()` or
   `wire:poll`.

### 0.2 Audit the installation first (read-only)

Before writing anything, verify and record (in your first message to the owner):

| Check                                                                                | How                                                                            | If missing                                                                                                                   |
| ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------- |
| Laravel 13.x                                                                         | `php artisan --version`                                                        | stop, report                                                                                                                 |
| Filament 5.x with an admin panel provider                                            | `composer show filament/filament`, `app/Providers/Filament/*PanelProvider.php` | stop, report                                                                                                                 |
| Laravel Reverb installed, `BROADCAST_CONNECTION=reverb`                              | `composer show laravel/reverb`, `.env`                                         | stop, report                                                                                                                 |
| `marcusvbda/filament-realtime-driver` installed, plugin registered with `->socket()` | `composer show`, panel provider                                                | stop, report                                                                                                                 |
| DB reachable, driver name                                                            | `php artisan migrate:status`                                                   | stop, report                                                                                                                 |
| `QUEUE_CONNECTION`                                                                   | `.env`                                                                         | if not `database`, report and ask (don't change it silently)                                                                 |
| `notifications`, `jobs`, `job_batches`, `failed_jobs` tables exist                   | `migrate:status` / Boost `database-schema`                                     | create the missing ones via `make:notifications-table` / `make:queue-batches-table` etc. (framework stubs, not dependencies) |
| `php artisan dev` + `Illuminate\Foundation\DevCommands` available                    | framework source                                                               | stop, report                                                                                                                 |
| Lint/format/type tooling present (Pint, PHPStan/Larastan, ESLint, Prettier, `tsc`)   | `composer.json` / `package.json` scripts                                       | just note what exists; use only what exists                                                                                  |

On **PostgreSQL**, the `notifications.data` column must be `json` (Filament
queries `data->>'format'`). If it's `text`, add a forward migration changing it.

This MVP is **100% native Filament + Laravel — no Inertia/React**. Inertia is
reserved for future non-Filament pages, not part of this MVP. If Inertia
and/or Fortify are already installed (e.g. from a starter kit), leave them
installed and untouched — don't remove packages — except disabling any public
registration route/feature they expose. Neither is a blocker and neither is
required for this spec.

### 0.3 Execution order

1. Audit (0.2). Stop on any blocker.
2. Create the harness (Part A).
3. Copy **Part B verbatim** into `docs/features/job-collection-mvp/spec.md`.
4. Execute it with the `execute-feature` skill (state in
   `.claude/state/job-collection-mvp.md`).
5. Run deterministic checks that exist in the repo (Pint on dirty files,
   PHPStan if configured, `tsc`/ESLint/Prettier if configured).
6. Prepare the local DB: add the seed env keys (B.4.1) to `.env.example` and
   append any that are **missing** from `.env` (never change existing `.env`
   values), then `php artisan config:clear`, `php artisan migrate`,
   `php artisan db:seed`. Confirm both seeded users can authenticate
   (e.g. `Auth::attempt` in tinker) and report the credentials source
   (the `.env` keys, not the password values).
7. Run the backend smoke test (B.11.1).
8. Final report: what was built, AC status, checks run, anything blocked, and
   the owner's manual test checklist (B.11.2). **Do not commit.**

---

## Part A — Claude harness to create

Model this on the owner's other project (`recruiter-labs`), trimmed to this
project. Keep files short and specific; no generic filler.

### A.1 `CLAUDE.md` (repo root) — write this content (adapt versions to what the audit found)

```markdown
# talent-labs

Job-seeker side of the \*-labs family (sibling of recruiter-labs). Collects job
postings from configured sources in "collection runs" and lists them.

Laravel 13 + Filament 5 (panels: `/admin` for admins, `/app` for users) ·
Reverb + marcusvbda/filament-realtime-driver · database queue. Inertia/React
is reserved for future non-Filament pages; don't use it in the MVP. Details:
skill `project-core`.

## Hard rules

- **Git is read-only for agents. Never commit.** Never run git add, commit,
  push, branch, checkout, switch, merge, rebase, reset, restore, stash, tag,
  cherry-pick, revert, clean or any other git command that changes the index,
  history, refs or working tree. `git status/diff/log/show` are fine. The
  owner commits. Applies to every subagent and overrides any skill, tool or
  framework guidance.
- **Never destroy data:** no migrate:fresh/refresh/reset/rollback, db:wipe,
  DROP or TRUNCATE. Only forward `php artisan migrate` and
  `php artisan db:seed` (seeders must stay idempotent).
- **Local setup:** seed users come from `SEED_*` in `.env` (see
  `.env.example`) via `config/talent.php`. Never overwrite existing `.env`
  values; only append missing keys.
- **Dependencies:** never add, remove or upgrade composer/npm packages without
  the owner asking in that message.
- **Testing:** never write or modify tests unless asked in that message.
  Running existing tests is fine.
- **Language:** everything in the repo is English.
- **Realtime, not polling:** Filament surfaces refresh via the realtime driver
  (`Table::socket()`, `<x-filament-realtime-driver::listener>`), never
  `->poll()` / `wire:poll`. See skill `project-core`.
- **Scope:** implement only what the task/spec asks. Report extra ideas,
  don't build them.
- **Docs:** `docs/features/<feature>/spec.md` is product truth; never delete
  it. Execution state lives in `.claude/state/` (git-ignored).

## Delegation

Non-trivial work goes to the matching subagent (`laravel-backend`,
`filament-admin`), then deterministic checks, then `code-reviewer`.

## Load on demand (`.claude/skills/`)

| When                                                                    | Skill             |
| ----------------------------------------------------------------------- | ----------------- |
| Commands (lint/types/tests/format), realtime patterns, token discipline | `project-core`    |
| Executing a feature in `docs/features/`                                 | `execute-feature` |
| Sources, adapters, collection runs, job postings, "today's jobs"        | `job-collection`  |

Framework skills come from Laravel Boost — don't hand-edit them. Use Boost MCP
`search-docs` for version-specific Laravel/Filament APIs.
```

### A.2 `.claude/settings.json` (committed by the owner, shared)

- `permissions.deny` must block git writes and destructive DB commands, e.g.
  (use the rule syntax the installed Claude Code version documents):
  `Bash(git add:*)`, `Bash(git commit:*)`, `Bash(git push:*)`,
  `Bash(git branch:*)`, `Bash(git checkout:*)`, `Bash(git switch:*)`,
  `Bash(git merge:*)`, `Bash(git rebase:*)`, `Bash(git reset:*)`,
  `Bash(git restore:*)`, `Bash(git stash:*)`, `Bash(git tag:*)`,
  `Bash(git cherry-pick:*)`, `Bash(git revert:*)`, `Bash(git clean:*)`,
  `Bash(git am:*)`, `Bash(php artisan migrate:fresh:*)`,
  `Bash(php artisan migrate:refresh:*)`, `Bash(php artisan migrate:reset:*)`,
  `Bash(php artisan migrate:rollback:*)`, `Bash(php artisan db:wipe:*)`.
- `enabledMcpjsonServers`: `laravel-boost`, `context7`, `playwright`.

### A.3 `.mcp.json`

Servers: `laravel-boost` (`php artisan boost:mcp`), `context7`
(`npx -y @upstash/context7-mcp@latest`), `playwright`
(`npx -y @playwright/mcp@latest`). No cloud/DB-vendor servers.

### A.4 Laravel Boost

`composer require laravel/boost --dev` (pre-approved), then `php artisan
boost:install`. Configure `boost.json` with `"guidelines": false` (so Boost
never overwrites `CLAUDE.md`), `"mcp": true`, and skills relevant to the stack
(`laravel-best-practices`, `filament-development`, `tailwindcss-development`,
plus `inertia-react-development`/`fortify-development`/`pest-testing` only if
those packages are already present — they aren't required by this MVP). If
`boost:install` requires interactive input you can't provide, stop and ask the
owner to run it.

### A.5 Agents (`.claude/agents/*.md`)

Each file: frontmatter (`name`, `description`, `tools`, `model`, `effort`,
`maxTurns`) + short role body. **Every agent body ends with:** "Global rules in
`CLAUDE.md` apply in full. Never run git write commands — the owner commits."

| File                 | model / effort  | Scope                                                                                                                                                                                                                                                                                                                                              |
| -------------------- | --------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `laravel-backend.md` | opus / medium   | migrations, models, enums, actions, jobs, adapters, policies, middleware. Loads `job-collection` skill for domain work.                                                                                                                                                                                                                            |
| `filament-admin.md`  | sonnet / medium | Filament resources, pages, actions, relation managers, panel config, for **both** the `admin` and `app` panels. Contains the realtime rule: `Table::socket(channel:, event:)` + model/job dispatches `RealtimeEvent`; query-builder writes (`upsert`, bulk `update`) bypass model events → dispatch explicitly after them. Reference `vendor/marcusvbda/filament-realtime-driver/README.md`. |
| `code-reviewer.md`   | sonnet / medium | Read-only (Read, Grep, Glob, Bash). Reviews the task diff against its acceptance criteria. Output: per-AC PASS/FAIL, blocking, non-blocking, `Verdict: APPROVED                                                                                                                                                                                   | CHANGES_REQUIRED`. Never requests tests. Flags any git write, any `->poll()`, any scope creep as blocking. |
| `qa-tester.md`       | sonnet / low    | Runs existing tests/checks, diagnoses failures. Doesn't write tests unless the owner authorized it.                                                                                                                                                                                                                                                |

### A.6 Skills (`.claude/skills/<name>/SKILL.md`)

- **`project-core`** — stack detail; the exact verification commands that
  **actually exist** in `composer.json`/`package.json` (don't invent any);
  realtime patterns (below); token discipline (review the diff, not the repo;
  deterministic checks before AI review; one resume max per stalled
  subagent; embed contracts in delegation prompts instead of telling subagents
  to go read docs).
  Realtime section must state: panel uses
  `FilamentRealtimeDriverPlugin::make()->socket()->databaseNotifications()` +
  `$panel->databaseNotifications()`; tables use `->socket()`; notifications are
  sent with `->sendToDatabase($user, isEventDispatched: true)`;
  `RealtimeEvent` is `ShouldBroadcastNow` (no queue needed); public channels
  carry only ids / refresh signals, never sensitive payloads.
- **`execute-feature`** — orchestrates `docs/features/<feature>/spec.md`:
  entry gate (spec complete with ACs, else stop) → audit repo → derive task
  graph into `.claude/state/<feature>.md` (tasks with domain, deps, covered
  ACs, completion evidence, status) → per task: delegate to role → deterministic
  checks → `code-reviewer` → max 2 correction rounds then stop and surface →
  integrated review → re-read spec end to end for gaps → report. Never edits
  the spec to match code. **Never commits.**
- **`job-collection`** — domain invariants from Part B: adapter contract,
  dedup key `(source_id, external_id)`, "first seen" ownership of a posting by
  a run, definition of "today", run status rules, realtime channel names,
  one-run-at-a-time rule. Short; it's a cheat sheet, not a copy of the spec.

### A.7 Docs and ignore rules

- `docs/features/README.md`: one paragraph — each feature lives in
  `docs/features/<feature>/spec.md` (product truth, with acceptance criteria);
  optional `tech-design.md` is binding when present; execution state goes to
  `.claude/state/`.
- `docs/features/job-collection-mvp/spec.md`: Part B verbatim.
- `.gitignore`: add `/.claude/state/`, `/.claude/settings.local.json`,
  `.playwright-mcp`.

---

## Part B — Product spec: job collection MVP

### B.1 Goal

Validate three things, nothing else:

1. Jobs can be collected from configurable sources on demand, grouped into
   collection runs ("levas").
2. Collected jobs are listed in both panels: **admin** sees all jobs grouped
   by run; **user** sees only today's jobs.
3. The owner's realtime driver works end to end in both panels (tables and
   database notifications update without reload or polling).

Closed beta: no public registration. Local only for now.

### B.2 Actors and access

Two native Filament panels, both using `->login()` (no registration feature
on either): **admin** at `/admin`, **app** at `/app`. `User implements
FilamentUser`, and `canAccessPanel(Panel $panel)` branches on
`$panel->getId()`:

- **Admin** — `users.is_admin = true` and `status = active`. Only admins can
  access the `admin` panel: `canAccessPanel()` for `admin` is
  `$this->is_admin && $this->status === UserStatus::Active`.
- **User** — any `status = active` user (admins included). Uses the `app`
  panel: `canAccessPanel()` for `app` is `$this->status === UserStatus::Active`.
- **Blocked** — `status = blocked`. Denied on both panels, natively, by
  `canAccessPanel()` returning false — no custom middleware. Filament rejects
  login and denies access to a panel on the next request for a user blocked
  mid-session; there is no separate "Your access is disabled." flow to build.
- **Registration is disabled.** Neither panel has a registration feature.
  Users are created only in the admin panel.

### B.3 Data model

#### `users` (forward migration adding columns)

| column     | type                                                              | notes |
| ---------- | ----------------------------------------------------------------- | ----- |
| `is_admin` | boolean, default false                                            |       |
| `status`   | string (enum `UserStatus`: `active`, `blocked`), default `active` |       |

Migration backfill: **existing users become `is_admin = true`, `status =
active`** (on this fresh install the only existing user is the owner's
Filament user; without this they'd lose admin access).

#### `sources`

| column             | type                          | notes                                                                                                |
| ------------------ | ----------------------------- | ---------------------------------------------------------------------------------------------------- |
| `id`               | pk                            |                                                                                                      |
| `name`             | string                        | display name; also the company name fallback for ATS sources (e.g. "Linear")                         |
| `adapter`          | string (enum `SourceAdapter`) | `greenhouse`, `lever`, `ashby`, `remotive`                                                           |
| `identifier`       | string, nullable              | board token / company slug. Required for `greenhouse`, `lever`, `ashby`; must be null for `remotive` |
| `settings`         | json, nullable                | adapter options; only `remotive` uses it: `category`, `search`, `limit` (all optional)               |
| `interval_minutes` | unsigned int, default 60      | **stored only**, not used yet (future automatic scheduling). Help text says so.                      |
| `is_active`        | boolean, default true         | only active sources are collected                                                                    |
| `last_run_at`      | timestamp, nullable           |                                                                                                      |
| `last_run_status`  | string, nullable              | last `SourceRunStatus`                                                                               |
| timestamps         |                               |                                                                                                      |

Unique: `(adapter, identifier)` only when `identifier` is not null (partial
index), so aggregator sources may repeat per adapter.

#### `collection_runs` (a "leva")

| column                                                   | type                                | notes                                                  |
| -------------------------------------------------------- | ----------------------------------- | ------------------------------------------------------ |
| `id`                                                     | pk                                  |                                                        |
| `status`                                                 | string (enum `CollectionRunStatus`) | `pending`, `running`, `completed`, `partial`, `failed` |
| `triggered_by`                                           | fk users, nullable, nullOnDelete    |                                                        |
| `batch_id`                                               | string, nullable                    | Laravel bus batch id                                   |
| `sources_total` / `sources_succeeded` / `sources_failed` | unsigned int, default 0             |                                                        |
| `jobs_fetched`                                           | unsigned int, default 0             | postings returned by sources                           |
| `jobs_new`                                               | unsigned int, default 0             | postings first seen in this run                        |
| `started_at` / `finished_at`                             | timestamp, nullable                 |                                                        |
| timestamps                                               |                                     |                                                        |

Index: `started_at`. Label accessor: `Run #{id} · {started_at in app timezone, e.g. 22 Sep 2026 22:50}`.

#### `source_runs`

| column                       | type                            | notes                                       |
| ---------------------------- | ------------------------------- | ------------------------------------------- |
| `id`                         | pk                              |                                             |
| `collection_run_id`          | fk, cascadeOnDelete             |                                             |
| `source_id`                  | fk, restrictOnDelete            |                                             |
| `status`                     | string (enum `SourceRunStatus`) | `pending`, `running`, `completed`, `failed` |
| `jobs_fetched` / `jobs_new`  | unsigned int, default 0         |                                             |
| `error_message`              | text, nullable                  | truncated to a sane length                  |
| `started_at` / `finished_at` | timestamp, nullable             |                                             |
| timestamps                   |                                 |                                             |

Unique: `(collection_run_id, source_id)`.

#### `job_postings`

Do **not** name this table/model `jobs`/`Job` — it collides with Laravel's
queue `jobs` table.

| column                           | type                                       | notes                                                                           |
| -------------------------------- | ------------------------------------------ | ------------------------------------------------------------------------------- |
| `id`                             | pk                                         |                                                                                 |
| `source_id`                      | fk, restrictOnDelete                       |                                                                                 |
| `collection_run_id`              | fk                                         | the run that **first** discovered it (never changes)                            |
| `last_seen_run_id`               | fk collection_runs, nullable, nullOnDelete |                                                                                 |
| `external_id`                    | string                                     | id at the source                                                                |
| `title`                          | string                                     |                                                                                 |
| `company_name`                   | string                                     | payload value, else `sources.name`                                              |
| `location`                       | string, nullable                           |                                                                                 |
| `is_remote`                      | boolean, nullable                          |                                                                                 |
| `department`                     | string, nullable                           |                                                                                 |
| `employment_type`                | string, nullable                           |                                                                                 |
| `url`                            | text                                       | public posting URL                                                              |
| `apply_url`                      | text, nullable                             |                                                                                 |
| `description_html`               | longText, nullable                         |                                                                                 |
| `description_text`               | longText, nullable                         |                                                                                 |
| `published_at`                   | timestamp, nullable                        |                                                                                 |
| `raw`                            | json                                       | full source payload for this posting (lets us re-map later without re-fetching) |
| `first_seen_at` / `last_seen_at` | timestamp                                  |                                                                                 |
| timestamps                       |                                            |                                                                                 |

Unique: `(source_id, external_id)`. Indexes: `collection_run_id`, `published_at`.

No NLP/AI extraction. Fields are a direct per-adapter mapping only.

### B.4 Source adapters

Contract (`App\Collection\Contracts\JobSourceAdapter` or similar):

```php
/** @return iterable<JobPostingData> */
public function fetch(Source $source): iterable;
```

`JobPostingData` is a readonly DTO with the `job_postings` mappable fields
(`externalId`, `title`, `companyName`, `location`, `isRemote`, `department`,
`employmentType`, `url`, `applyUrl`, `descriptionHtml`, `descriptionText`,
`publishedAt`, `raw`). `SourceAdapter` enum resolves the adapter class, its
label, and whether it requires `identifier`.

HTTP: Laravel `Http` client with timeout (~20s), `retry(2, 500)`, `acceptJson()`,
a descriptive User-Agent (`talent-labs/0.1 (local)`), and throw on non-2xx.

| Adapter      | Endpoint                                                                                                              | Mapping notes                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ------------ | --------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `greenhouse` | `GET https://boards-api.greenhouse.io/v1/boards/{identifier}/jobs?content=true` → `{ jobs: [...] }`                   | `id`, `title`, `company_name`, `location.name`, `departments[0].name`, `absolute_url` → url + apply_url, `content` is **HTML-entity-encoded** HTML (decode, then strip tags for text), `first_published ?? updated_at` → published_at                                                                                                                                                                                                                   |
| `lever`      | `GET https://api.lever.co/v0/postings/{identifier}?mode=json` → **top-level array**                                   | `id`, `text` → title, company = source name, `categories.location`, `categories.team` → department, `categories.commitment` → employment_type, `workplaceType === 'remote'` → is_remote, `hostedUrl` → url, `applyUrl`, `description` (+ `lists[].text/content` + `additional`) → html, `descriptionPlain` (+ `additionalPlain`) → text, `createdAt` is **Unix ms**                                                                                     |
| `ashby`      | `GET https://api.ashbyhq.com/posting-api/job-board/{identifier}?includeCompensation=true` → `{ jobs: [...] }`         | skip `isListed === false`; `id`, `title`, company = source name, `location`, `department`, `employmentType`, `isRemote`, `jobUrl` → url, `applyUrl`, `descriptionHtml`, `descriptionPlain`, `publishedAt`                                                                                                                                                                                                                                               |
| `remotive`   | `GET https://remotive.com/api/remote-jobs` (query from `settings`: `category`, `search`, `limit`) → `{ jobs: [...] }` | `id`, `title`, `company_name`, `candidate_required_location` → location, is_remote = true, `category` → department, `job_type` → employment_type, `url`, `description` (html), `publication_date`. **Verify the live response shape before mapping** (not verified when this spec was written). Remotive asks API users to call it sparingly and to credit/link back to Remotive — show "via Remotive" as the source label and keep the original `url`. |

Endpoint shapes for greenhouse/lever/ashby were verified live on 2026-09-22.
Still sanity-check one real response per adapter during implementation.

Seeder `SourceSeeder` (idempotent, `firstOrCreate` on `adapter + identifier`),
all active:

| name     | adapter    | identifier | settings         |
| -------- | ---------- | ---------- | ---------------- |
| Remotive | remotive   | null       | `{"limit": 100}` |

#### B.4.1 Seeders (local, ready to log in)

`DatabaseSeeder` calls, in order: `UserSeeder`, `SourceSeeder`. Every seeder
is **idempotent** (safe to run `php artisan db:seed` any number of times;
never duplicates rows).

Credentials come from env through a config file (no `env()` outside
`config/`): `config/talent.php` →
`seed.admin.{name,email,password}` and `seed.client.{name,email,password}`.

Add to `.env.example` (and append to `.env` only the keys that are missing):

```dotenv
# Local seed users (db:seed)
SEED_ADMIN_NAME="Admin"
SEED_ADMIN_EMAIL=admin@talent-labs.test
SEED_ADMIN_PASSWORD=password
SEED_CLIENT_NAME="Client"
SEED_CLIENT_EMAIL=client@talent-labs.test
SEED_CLIENT_PASSWORD=password
```

`UserSeeder`:

- `updateOrCreate` by email for both users; always sets name, hashed
  password (from config), `status = active`, verified email
  (`email_verified_at = now()` if the column exists).
- Admin user: `is_admin = true` → logs in at `/admin` and `/app`.
- Client user: `is_admin = false` → logs in at `/app` only (denied at
  `/admin`).
- If a user's email or password config is empty, skip that user with a
  console warning instead of failing.
- Refuse to run in production (`app()->isProduction()` → warn and return).

`SourceSeeder`: the source above, `firstOrCreate` on `adapter + identifier`.

Run with `php artisan db:seed` (never `migrate:fresh --seed`).

### B.5 Collection flow

**Start** — `App\Actions\Collection\StartCollectionRun` (called by the Filament
action; also callable from tinker for the smoke test):

1. Guard (inside a DB transaction / cache lock so double-clicks can't create two
   runs): fail with a user-facing message if a run is `pending` or `running`,
   or if there are no active sources.
2. Create `collection_runs` row (`pending`, `triggered_by`, `sources_total`).
3. Create one `source_runs` row (`pending`) per active source.
4. Dispatch a **bus batch** of `FetchJobsFromSource` jobs (one per source run)
   on queue `collection`, with `allowFailures()` and a `finally` callback that
   calls `FinalizeCollectionRun` for this run id. Callbacks capture only the
   run id (they're serialized).
5. Save `batch_id`; set run `running`, `started_at = now()`.

**Per source** — `App\Jobs\FetchJobsFromSource` (`$tries = 1`, sensible
`$timeout`):

1. Mark source run `running`, `started_at`.
2. Fetch via the adapter. For chunks of postings: find which `external_id`s
   already exist for this source; **insert** new ones with
   `collection_run_id = this run`, `first_seen_at = now`; **update** existing
   ones' mutable fields plus `last_seen_run_id`, `last_seen_at` (never touch
   `collection_run_id` / `first_seen_at`). Use `upsert` or equivalent.
3. Mark source run `completed` with `jobs_fetched` / `jobs_new`; update
   `sources.last_run_at` / `last_run_status`.
4. On any exception: catch it, mark source run `failed` with the message,
   update the source's last status, log it. Don't rethrow (no retry storm).
   Implement `failed()` too, so a timeout/killed worker still marks the source
   run `failed`.
5. After the write, dispatch realtime events explicitly (upserts bypass model
   events): `job_postings` / `JobPostingsUpdated` once per source run — **not
   once per posting**.

**Finalize** — `App\Actions\Collection\FinalizeCollectionRun`:

- Aggregate from `source_runs`: `sources_succeeded`, `sources_failed`,
  `jobs_fetched`, `jobs_new`.
- Status: all completed → `completed`; some failed → `partial`; all failed →
  `failed`. Set `finished_at`.
- Send a Filament **database notification** to the triggering admin:
  "Run #12 finished — 143 new jobs from 4 sources (1 failed)", with an action
  linking to the run's view page. Use `isEventDispatched: true`.
- Idempotent (safe if called twice).

**Stuck runs** — a run left `pending`/`running` (worker died) would block new
runs. Admin gets a **"Mark as failed"** row/page action (with confirmation) on
pending/running runs: marks the run and its unfinished source runs `failed`,
cancels the bus batch if it exists.

### B.6 Queue and dev processes

- Queue connection `database` (as audited). Collection jobs on queue
  `collection`.
- `composer dev` → `@php artisan dev` (with
  `Composer\Config::disableProcessTimeout`), mirroring recruiter-labs. In
  `AppServiceProvider::boot()` under `runningInConsole()`, register via
  `DevCommands::artisan(...)`:
    - `reverb:start --debug` → `reverb`
    - `queue:work database --queue=collection,default` → `queue`
    - Only add `filament-realtime-driver:listen` if the owner already configured a
      backend listener callback (otherwise it exits immediately — skip it).
    - Check what `php artisan dev` already runs by default (server, Vite, logs…)
      and don't register duplicates. No `schedule:work` (nothing is scheduled).
- `.env.example`: document `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=database`,
  `REVERB_*`, `FILAMENT_REALTIME_SERVER`, and the `SEED_*` keys (B.4.1).
  In `.env`, only append missing keys; never change the owner's existing
  values.

### B.7 Realtime (admin and app panels)

Both panels register the socket plugin (verify, don't duplicate if the owner
already set it on `admin`):
`FilamentRealtimeDriverPlugin::make()->socket()->databaseNotifications()` on
`admin` (with `$panel->databaseNotifications()` too), and
`FilamentRealtimeDriverPlugin::make()->socket()` on `app` (no database
notifications there — nothing sends the user any).

Public channels, payload = ids only:

| Channel               | Event                  | Dispatched when                                               | Subscribed by                                                                                                          |
| --------------------- | ---------------------- | ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `collection_runs`     | `CollectionRunUpdated` | `CollectionRun` saved/deleted (model `booted()` hooks)        | Runs table (admin)                                                                                                     |
| `collection_run_{id}` | `CollectionRunUpdated` | that run or any of its `SourceRun`s saved                     | Run view page (infolist refresh via listener component) + its source runs relation manager table (admin)               |
| `job_postings`        | `JobPostingsUpdated`   | after each source run writes its postings (explicit dispatch) | Job postings table (admin) **and** the app panel's Today's jobs table                                                  |
| `sources`             | `SourceUpdated`        | `Source` saved/deleted                                        | Sources table (admin)                                                                                                   |

Plus database notifications (B.5 Finalize) arriving live in the admin bell.
No `->poll()` / `wire:poll` anywhere, on either panel.

### B.8 Admin panel (Filament, `/admin`)

Navigation groups: **Collection** (Runs, Job postings, Sources) and **Access**
(Users). Keep the default dashboard; no widgets.

**Sources** (`SourceResource`)

- Table: name, adapter (badge), identifier, active (toggle column), interval,
  last run at, last run status (badge). Filter by adapter / active.
- Form: name; adapter (select); identifier (required unless `remotive`,
  hidden/cleared for `remotive`); settings (key-value, only shown for
  `remotive`); interval_minutes (help: "Stored for future automatic
  collection; not used yet"); is_active.
- Delete only when the source has no postings/runs; otherwise deactivate
  (hide the delete action, explain via tooltip or disabled state).

**Runs** (`CollectionRunResource`, read-only — no create/edit forms)

- Header action **"Collect jobs now"**: `requiresConfirmation()` with a
  description listing how many active sources will run; calls
  `StartCollectionRun`; disabled (with tooltip) when a run is pending/running
  or no source is active; success/failure Filament notification.
- Table: label, status (colored badge), sources (succeeded/failed/total),
  jobs fetched, jobs new, triggered by, started at, finished at / duration.
  Default sort newest first. Realtime via `->socket('collection_runs', 'CollectionRunUpdated')`.
- Row actions: View; "View jobs" (opens Job postings filtered by this run);
  "Mark as failed" (B.5, only on pending/running).
- View page: infolist with run summary + relation manager of source runs
  (source, adapter, status, fetched, new, error message, timings), both
  realtime on `collection_run_{id}`.

**Job postings** (`JobPostingResource`, read-only)

- Table **grouped by run** by default (group on `collection_run_id`, group
  title = run label, newest run first), then `published_at` desc.
- Columns: title (searchable, wraps), company (searchable), location, remote
  (icon), source name, adapter (badge), published at, first seen at.
- Filters: run (select), source, adapter, "Today's runs only" toggle.
- Row actions: View (modal/infolist with fields + `description_text`), Open
  posting (external `url`, new tab).
- Realtime via `->socket('job_postings', 'JobPostingsUpdated')`.

**Users** (`UserResource`)

- Table: name, email, admin (icon), status (badge), created at. Filter status.
- Form: name, email (unique), password (required on create, optional on
  edit, hashed), is_admin, status (default `active`).
- Row actions: Block / Unblock (with confirmation), Edit.
- Guards: an admin cannot block themselves, remove their own admin flag, or
  delete themselves; the last active admin can't be blocked/demoted/deleted.

### B.9 User app (Filament panel `app`, `/app`)

- Second panel provider, e.g. `App\Providers\Filament\AppPanelProvider`:
  `id('app')`, `path('app')`, `->login()` (native Filament login — no
  registration, no password reset, no profile pages). Register
  `FilamentRealtimeDriverPlugin::make()->socket()` on it too (B.7).
- **Today's jobs** — the panel's home screen: a read-only, list-only view of
  job postings whose `collection_run` started **today in the app timezone**,
  ordered by `published_at` desc (nulls last), then id desc, **paginated**
  (25/page). Prefer a list-only resource (or a custom page backed by the same
  query) set as the panel's default/home page. Only the fields the UI needs.
    - Each item: title, company, location, remote badge, source label
      (e.g. "Greenhouse", "via Remotive"), published date (relative).
    - Row actions: View (modal/infolist with the item's fields) and "Open
      posting" (external `url`, new tab).
    - Empty state: "No jobs collected today yet."
    - No filters, no search, no create/edit/delete actions.
    - Realtime via `->socket('job_postings', 'JobPostingsUpdated')` (B.7) —
      this page **does** update live, unlike the rest of this section, which
      has none of the other admin-panel affordances.
- No settings/profile pages on this panel.

### B.10 Acceptance criteria

- **AC01** — Registration is unavailable on both panels (no registration
  feature, no route, no link); a new user can only be created from the admin
  Users resource.
- **AC02** — Only active admins can access `/admin`; active non-admins are
  denied on `/admin` but can access `/app`; blocked users are denied on both
  panels via `canAccessPanel()`, including a user blocked mid-session (their
  next navigation to either panel is denied).
- **AC03** — Admin can create, edit, block and unblock users; self-block,
  self-demotion, self-delete and removing the last active admin are
  prevented.
- **AC04** — Admin can create/edit/activate/deactivate sources for the four
  adapters with the identifier/settings validation in B.8; interval is
  stored; a source with history can't be deleted.
- **AC05** — Migrations and `php artisan db:seed` have been run on the local
  DB. Seeding is idempotent and creates the starter source, an active
  admin and an active client user from the `SEED_*` env keys (via
  `config/talent.php`). The admin logs in at `/admin` and `/app`; the
  client logs in at `/app` and is denied at `/admin`.
- **AC06** — "Collect jobs now" requires confirmation, creates exactly one run
  with one source run per active source, and is blocked while another run is
  pending/running or when no source is active.
- **AC07** — Each adapter fetches and maps real postings into `job_postings`;
  re-running does not duplicate postings (`source_id + external_id`), keeps
  `collection_run_id` as the first run that saw them, and updates
  `last_seen_*`.
- **AC08** — A failing source (e.g. an invalid identifier) marks only its
  source run `failed` with an error message; other sources still complete;
  the run ends `partial` (or `failed` if all failed).
- **AC09** — On finish, run counters and status are correct and the
  triggering admin receives a database notification that appears live
  (no reload, no polling).
- **AC10** — Runs, run detail (source runs) and Job postings tables (admin)
  update live over the realtime driver while a run is in progress, and so
  does the app panel's Today's jobs table; no `->poll()` / `wire:poll` exists
  in the codebase.
- **AC11** — Admin Job postings are grouped by run and filterable by run,
  source, adapter and "today's runs".
- **AC12** — "Mark as failed" unblocks a stuck run.
- **AC13** — The app panel's Today's jobs screen (`/app`) shows only postings
  first discovered by runs started today (app timezone), paginated, with the
  fields in B.9, and an empty state when there are none.
- **AC14** — `composer dev` starts everything needed locally (app server,
  Vite if the panels' theme requires it, Reverb, the queue worker covering
  `collection`) in one terminal.
- **AC15** — The harness from Part A exists; the no-git-writes rule is in
  `CLAUDE.md`, every agent file, and enforced by `.claude/settings.json`
  deny rules. No commit was made.

### B.11 Verification

#### B.11.1 Agent smoke test (backend, no browser)

1. `php artisan migrate` and `php artisan db:seed` (already done in 0.3 step
   6; run `db:seed` once more to prove idempotency — row counts unchanged).
2. In `php artisan tinker --execute`, call `StartCollectionRun` as the seeded
   admin user.
3. `php artisan queue:work database --queue=collection,default --stop-when-empty`.
4. Query and report: run status/counters, source run statuses, postings per
   source. Run steps 2–3 again and show `jobs_new` ≈ 0 and no duplicates.
5. Failure path: create a source named `Invalid (smoke test)` (greenhouse,
   identifier `this-board-does-not-exist-talent-labs`), run steps 2–3 again,
   confirm the run ends `partial` and that source run has an error message.
   Then **deactivate** it (it now has history, so it can't be deleted) and
   tell the owner it exists.

#### B.11.2 Owner's manual checklist (include in the final report)

1. `composer dev`.
2. Log in to `/admin` with `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD`, open
   Runs, click "Collect jobs now", confirm.
3. Without reloading: run row goes pending → running → completed/partial;
   Job postings table fills in; bell notification arrives.
4. Open the run: source runs update live.
5. In another browser, log in at `/app/login` with `SEED_CLIENT_EMAIL` /
   `SEED_CLIENT_PASSWORD` → `/app` lists today's jobs. `/admin` is denied for
   this user.
6. Block the client from admin → their next attempt to access `/app` is
   denied. Unblock → they can access `/app` again.
7. Create a new user in Users → they can log in at `/app/login`.

### B.12 Out of scope (do not build)

Email templates, sending (manual or automatic), contact discovery/SMTP
verification, matching/fit score, user profiles/filters, normalization or
AI extraction, automatic/scheduled collection (interval is stored only),
public registration, checkout, plans/billing, LinkedIn or HTML scraping,
dashboards/widgets, tests (unless the owner asks), deploy/production config,
**Inertia/React pages** (reserved for future non-Filament pages, not part of
this MVP). Anything not stated in Part B.
