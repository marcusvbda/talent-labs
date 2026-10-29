# Plan — Match Ranking

Spec: `docs/features/match-ranking/spec.md` · SHA-256 `b4bd82ff96270ee00f2e7c6681f1f9e92917ddd88557e2e90e6f2a5cbd90b2ca`
Run phases with `/exec-phase match-ranking <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — 2026-10-29

Implemented: 0 · Partial: 0 · Divergent: 0 · Missing: 7 · Unverifiable: 0

| Requirement                                            | State                   | Evidence                                                                                                                                                                                                                                                                                |
| ------------------------------------------------------ | ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| R1 Score per pool posting                              | Missing                 | `app/Outreach/Queries/MatchingJobPostings.php` is pass/fail only. `app/Client/StackHighlight.php` (`stackMatches`) highlights tags and scores nothing.                                                                                                                                  |
| R2 Readable explanation                                | Missing                 | `app/Http/Resources/Client/JobCardResource.php` and `JobDetailResource.php` have no breakdown.                                                                                                                                                                                          |
| R3 Order by match                                      | Missing                 | `app/Client/JobPoolQuery.php` always orders `first_seen_at desc, id desc`. `JobFiltersRequest` has no `sort`.                                                                                                                                                                           |
| R4 "N% match" on row + breakdown in detail             | Missing                 | `resources/js/components/patterns/job-row.tsx`, `resources/js/features/jobs/job-detail-sheet.tsx`                                                                                                                                                                                       |
| R5 Gate unchanged                                      | Implemented (invariant) | The gate lives in `MatchingJobPostings::forUser`, and this plan never edits it.                                                                                                                                                                                                         |
| R6 Jobs list only, newest default, opt-in "Best match" | Missing                 | `resources/js/features/jobs/job-filters.tsx`, `resources/js/lib/use-query-filters.ts`                                                                                                                                                                                                   |
| R7 Weights                                             | Missing                 | —                                                                                                                                                                                                                                                                                       |
| R8 Settled by code                                     | Implemented             | Profile `done` is required by the gate. `jobs.collected` → `jobsCollected()` in `resources/js/data/realtime/handlers.ts` and the preference save in `use-preferences.ts` both invalidate `keys.jobs.all()`. The `plan.jobs` middleware limits the Jobs list to `canChooseJobs()` plans. |

Code not backed by the spec (keep it): `stackMatches` and the matching-tags-first
stack order on the job card and in the detail.

## Status board

| Phase | Title                                                     | Role             | Depends on | Origin             | Size | Status  |
| ----- | --------------------------------------------------------- | ---------------- | ---------- | ------------------ | ---- | ------- |
| 1     | Match score, explanation and `sort=match` in the job pool | laravel-backend  | none       | R1, R2, R3, R5, R7 | M    | DONE    |
| 2     | Contract, fixtures and "N% match" chip on Jobs rows       | inertia-frontend | 1          | R1, R4             | M    | DONE    |
| 3     | Match breakdown in the job detail sheet                   | inertia-frontend | 2          | R2, R4             | S    | PENDING |
| 4     | "Best match" sort control on the Jobs list                | inertia-frontend | 2          | R3, R6             | S    | PENDING |
| 5     | Verification and report                                   | qa-tester        | 1–4        | all                | S    | PENDING |

## Owner decisions

### D1 — Score format

Answer (2026-10-29): an integer percentage plus a breakdown. Recorded as spec R4.

### D2 — Eligibility gate

Answer: keep the current gate and rank inside it. Recorded as spec R5.

### D3 — Where the ranking applies

Answer: the Jobs list only, as an opt-in "Best match" sort. Newest first stays
the default. Random sends stay random. Recorded as spec R6.

### D4 — Weights

Answer: Technologies 50 / Seniority 25 / Remote-location 15 / Title 10, with
empty criteria left out and the rest re-normalised. Recorded as spec R7.

### Assumptions (owner may override; still listed in spec "Open points")

- **A1.** The score appears only on Jobs list rows and in the job detail
  sheet. The dashboard "new matches" card (`new-matches-card.tsx`) and the
  preferences preview don't change. Their payloads may carry `match`, but the
  UI ignores it.
- **A2.** The EN/PT copy is the copy proposed in Phases 2–4.
- **A3.** The score is computed when the list is read, in SQL, inside
  `JobPoolQuery`. Nothing is stored, so no migration, no job and no new
  realtime event are needed.

## Global constraints (every phase)

- Never change the pool membership. `MatchingJobPostings::forUser` is **not
  edited**, and the score only orders and explains (R5).
- No `->poll()`, `wire:poll`, `$pollingInterval` or `refetchInterval`. The
  freshness comes from the existing `jobs.collected` invalidation and the
  preference-save invalidation.
- No new composer/npm packages. No migrations. No git writes from subagents.
  Don't write or modify tests (none are requested).
- The product is EN/PT only: add each new string to **both** `lang/en.json` and
  `lang/pt.json`, never Spanish.
- Public channels carry no new payloads. No realtime changes in this feature.
- React Compiler is on: no speculative `useMemo`/`useCallback`/`React.memo`.
- `JobCard` stays client-safe: no descriptions, contacts or raw payloads.
- Frontend data goes through the existing hooks (`useJobs`, `useJob`). Fixture
  mode (`VITE_USE_FIXTURES=true`) must keep working, so each contract change
  is mirrored in `resources/js/data/fixtures/`.

## Requirement coverage

| Requirement | Phases                |
| ----------- | --------------------- |
| R1          | 1, 2                  |
| R2          | 1, 3                  |
| R3          | 1, 4                  |
| R4          | 2, 3                  |
| R5          | 1 (invariant), 5      |
| R6          | 4                     |
| R7          | 1                     |
| R8          | 5 (verified, no code) |

## Phases

### Phase 1 — Match score, explanation and `sort=match` in the job pool

Status: DONE
Evidence: pint clean on the 5 changed files; `php artisan test --compact` 33/33 passed; code-reviewer APPROVED; smoke-checked on the local DB (sort=match non-increasing, cursor pagination without overlap, pool count identical with/without sort and equal to the gate, `match: null` without scored preferences or for review drafts). `composer types:check` / `composer lint:check` report only errors that already exist in untouched files (`database/seeders/SourceSeeder.php:43`, `config/filament.php`, `app/Providers/Filament/AdminPanelProvider.php`).
Role: laravel-backend · Depends on: none · Covers: R1, R2, R3, R5, R7 · Size: M
Origin: spec x code gap (new feature; Requirements R1–R3, R5, R7)

**Goal.** Every posting returned by `JobPoolQuery` carries a `match_score`
computed in SQL. `JobCard` exposes `match` (score plus breakdown).
`GET /jobs` and `GET /internal/jobs` accept `sort=match`.

**Gap -> desired.** Today the pool is pass/fail and newest first. After this
phase, `JobPoolQuery::forUser()` wraps the filtered pool in a derived table
that adds `match_score` and `match_location_hit`. It orders by
`match_score desc` first when `sort=match`, and the resource adds `match`.

**Contract.**

- New `app/Client/MatchScore.php` (`final class MatchScore`):
    - `public const array WEIGHTS = ['stack' => 50, 'seniority' => 25, 'location' => 15, 'title' => 10];`
      (the order is also the display order).
    - `public static function criteria(PreferenceCriteria $criteria): array`
      returns `list<string>`: the applicable keys, in WEIGHTS order:
        - `stack` when `$criteria->stack !== []`
        - `seniority` when `$criteria->seniorities !== []`
        - `location` when `remoteMode === RemoteMode::RemoteOnly`, or when
          (`remoteMode !== RemoteMode::Any` and `locations !== []`)
        - `title` when `$criteria->titles !== []`
    - `public static function addSelects(Builder $query, PreferenceCriteria $criteria): Builder`
      appends two columns with `selectRaw($sql, $bindings)`. The query must
      be the `MatchingJobPostings` builder, which already joins
      `job_posting_profiles` and selects `job_postings.*`.
        - `match_score` (int). No applicable criterion → `0 as match_score`.
          Otherwise `round(( <parts> ) * 100 / <sum of applicable weights>)::int as match_score`,
          where each part is numeric:
            - stack: `50.0 * (select count(*) from unnest(array[?, ...]::text[]) as s(v) where jsonb_exists(job_posting_profiles.stack, s.v)) / N`.
              N is `count($criteria->stack)`, and the bindings are `$criteria->stack`
              (already normalised by `StackNormalizer`, like the profile stack).
              Use `jsonb_exists`, **not** the `?` operator (it clashes with
              PDO placeholders).
            - seniority: `25.0 * (case when job_posting_profiles.seniority in (?, ...) then 1 when job_posting_profiles.seniority = 'unknown' then 0.5 else 0 end)`
            - location: `15.0 * (case when <fit> then 1 else 0 end)`, where `<fit>` is:
                - remote_only: `job_posting_profiles.is_remote is true`
                - remote_or_locations: `job_posting_profiles.is_remote is true or (<location hit>)`
                - locations_only: `(<location hit>)`
            - title: `10.0 * (case when (<title hit>) then 1 else 0 end)`, where `<title hit>`
              ORs `(job_postings.title ~* ? or job_posting_profiles.normalized_title ~* ?)`
              per title, with `WordPattern::toRegex($title)` bound twice (the same
              expression the gate uses).
        - `match_location_hit` (bool or null): when `$criteria->locations !== []` and
          `remoteMode !== Any`, it is `(<location hit>) as match_location_hit`;
          otherwise `null::boolean as match_location_hit`. `<location hit>` ORs, per
          location, `(job_postings.location ~* ? or exists (select 1 from jsonb_array_elements_text(job_posting_profiles.locations) as l(v) where l.v ~* ?))`
          with `WordPattern::toRegex($location)` bound twice. This is the same
          expression as `MatchingJobPostings::orLocationMatch`, but copied, not
          called: that method is private, and the gate stays untouched.
    - `public static function explain(JobPosting $posting, PreferenceCriteria $criteria): ?array`
      returns null when `$posting->getAttribute('match_score') === null` (the
      posting was not loaded through `JobPoolQuery`, e.g.
      `ReviewDraftPresenter`) or when `criteria()` is empty. Otherwise it
      returns `['score' => (int) match_score, 'criteria' => list]`, with one
      entry per applicable key, in WEIGHTS order:
        - `['key' => 'stack', 'result' => 'met'|'partial'|'missed', 'matched' => list<string>, 'missing' => list<string>]`.
          `matched` is the requested technologies (in preference order) that are
          in `profile->stack`, and `missing` is the rest. `result` is `met` when
          `missing === []`, `partial` when `matched !== []`, and `missed` otherwise.
        - `['key' => 'seniority', 'result' => 'met'|'not_stated'|'missed']`:
          `met` when `profile->seniority` is in `$criteria->seniorities`,
          `not_stated` when it is `'unknown'` (or null), `missed` otherwise.
        - `['key' => 'location', 'result' => 'remote'|'location'|'missed']`:
            - remote_only: `remote` if `profile->is_remote === true`, otherwise `missed`
            - remote_or_locations: `remote` if `is_remote === true`, else
              `location` if `match_location_hit` is truthy, else `missed`
            - locations_only: `location` if `match_location_hit`, otherwise `missed`
        - `['key' => 'title', 'result' => 'met'|'missed']`: `met` when any
          preferred title matches `title` or `profile->normalized_title` as a whole
          word, case-insensitively. In PHP, use the same whole-word approach as
          `MatchingJobPostings::anyContainsWord` (lookarounds
          `(?<![\p{L}\p{N}])…(?![\p{L}\p{N}])`, `iu`), applied the other way
          round: preference title in posting title.
- `app/Client/JobPoolQuery.php`:
    - `forUser(User $user, array $filters = [])`: add `sort?: string|null` to
      the filters array shape. Build `$criteria = $user->jobPreference !== null
? PreferenceCriteria::fromPreference($user->jobPreference) :
PreferenceCriteria::empty()`. Then
      `$pool = MatchScore::addSelects(self::filtered($user, $filters), $criteria);`
      and `$query = JobPosting::query()->fromSub($pool, 'job_postings');`.
      If `($filters['sort'] ?? null) === 'match'`, call
      `->orderByDesc('job_postings.match_score')` first. Then always
      `->orderByDesc('job_postings.first_seen_at')->orderByDesc('job_postings.id')`.
    - The derived table is aliased `job_postings`, so the existing callers keep
      working unchanged:
        - `JobsController::show` (`->where('job_postings.id', $id)`)
        - `DashboardPresenter` (`->with()->limit()->get()`, `->reorder()->count()`)
        - `JobsPayload` (`cursorPaginate`, `reorder()->count()`)
          Cursor pagination works because `match_score` is a real column of the
          derived table and is never null.
    - Update the docblock: newest first by default, best match first with `sort=match`.
- `app/Http/Requests/Client/JobFiltersRequest.php`: add the rule
  `'sort' => ['nullable', Rule::in(['newest', 'match'])]`. Add
  `'sort' => $this->string('sort')->toString()` to `filters()` and to its
  array-shape docblock.
- `app/Client/JobsPayload.php`: add `sort: string` to the `$filters`
  array-shape docblock. No logic change: the filters pass through, and the
  counts already `reorder()`.
- `app/Http/Resources/Client/JobCardResource.php`: add
  `'match' => MatchScore::explain($posting, $criteria)`. Build `$criteria`
  from `$user->jobPreference` the same way as above, with
  `PreferenceCriteria::empty()` when there is no user or no preference. Add a
  line to the class docblock: "`match` is the score and breakdown when the
  posting was loaded through JobPoolQuery and the user has at least one
  scored preference; null otherwise". `JobDetailResource` inherits it.

**Steps.**

1. Create `MatchScore` exactly as above. Keep the SQL fragments in private
   helpers (`stackPart`, `seniorityPart`, `locationFit`, `locationHit`,
   `titleHit`) that return `[string $sql, list<mixed> $bindings]`.
2. Wire it into `JobPoolQuery::forUser()` and add the sort.
3. Add `sort` to `JobFiltersRequest` and the docblocks in `JobsPayload`.
4. Add `match` to `JobCardResource`.
5. Run `vendor/bin/pint --dirty --format agent` and `composer types:check`.
6. Smoke-check with Boost `database-query` or `php artisan tinker --execute`,
   for a seeded client with preferences:
    - `JobPoolQuery::forUser($u, ['sort' => 'match'])->limit(5)->get(['job_postings.id','job_postings.match_score'])`
      returns non-increasing scores.
    - `->reorder()->count()` equals the count before this phase, with and
      without `sort`.

**Done when.**

- `GET /internal/jobs?sort=match` returns pages ordered by `match.score`
  desc, with ties newest first. Without `sort` (or with `sort=newest`), the
  order is unchanged.
- Each card has `match: {score, criteria[]}` when the user has scored
  preferences, and `match: null` without preferences or with "All jobs" and
  nothing else set.
- `summary.total` is identical with and without `sort=match` (the gate is
  untouched). `git diff` shows no change to `MatchingJobPostings.php`.
- Review drafts (`ReviewDraftPresenter`) still render, with `job.match === null`.
- `composer lint:check`, `composer types:check` and `php artisan test --compact` pass.

**Not in this phase.** TypeScript types, fixtures and any UI (Phases 2–4).

### Phase 2 — Contract, fixtures and "N% match" chip on Jobs rows

Status: DONE
Evidence: `yarn run check` and `yarn types:check` pass; `php artisan test --compact` 33/33 passed; code-reviewer found one divergence (fixture ignored remote mode `any` for the location criterion), fixed and re-checked. Fixture mode carries `match` via `toJobCard`; `/jobs` rows show the chip, the dashboard card and other JobRow users don't pass the prop.
Role: inertia-frontend · Depends on: 1 · Covers: R1, R4 · Size: M
Origin: spec x code gap (Requirements R1, R4)

**Goal.** The TypeScript contract knows `match`, fixture mode produces it, and
each Jobs list row shows an "N% match" chip.

**Gap -> desired.** `JobCard` has no `match`, and `JobRow` has no score. After
this phase, both carry it. The chip is shown on the Jobs list only (A1).

**Contract.**

- `resources/js/types/contracts.ts`:
    ```ts
    export type MatchCriterion =
        | {
              key: 'stack';
              result: 'met' | 'partial' | 'missed';
              matched: string[];
              missing: string[];
          }
        | { key: 'seniority'; result: 'met' | 'not_stated' | 'missed' }
        | { key: 'location'; result: 'remote' | 'location' | 'missed' }
        | { key: 'title'; result: 'met' | 'missed' };
    export type JobMatch = { score: number; criteria: MatchCriterion[] }; // 0–100; criteria in weight order, only preferences that are set
    ```
    Add `match: JobMatch | null;` to `JobCard`, with the comment "null without
    scored preferences". Add `sort?: 'newest' | 'match';` to `JobFilters` (used
    in Phase 4; adding it now keeps the contract in one place).
- `resources/js/data/fixtures/handlers/cards.ts` → `toJobCard` adds
  `match: fixtureMatch(job, fixtureState.get().preferences)`. It is a local
  function that mirrors Phase 1 approximately:
    - weights `{ stack: 50, seniority: 25, location: 15, title: 10 }`, with the
      same applicability rules as Phase 1
    - stack: case-insensitive comparison
    - seniority: `'unknown'` → `not_stated`, half credit
    - location: remote when `job.isRemote === true` and the mode isn't
      `locations_only`, location when `job.location` or `job.locations`
      includes a preferred location (case-insensitive substring)
    - title: case-insensitive substring
    - score `Math.round(sum * 100 / applicableWeight)`; null when nothing applies
- `resources/js/components/patterns/job-row.tsx`: add the optional prop
  `matchScore?: number | null` (JSDoc: "Match percentage; shown as a chip when
  set"). When it's a number, render it as the **first** chip in the chip
  cluster: `<Chip variant="plan">{t('jobs.match.score', { score: matchScore })}</Chip>`.
  Other `JobRow` users (dashboard, landing, styleguide) don't pass it and stay
  unchanged.
- `resources/js/features/jobs/jobs-list.tsx`: pass `matchScore={job.match?.score ?? null}`.
- Copy (`lang/en.json` / `lang/pt.json`, next to the other `jobs.*` keys):
    - `jobs.match.score`: ":score% match" / ":score% compatível"

**Steps.**

1. Types. 2. Fixture `toJobCard`. 3. `JobRow` prop and chip. 4. `JobsList`
   wiring. 5. Copy. 6. `yarn check`.

**Done when.**

- With real data and saved preferences, `/jobs` rows show "N% match"
  (EN) / "N% compatível" (PT). Without preferences, no chip appears.
- Fixture mode (`VITE_USE_FIXTURES=true`) also shows the chip.
- The dashboard "new matches" card looks the same as before.
- `yarn check` passes.

**Not in this phase.** The detail breakdown (Phase 3) and the sort control (Phase 4).

### Phase 3 — Match breakdown in the job detail sheet

Status: PENDING
Role: inertia-frontend · Depends on: 2 · Covers: R2, R4 · Size: S
Origin: spec x code gap (Requirements R2, R4)

**Goal.** The detail sheet explains the score in plain language.

**Gap -> desired.** `JobDetailSheet` shows facts and stack chips, but no
reasons. After this phase, it shows a "Why it matches" block when
`job.match` is set.

**Contract.**

- New `resources/js/features/jobs/match-breakdown.tsx` exports
  `MatchBreakdown({ match }: { match: JobMatch })`:
    - a header row with the title `t('jobs.match.title')` and a
      `<Chip variant="plan">` showing `t('jobs.match.score', { score })`
    - then a `<ul>`, one `<li>` per criterion, in order, each with a lucide icon:
        - `Check` for `met`, `remote` and `location`
        - `CircleDashed` for `partial` and `not_stated`
        - `X` for `missed`
          Icons are `aria-hidden`, and the text carries the meaning.
    - Text per criterion:
        - stack: `plural('jobs.match.stack', total, { matched: matched.length, total })`,
          where `total = matched.length + missing.length`. When
          `missing.length > 0`, add a second muted line:
          `t('jobs.match.stack_missing', { list: format.list(missing, 'unit') })`.
        - seniority: `t('jobs.match.seniority.<result>')`
        - location: `t('jobs.match.location.<result>')`
        - title: `t('jobs.match.title_result.<result>')`
    - Use the same text tokens as the sheet (`text-label-sm text-muted`,
      `text-body text-ink`).
- `resources/js/features/jobs/job-detail-sheet.tsx`: in `DetailBody`, render
  `{job.match && <MatchBreakdown match={job.match} />}` right after the
  company header block and before the `<dl>`. It uses the card's `match`,
  so it doesn't wait for `useJob`.
- Copy (EN / PT):
    - `jobs.match.title`: "Why it matches" / "Por que combina"
    - `jobs.match.stack.one`: "Matches :matched of :total technology" / "Atende :matched de :total tecnologia"
    - `jobs.match.stack.other`: "Matches :matched of :total technologies" / "Atende :matched de :total tecnologias"
    - `jobs.match.stack_missing`: "Missing: :list" / "Faltando: :list"
    - `jobs.match.seniority.met`: "Seniority matches" / "Senioridade compatível"
    - `jobs.match.seniority.not_stated`: "Seniority not stated" / "Senioridade não informada"
    - `jobs.match.seniority.missed`: "Seniority differs" / "Senioridade diferente"
    - `jobs.match.location.remote`: "Remote, as you prefer" / "Remoto, como você prefere"
    - `jobs.match.location.location`: "In one of your locations" / "Em uma das suas localizações"
    - `jobs.match.location.missed`: "Location doesn't fit" / "Localização não compatível"
    - `jobs.match.title_result.met`: "Title matches" / "Cargo compatível"
    - `jobs.match.title_result.missed`: "Title differs" / "Cargo diferente"

**Done when.**

- Opening a job with preferences set shows the score and one line per set
  preference, with the missing technologies listed. Without preferences, the
  block is absent.
- EN and PT both render with no missing-key warnings in the console.
- `yarn check` passes.

**Not in this phase.** The sort control.

### Phase 4 — "Best match" sort control on the Jobs list

Status: PENDING
Role: inertia-frontend · Depends on: 2 · Covers: R3, R6 · Size: S
Origin: spec x code gap (Requirements R3, R6)

**Goal.** The client can switch the Jobs list between "Newest first"
(default) and "Best match", and the choice is kept in the URL.

**Gap -> desired.** No sort exists today. After this phase, a select in the
filter bar sets `sort`. The URL carries `sort=match`, and the default
`newest` is omitted to keep URLs clean.

**Contract.**

- `resources/js/lib/use-query-filters.ts`: `parse` sets
  `sort: params.get('sort') === 'match' ? 'match' : 'newest'`. `serialize`
  appends `sort=match` only when `filters.sort === 'match'`.
- `resources/js/features/jobs/job-filters.tsx`: add, last in the bar, a
  `Labeled` select with `label={t('jobs.sort.label')}` and `className="w-full md:w-48"`.
  The options are `newest` → `t('jobs.sort.newest')` and `match` →
  `t('jobs.sort.match')`, with `value={filters.sort ?? 'newest'}` and
  `onChange={(sort) => onChange({ sort })}`.
- `resources/js/data/fixtures/handlers/jobs.ts` → `listJobs`: when
  `filters.sort === 'match'`, sort `filtered` by
  `toJobCard(job).match?.score ?? 0` desc, then the existing `newestFirst`.
  Do this before slicing.
- `keys.jobs.list(filters)` already includes every filter, so no key change is needed.
- Copy (EN / PT):
    - `jobs.sort.label`: "Sort" / "Ordenar"
    - `jobs.sort.newest`: "Newest first" / "Mais recentes"
    - `jobs.sort.match`: "Best match" / "Mais compatíveis"

**Done when.**

- Choosing "Best match" reloads the list with the highest `N% match` first,
  and "Load more" keeps the order.
- Back and forward restore the previous sort. The bare `/jobs` URL has no `sort`.
- The summary counts don't change when switching sort.
- `yarn check` passes.

**Not in this phase.** Any change to the send modes (R6: random stays random).

### Phase 5 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–4 · Covers: all · Size: S
Origin: spec x code gap

**Goal.** Prove the feature end to end and hand the owner a checklist.

**Steps.**

1. `composer ci:check` (the full gate).
2. Forbidden patterns, each grep expected to return nothing:
    - `grep -rnE -- "->poll\(|wire:poll|pollingInterval|refetchInterval" app resources`
    - no Spanish copy in the new `jobs.match.*` / `jobs.sort.*` keys
      (`lang/*.json` holds only `en` and `pt`)
    - `git diff --stat` shows no change to `app/Outreach/Queries/MatchingJobPostings.php`,
      `QueueRandomApplications.php`, migrations or `composer.json` / `package.json`
3. Walk through the requirements:
    - R1/R7: for one client, compute one posting's score by hand from its
      profile and the preferences, and compare it with `match.score`.
    - R2: the breakdown lines agree with the score.
    - R3/R6: `sort=match` orders by score, and the default stays newest.
    - R5: `summary.total` is equal under both sorts, and random sends are untouched.
    - R8: collect or extract a posting while `/jobs` is open. It appears with
      its score through `jobs.collected`, without reloading the page. Save
      preferences and the scores change.

**Owner's manual checklist.**

- [ ] `/jobs` (Starter/Pro user with preferences) shows "N% match" on each row (EN and PT).
- [ ] The detail sheet shows "Why it matches" with technologies x/y, missing ones, seniority, location and title.
- [ ] The "Sort" select switches between "Newest first" and "Best match", and the URL keeps it.
- [ ] A user with no preferences, or "All jobs" with nothing else set, sees no chip and no breakdown.
- [ ] The dashboard and random sends behave exactly as before.

**Done when.** The gate is green, the greps are clean, and the walkthrough is
reported requirement by requirement. Then fold R1–R8 into the spec body as
current behaviour (`/exec-phase` does this after the last phase).
