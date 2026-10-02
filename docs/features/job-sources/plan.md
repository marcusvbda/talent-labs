# Plan — job-sources

Source spec: `docs/features/job-sources/spec.md` · SHA-256 `9b47fafe3b78246cc658b150b27fb52687f12851373dfa56ea0cf1cf20c1f88f`
Product truth: same file (sections "Daily batch and availability window", "New job sources", "Owner decisions", "Acceptance criteria").
Run phases with `/execute-phases docs/features/job-sources/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                                     | Role            | Depends on | Size | Status  |
| ----- | --------------------------------------------------------- | --------------- | ---------- | ---- | ------- |
| 1     | Availability window on the client pool (D3)               | laravel-backend | none       | S    | DONE    |
| 2     | "Today" = first run started today, app timezone (D1)      | laravel-backend | 1          | M    | PENDING |
| 3     | `source_contacts` on postings (storage + DTO + upsert)    | laravel-backend | none       | M    | PENDING |
| 4     | Hacker News: extract emails into source contacts          | laravel-backend | 3          | S    | PENDING |
| 5     | New source: Y Combinator (jobs + founders)                | laravel-backend | 3          | M    | PENDING |
| 6     | Admin: show source contacts + Y Combinator settings hint  | filament-admin  | 3, 5       | S    | PENDING |
| 7     | Verification and report                                   | qa-tester       | 1–6        | S    | PENDING |

## Audit — 2026-10-02

| Check | Result |
| ----- | ------ |
| Stack | Laravel 13, Filament 5, PostgreSQL, database queue (per `CLAUDE.md`). No new package needed. |
| Existing adapters | `SourceAdapter` already has `himalayas`, `we_work_remotely`, `working_nomads`, `hacker_news` (built in `docs/features/targeted-sourcing/plan.md` phases 5–8), seeded in `database/seeders/SourceSeeder.php` with `sourceLabel()` "via …" credits. Spec section "3. Remote job boards" is already implemented → AC8 is verification only (Phase 7). |
| Hacker News | `app/Collection/Adapters/HackerNewsAdapter.php` reads the Algolia HN API (`hn.algolia.com/api/v1/search_by_date` + `/items/{id}`), public, no login. Title/company come from the first comment line split on `\|` (company = segment 0 without URLs, title = first role-like segment, posts without one skipped). Links already extracted: `applyUrl` (first non-HN href), `companyWebsite`. **Emails are not extracted.** |
| Y Combinator | No official API. Public pages are Inertia pages with JSON in the `data-page` attribute. `GET https://www.ycombinator.com/jobs/role/{slug}` → `props.jobPostings` (20 items; keys `id, title, url, applyUrl, location, type, role, roleSpecificType, prettyRole, salaryRange, minExperience, skills, companyUrl, companyName, companyOneLiner, hiringManager, createdAt` — `createdAt` is relative, e.g. `"14 days"`; `applyUrl` needs a YC account login). `props.jobRoles` slugs: `software-engineer, designer, product-manager, recruiting-hr, sales-manager, marketing, support, operations, science`. `GET https://www.ycombinator.com/companies/{slug}` → `props.company.website`, `props.company.founders[]` (`full_name, title, has_email, …` — no email). `robots.txt` disallows `/companies?*` only. No job description text on these pages. Terms of use not verified; owner accepted the risk (D5). |
| Contacts model | `contacts` is company-level (`company_id, email, local_part, confidence`); recipients only `OutreachLimits::RECIPIENT_PRIORITY` (`careers, jobs, hr, talent, recruiting, people`). Per D4/D6 source contacts go on the posting instead, admin-only. |
| `job_postings` | Create migration `database/migrations/2026_09_23_120002_create_job_postings_table.php`; has `collection_run_id` (first run, indexed), `first_seen_at`, `company_website`, `raw` json; unique `(source_id, external_id)`. No column for contacts. |
| Upsert | `app/Jobs/FetchJobsFromSource.php` `JobPosting::upsert(..., MUTABLE_COLUMNS)`; never touches `collection_run_id`/`first_seen_at` → AC2 already holds. |
| Pool | `app/Outreach/Queries/MatchingJobPostings::forUser()` is the single source of truth; **no time limit today**. Company-level application exclusion (any status) already matches D2. Applications table unique `(user_id, company_id)`. |
| "Today" | `app/Client/JobPoolQuery.php` `todayStartsAt(User)` uses `first_seen_at` in the **user's** timezone; used by the `today` filter, `JobCardResource` (`collectedToday`), `JobsPayload` summary and `DashboardPresenter` `newToday`. Conflicts with D1. |
| JobCardResource callers | `app/Client/JobsPayload.php` (`with(['profile','company'])`), `app/Client/DashboardPresenter.php` (`with(['profile','company'])`), `app/Http/Controllers/Client/Internal/JobsController.php` (`with(['profile','company','source'])`, via `JobDetailResource`), `app/Client/ReviewDraftPresenter.php`. `JobPosting::collectionRun()` relation exists. |
| Other day logic | `QueueRandomApplications` filters `first_seen_at >= now - OutreachLimits::MAX_POSTING_AGE_DAYS (14)` — becomes redundant under a 5-day window; left untouched (scope). `DashboardPresenter::collected()` period counts by `first_seen_at` — left untouched (not a "today" surface). |
| Config | `config/talent.php` `collection` has only `target_role_families`. No `COLLECTION_*` key in `.env.example`. |
| Migrations | Owner preference (memory): new columns fold into the table's create migration; the **owner** runs `php artisan migrate:fresh --seed`. Agents never run it. |
| Tests | `tests/Feature/Outreach/*` (3 files) + examples; none touch the pool, runs or adapters. |
| Commands | `composer lint` / `lint:check` / `types:check` / `test` / `ci:check`; `yarn check`, `yarn types:check`. `php artisan test --compact --filter=…`. |

## Owner decisions

All resolved on 2026-10-02 and recorded in the spec ("Owner decisions" D1–D6). Nothing is blocked.

## Global constraints (every phase)

- `CLAUDE.md` hard rules: no git writes (only `/execute-phases` commits); no
  migrate:fresh/refresh/reset/rollback, db:wipe, DROP, TRUNCATE; no package
  changes; no new or edited tests; English only; realtime, never `->poll()`;
  scope = the phase contract.
- Schema changes go into the **create** migration (owner preference). After
  Phase 3 the owner must run `php artisan migrate:fresh --seed`; say so in the
  phase report. Do not create `add_*_to_*` migrations.
- Job-collection invariants (skill `job-collection`): adapters are direct
  field mapping only (no AI/NLP), `raw` keeps the full item, HTTP through
  `InteractsWithJobBoardApi::http()`, at most `MAX_REQUESTS_PER_RUN` (10)
  requests per source per run, `pause()` between requests, 429 handled with
  `getOrNullWhenRateLimited()` + `stopOnRateLimit()`. Never change
  `collection_run_id` / `first_seen_at` of an existing posting. Realtime
  events once per source run, never per posting.
- `MatchingJobPostings::forUser()` stays the single source of truth for the
  pool; do not duplicate the window logic elsewhere.
- No X/Twitter, Hunter/Apollo, Reddit, relocation/visa or funding-news
  sources (AC9). No guessed or SMTP-probed emails from founder names (D6).
  Source contacts are never used as application recipients (D4).
- If an existing test fails only because of a behaviour this plan
  intentionally changes, stop and report; do not edit the test.

## Acceptance-criteria coverage

| AC  | Phases |
| --- | ------ |
| AC1 | 2, 7   |
| AC2 | 7 (already holds; verified) |
| AC3 | 1, 7   |
| AC4 | 1, 7   |
| AC5 | 1 (kept unchanged), 7 |
| AC6 | 3, 4, 6, 7 |
| AC7 | 3, 5, 6, 7 |
| AC8 | 7 (already built; verified) |
| AC9 | 7      |

## Phases

### Phase 1 — Availability window on the client pool

Status: DONE
Evidence: tinker raw SQL has `first_run.started_at >= '2026-09-28 00:00:00'` (today − 4 days, app tz); `composer lint:check`, `composer types:check` (0 errors), `php artisan test --compact` (33 passed) green; code-reviewer APPROVED.
Role: laravel-backend · Depends on: none · Covers: AC3, AC4, AC5 · Size: S
Spec: "Availability window", "What leaves the available list", D2, D3

**Goal.** The client pool only holds postings whose first run started inside
the last N app-timezone days (today included), on top of every existing pool
rule.

**Contract.**

- `config/talent.php` → `collection.window_days` = `(int) env('COLLECTION_WINDOW_DAYS', 5)`,
  with a comment: "Days (today included, app timezone) a posting stays in the
  client pool, counted by the started_at of the run that first collected it."
- `.env.example`: append `COLLECTION_WINDOW_DAYS=5` next to the other
  collection/matching keys. Do not touch `.env`.
- New `app/Collection/Support/PostingDay.php` (`final class`, static):
  - `todayStartsAt(): CarbonImmutable` → `CarbonImmutable::now(config('app.timezone'))->startOfDay()`.
  - `windowStartsAt(): CarbonImmutable` → `todayStartsAt()->subDays(max(1, (int) config('talent.collection.window_days')) - 1)`.
  - `isToday(?CarbonInterface $runStartedAt): bool` → non-null and `>= todayStartsAt()`.
- `MatchingJobPostings::forUser()`: add
  `->join('collection_runs as first_run', 'first_run.id', '=', 'job_postings.collection_run_id')`
  and `->where('first_run.started_at', '>=', PostingDay::windowStartsAt())`.
  Keep `select('job_postings.*')`. Add one docblock sentence on the window.
  Every other rule stays, including the company exclusion for any
  application status (D2).
- Not changed: `QueueRandomApplications` 14-day filter (now redundant with a
  5-day window), `DashboardPresenter::collected()`.

**Steps.**

1. Add config key and `.env.example` line.
2. Create `PostingDay`.
3. Apply the window in `MatchingJobPostings`.

**Done when.**

- In tinker: `MatchingJobPostings::forUser($client)->toRawSql()` contains
  `first_run.started_at >=` with today 00:00 (app tz) minus 4 days.
- With `config(['talent.collection.window_days' => 1])` in tinker the pool only
  holds postings whose first run started today.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** The "today" filter/badge (Phase 2).

### Phase 2 — "Today" = first run started today, app timezone

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: AC1 · Size: M
Spec: "Daily batch accumulates", D1

**Goal.** Every "today" surface counts a posting by the `started_at` of its
first run in the app timezone, so several runs on the same day add up.

**Contract.**

- `JobPoolQuery`: the `today` filter becomes
  `where('first_run.started_at', '>=', PostingDay::todayStartsAt())` (the
  `first_run` join comes from Phase 1). Remove `todayStartsAt(User)`.
- `JobCardResource`: `'collectedToday' => PostingDay::isToday($posting->collectionRun?->started_at)`.
  `firstSeenAt` stays. Frontend contract unchanged (`collectedToday` boolean).
- Eager-load `collectionRun` wherever postings go through `JobCardResource`:
  `JobsPayload` and `DashboardPresenter` (`with([... 'collectionRun'])`),
  `JobsController` (`with([... 'collectionRun'])`), `ReviewDraftPresenter`
  (`loadMissing('collectionRun')`).
- `JobsPayload` summary `collectedToday` and `DashboardPresenter` `newToday`
  follow automatically through `JobPoolQuery`.

**Steps.**

1. Switch the `JobPoolQuery` filter and remove the old helper.
2. Update `JobCardResource` and the four callers' eager loads.
3. `grep -rn "todayStartsAt" app` matches only `PostingDay` and `JobPoolQuery`'s new call.

**Done when.**

- Tinker: with two runs started today (app tz), `JobPoolQuery::forUser($client, ['today' => true])->count()`
  includes postings from both.
- `GET /app/jobs` (client) renders; `summary.collectedToday` equals the today count.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** Source contacts, adapters, admin.

### Phase 3 — `source_contacts` on postings

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: AC6, AC7 (storage) · Size: M
Spec: "1. Hacker News" (contact kept), "2. Y Combinator" (founders), D4, D6

**Goal.** A posting can carry the human contacts its source exposes, stored
without ever being lost on a later run.

**Contract.**

- New enum `app/Enums/SourceContactKind.php` (string-backed, `HasLabel`):
  `Email = 'email'` ("Email"), `Founder = 'founder'` ("Founder"),
  `HiringManager = 'hiring_manager'` ("Hiring manager").
- New `app/Collection/Data/SourceContactData.php` (`final readonly`):
  `__construct(public SourceContactKind $kind, public ?string $name = null, public ?string $title = null, public ?string $email = null)`
  and `toArray(): array{kind: string, name: string|null, title: string|null, email: string|null}`.
- `JobPostingData`: new last constructor param
  `public array $sourceContacts = []` (`@param list<SourceContactData> $sourceContacts`).
  Existing adapters keep compiling unchanged.
- Create migration `2026_09_23_120002_create_job_postings_table.php`: add
  `$table->jsonb('source_contacts')->nullable();` after `company_website`,
  with comment "Human contacts exposed by the source (HN emails, YC founders). Admin-only; never recipients."
- `JobPosting` model: `source_contacts` in `#[Fillable]`, cast `'array'`,
  `@property list<array{kind: string, name: string|null, title: string|null, email: string|null}>|null $source_contacts`.
- `FetchJobsFromSource`: row key `'source_contacts' => $item->sourceContacts === [] ? null : json_encode(array_map(fn (SourceContactData $c) => $c->toArray(), $item->sourceContacts))`.
  On update it must **not** wipe stored contacts when a run brings none: pass
  the update columns so that `source_contacts` uses
  `DB::raw('coalesce(excluded.source_contacts, job_postings.source_contacts)')`
  (Laravel `upsert` accepts an associative update array). All other
  `MUTABLE_COLUMNS` behave as before.

**Steps.**

1. Enum + DTO; extend `JobPostingData`.
2. Edit the create migration; update the model.
3. Upsert change in `FetchJobsFromSource`.
4. Report that the owner must run `php artisan migrate:fresh --seed`.

**Done when.**

- `php artisan migrate:status` shows no new migration file (column folded in).
- After the owner's `migrate:fresh --seed`: `\Schema::hasColumn('job_postings', 'source_contacts')` is true.
- A collection run with existing adapters still completes (no adapter sends contacts yet).
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** Filling contacts from any adapter; admin display.

### Phase 4 — Hacker News: extract emails into source contacts

Status: PENDING
Role: laravel-backend · Depends on: 3 · Covers: AC6 · Size: S
Spec: "1. Hacker News", D4

**Goal.** Each HN posting keeps the direct emails written in its comment.

**Contract.**

- `HackerNewsAdapter::map()` passes `sourceContacts: $this->emails($html)`
  mapped to `new SourceContactData(SourceContactKind::Email, email: $email)`.
- `emails(string $html): list<string>` (private), deterministic:
  1. Collect `mailto:` hrefs (strip query string).
  2. From the decoded plain text (`htmlToText`), de-obfuscate only bracketed
     forms `[at]`, `(at)`, `{at}`, ` at ` inside brackets, and `[dot]`,
     `(dot)`, `{dot}` (case-insensitive) — no bare-word " at " rewriting.
  3. Match `/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i`, trim trailing
     `.,;:)`, lowercase, keep only `filter_var(..., FILTER_VALIDATE_EMAIL)`.
  4. Drop `@news.ycombinator.com` / `@ycombinator.com`; unique, order kept,
     at most 5.
- Links stay as today (`applyUrl`, `companyWebsite`). Class docblock gains
  one line on email extraction.

**Steps.**

1. Add `emails()` and wire it in `map()`.
2. Smoke in tinker: run the adapter against a `Source` model for
   `hacker_news` (read-only HTTP) and count postings with ≥ 1 email.

**Done when.**

- Live fetch returns > 0 postings and at least one has an `email` source contact.
- A comment containing `jobs [at] acme [dot] io` yields `jobs@acme.io` (tinker, via reflection or a quick fixture string).
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Using emails as recipients (D4); admin display.

### Phase 5 — New source: Y Combinator

Status: PENDING
Role: laravel-backend · Depends on: 3 · Covers: AC7 · Size: M
Spec: "2. Y Combinator", D5, D6

**Goal.** A seeded, inactive-by-default `y_combinator` source that collects
public YC job postings with founder names/titles and the company website.

**Contract.**

- `SourceAdapter`: case `YCombinator = 'y_combinator'`; label `Y Combinator`;
  color `gray`; `requiresIdentifier()` false; `sourceLabel()` `via Y Combinator`;
  `adapter()` → `YCombinatorAdapter`.
- New `app/Collection/Adapters/YCombinatorAdapter.php` using
  `InteractsWithJobBoardApi`:
  - Setting `roles` (`settingList`), default
    `['software-engineer', 'product-manager', 'support']` when empty.
  - Budget: total requests ≤ `MAX_REQUESTS_PER_RUN` (10). Role pages first
    (`GET https://www.ycombinator.com/jobs/role/{slug}`, header
    `Accept: text/html`, via `getOrNullWhenRateLimited`), then company pages
    with the remaining budget. `pause()` between every request.
  - Page data: `preg_match('/data-page="([^"]+)"/', $body)` →
    `json_decode(html_entity_decode(..., ENT_QUOTES | ENT_HTML5), true)`.
    Missing/invalid on a role page → `RuntimeException('Y Combinator page has no data-page payload')`;
    on a company page → skip enrichment for that company.
  - Postings from `props.jobPostings`, de-duplicated by `id` across roles.
  - Company pages: distinct `companyUrl` values (`/companies/{slug}`),
    shuffled, fetched until the budget runs out:
    `https://www.ycombinator.com{companyUrl}` → `props.company.website`,
    `props.company.founders[]`.
  - Mapping (`JobPostingData`):
    `externalId` = `(string) id`; `title`; `companyName` = `companyName` ??
    source name; `location`; `isRemote` = location matches `/remote/i`;
    `department` = `prettyRole`; `employmentType` = `type`;
    `url` = `https://www.ycombinator.com{url}`; `applyUrl` = same public URL
    (the YC `applyUrl` needs a login — out of scope);
    `descriptionHtml` = null; `descriptionText` = plain lines built from the
    payload: `companyOneLiner`, `Role: {prettyRole} · {roleSpecificType}`,
    `Location`, `Experience: {minExperience}`, `Skills: {comma list}`,
    `Salary: {salaryRange}` (skip empty ones); `publishedAt` = `createdAt`
    parsed as `^(\d+)\s+(minute|hour|day|week|month|year)s?$` subtracted from
    now, else null; `raw` = the posting item plus `company` (website +
    founders) when fetched; `companyWebsite` = company page `website`;
    `sourceContacts` = one `Founder` per founder with `full_name` (name) and
    `title`, plus one `HiringManager` when `hiringManager` is an array with a
    `name`/`full_name` (and optional `title`). No emails.
  - Throw if every role page was rate-limited before anything was collected
    (`stopOnRateLimit`).
- `SourceSeeder`: add
  `['name' => 'Y Combinator', 'adapter' => SourceAdapter::YCombinator, 'identifier' => null, 'settings' => ['roles' => 'software-engineer,product-manager,support'], 'is_active' => false]`
  (existing `firstOrCreate` keeps it idempotent).

**Steps.**

1. Enum case and match arms.
2. Adapter.
3. Seeder line; run `php artisan db:seed --class=SourceSeeder` (idempotent).
4. Tinker smoke: `SourceAdapter::YCombinator->adapter()->fetch($source)` (read-only HTTP).

**Done when.**

- Live fetch returns > 0 postings; at least one has `companyWebsite` and a
  `Founder` source contact with a name; no request count above 10.
- Seeded source exists, `is_active = false`, label "via Y Combinator".
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Admin hint text and contact display (Phase 6).

### Phase 6 — Admin: source contacts + Y Combinator settings hint

Status: PENDING
Role: filament-admin · Depends on: 3, 5 · Covers: AC6, AC7 (visibility) · Size: S
Spec: D4, D6

**Goal.** Admins see a posting's source contacts, and know which settings the
Y Combinator source reads.

**Contract.**

- `app/Filament/Resources/JobPostings/Schemas/JobPostingInfolist.php`: a
  section "Source contacts" (visible only when `source_contacts` is non-empty)
  with a `RepeatableEntry::make('source_contacts')` showing `kind` (label from
  `SourceContactKind`), `name`, `title`, `email` (email copyable, placeholders
  `—`). Helper text: "Contacts exposed by the source. Not used as application recipients."
- Source form settings hint (added in `docs/features/targeted-sourcing/plan.md` Phase 3): append
  "Y Combinator: roles (comma list of /jobs/role slugs, e.g. software-engineer,product-manager,support)."
- No polling; existing realtime listeners unchanged.

**Steps.**

1. Locate the settings hint string (`grep -rn "Hacker News: none" app/Filament`).
2. Add the infolist section and the hint text.

**Done when.**

- A job posting view with HN emails or YC founders shows the section; one
  without shows nothing.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Client-facing display (out of scope per D4/D6).

### Phase 7 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–6 · Covers: AC1–AC9 · Size: S
Spec: whole spec

**Goal.** Prove every AC and hand the owner a manual checklist.

**Steps.**

1. Full gate: `composer ci:check`.
2. Live adapter smoke (tinker, read-only HTTP): Hacker News, Y Combinator,
   Himalayas, We Work Remotely, Working Nomads each return > 0 postings;
   their `sourceLabel()` is "via …"; `url` is the board's original link (AC6–AC8).
3. Window/day checks in tinker against the local DB (read-only queries):
   - AC1: postings from two runs started today (app tz) both count in
     `JobPoolQuery::forUser($client, ['today' => true])`.
   - AC2: `select source_id, external_id, count(*) … having count(*) > 1` is empty;
     a re-seen posting keeps its `collection_run_id`.
   - AC3/AC4: no pool posting has `first_run.started_at < PostingDay::windowStartsAt()`;
     every eligible posting inside the window is present regardless of the
     client's last application date.
   - AC5: no pool posting belongs to a company the client has an application for.
4. Forbidden-pattern greps: `->poll(` / `wire:poll` in changed files;
   `twitter|x\.com|hunter|apollo|reddit` in `app/Enums/SourceAdapter.php` and
   `app/Collection/Adapters` (only the existing HN `NON_COMPANY_HOSTS` deny-list may match);
   `add_.*_to_job_postings` migrations (none); `RECIPIENT_PRIORITY` unchanged.
5. AC walkthrough table with evidence; report deviations.

**Done when.**

- `composer ci:check` passes.
- Every AC has evidence in the report.
- Owner manual checklist delivered:
  1. Run `php artisan migrate:fresh --seed` (needed after Phase 3).
  2. Activate the Y Combinator source in admin, start a run, open a YC posting
     → "Source contacts" lists founders; company website set.
  3. Open an HN posting with an email → listed under "Source contacts".
  4. Client app: Jobs "today" count matches postings whose first run started
     today (app timezone); postings older than 5 days are gone from the list.
  5. Set `COLLECTION_WINDOW_DAYS=2`, `php artisan config:clear`, confirm the
     pool shrinks; restore.

**Not in this phase.** Any code change beyond fixing what the gate finds in
this feature's own files.
