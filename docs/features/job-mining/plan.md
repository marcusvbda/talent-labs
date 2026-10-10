# Plan — Job Mining (application quality audit and fixes)

Spec: `docs/features/job-mining/spec.md` · SHA-256 `4e275ce0dccfe7330202beb6e2b711e5feee79bb5d095229f9a8d138407400f0`
Run phases with `/exec-phase job-mining <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — 2026-10-10

Implemented: 0 · Partial: 3 · Divergent: 1 · Missing: 7 · Unverifiable: 1

Target = `## Pending changes` (P1–P12 and their recorded decisions). The spec
body (collection pipeline, sections 1–4) is the invariant to preserve.

| Requirement | State | Evidence |
| ----------- | ----- | -------- |
| P1 audit report with flags a–e | Missing | No audit command. Pattern: `app/Console/Commands/FunnelReport.php` + `app/Reports/InventoryFunnel.php` |
| P2 summary (%, top 10, 10 examples per flag) | Missing | — |
| P3 origin of each flag | Missing | Analysis, written with the report |
| P4 stop after Stage 1 / show report | Missing | Process step (no longer gates Stage 2, P-D3) |
| P5 multi-role posts split or not eligible | Missing | `HackerNewsAdapter::map()` takes one header segment as the title. No "not eligible" column. (`YCombinatorAdapter.php:216` joins with `' · '` in the description only, not the title) |
| P6 never an aggregator/thread `job_url` | Missing | `JobPosting::applicationUrl()` returns `url` and ignores `apply_url`. For HN, `url` is always `news.ycombinator.com/item?id=…` |
| P7 location / work-authorization eligibility | Partial | `MatchingJobPostings` filters `remote_mode` + `locations`. No restriction detection |
| P8 out-of-profile role/seniority | Partial | Seniority filter exists. `qa/support/customer_service/product` are target families. No management/intern exclusion |
| P9 templates | Divergent (pending) | `ApplicationTemplateRenderer.php:14-20` (PT typo `{links}}`), mirrored in `resources/js/data/fixtures/catalog/profiles.ts` |
| P10 record generic destination | Partial | `OutreachLimits::RECIPIENT_PRIORITY` are all generic aliases. The type isn't exposed anywhere |
| P11 cancel flagged queued applications | Missing | No `cancelled` status. Statuses are mirrored in `resources/js/types/contracts.ts` + `lang/{en,pt}.json` |
| P12 final gate | Unverifiable now | Commands exist: `composer lint:check`, `composer types:check`, `yarn check`, `yarn types:check` |

Reported only (not planned): the landing demo
(`resources/js/features/landing/demo-data.ts`) still says "I'm writing to
apply…".

## Status board

| Phase | Title | Role | Depends on | Origin | Size | Status |
| ----- | ----- | ---- | ---------- | ------ | ---- | ------ |
| 1 | `reports:application-audit` + shared aggregator/generic helpers | laravel-backend | none | P1, P2 | M | DONE |
| 2 | Run the audit on the restored data and present the report | laravel-backend | 1 | P1–P4 | S | PENDING (needs the dump) |
| 3 | Multi-role HN posts: split or mark not eligible | laravel-backend | none | P5 | M | DONE |
| 4 | Safe `job_url` + link line removed when empty | laravel-backend | 1 | P6 | M | DONE |
| 5 | Detect and store posting restrictions | laravel-backend | 3 | P7 | M | PENDING |
| 6 | Restrictions enforced by the match | laravel-backend | 5 | P7 | S | PENDING |
| 7 | Out-of-profile role/seniority rejected by the match | laravel-backend | 6 | P8 | S | PENDING |
| 8 | New default templates EN/PT | laravel-backend | 4 | P9 | S | PENDING |
| 9 | Destination type (generic vs named) | laravel-backend | 1 | P10 | S | PENDING |
| 10 | `Cancelled` application status (backend + client display) | inertia-frontend | none | P11 | M | PENDING |
| 11 | Propose and cancel flagged queued applications | laravel-backend | 1, 10 | P11 | S | PENDING |
| 12 | Verification and report | laravel-backend | 1–11 | P12 | S | PENDING |

Phase 2 needs the dump to be restored. It blocks only itself and the live
part of Phase 11, not the other phases.

## Owner decisions

All answered on 2026-10-10 (recorded in the spec under `## Pending changes`).

### D1 — Where does the audit read from?

Blocks: Phase 2 · Answer: **A** — a dump restored into the local DB (P1-D1).

### D2 — Is the audit command committed?

Blocks: Phase 1 · Answer: **A** — permanent read-only
`reports:application-audit`; output not committed (P1-D2).

### D3 — Does Stage 2 wait for report approval?

Blocks: — · Answer: **No.** Stage 2 covers all of P5–P11. The report is still
produced (P-D3).

### D4 — P6 scope

Answer: **A** — both the email `{{ job_url }}` and the client job link
(P6-D).

### D5 — P7 rule

Answer: **A** — existing fields + detected restrictions (P7-D, details in
Phases 5–6).

### D6 — P8 rule

Answer: **A** — existing fields (P8-D, details in Phase 7).

### D7 — P9 copy / existing profiles

Answer: **A** — the copy in Phase 8, new profiles only (P9-D).

### D8 — Schema changes with restored data

Answer: **A** — new forward migrations, never `migrate:fresh` (D8). This is
an exception, for this feature only, to the owner's usual "fold into the
create migration" habit.

### D9 — P10 derived vs stored

Answer: **A** — derived from `recipient_email` (P10-D).

### D10 — P11 cancel mechanism

Answer: **A** — `ApplicationStatus::Cancelled`. It runs only on ids the owner
confirms in that session (P11-D).

## Global constraints (every phase)

- No git writes except the `/exec-phase` commit + push of a completed phase
  (orchestrator only, explicit paths, never force).
- Never destroy data: no `migrate:fresh/refresh/reset/rollback`, `db:wipe`,
  DROP or TRUNCATE. Schema changes are **new forward migrations** (D8), run
  with `php artisan migrate`. Never edit an existing create migration in this
  feature.
- Applications with status `sent` are never changed (P11).
- No new composer/npm dependencies.
- Don't write or modify tests. Run the existing ones. If an existing test
  breaks because of an intended behaviour/copy change, report it and don't
  edit the test.
- English in code, comments and docs. User-facing copy EN + PT only, no
  Spanish.
- Realtime, never `->poll()` / `wire:poll`. Query-builder writes (`upsert`,
  bulk `update`) skip model events, so dispatch the realtime event
  explicitly.
- Collection invariants (spec §2): unique `(source_id, external_id)`; never
  change `collection_run_id` / `first_seen_at` on existing postings; one
  `JobPostingsUpdated` per source run; adapters use deterministic mapping, no
  AI.
- `MatchingJobPostings::forUser()` stays the single source of truth for the
  pool and for queueing (`QueueApplication` re-checks through it).
- Report output goes to `storage/app/private/reports/` (git-ignored). Never
  commit it.
- Scope: only what the phase asks. Report extra ideas.

## Requirement coverage

| Requirement | Phases |
| ----------- | ------ |
| P1 | 1, 2 |
| P2 | 1, 2 |
| P3 | 2 |
| P4 | 2 |
| P5 | 3 |
| P6 | 1 (helper), 4 |
| P7 | 5, 6 |
| P8 | 7 |
| P9 | 8 |
| P10 | 1 (list), 9 |
| P11 | 10, 11 |
| P12 | 12 |

## Phases

### Phase 1 — `reports:application-audit` + shared helpers

Status: DONE
Evidence: pint passed; phpstan 0 errors (needs `--memory-limit=1G`, 128M default crashes); `php artisan test --compact` 33/33; empty-DB run prints "No applications to audit." exit 0 with no files; AggregatorUrl tinker -> true,false,true; unknown status/user validated; reviewer APPROVED. `composer lint:check` still fails on two pre-existing files not in this phase (`ApplicationTemplateRenderer.php`, `tests/TestCase.php`, from 6545e8c). Per-application path untested until Phase 2 (empty DB).
Role: laravel-backend · Depends on: none · Covers: P1, P2 · Size: M
Origin: P1 "For ALL applications with status `sent` (and `queued`), produce a
report… flags a–e"; P2 "total, count and % per flag, % with 2+ flags, % with no
flag, top 10 sources/domains per flag, 10 real examples per flag"; P1-D2.

**Goal.** A read-only artisan command that audits applications into a
per-application CSV plus a summary. It also adds the aggregator-domain and
generic-alias helpers that Phases 4, 9 and 11 reuse.

**Gap -> desired.** Nothing exists -> `php artisan reports:application-audit`
writes `storage/app/private/reports/application-audit-{Ymd-His}.csv` and
`.md`, and prints the summary (`--json` prints JSON).

**Contract.**

- `config/talent.php` → under `outreach`, add
  `'aggregator_domains' => ['news.ycombinator.com', 'ycombinator.com', 'reddit.com', 'remotive.com', 'remoteok.com', 'arbeitnow.com', 'jobicy.com', 'himalayas.app', 'weworkremotely.com', 'workingnomads.com', 'adzuna.*']`
  with a comment: "Job-board/thread hosts that are never the company's own
  page or ATS. `name.*` matches any registrable domain starting with `name.`".
- `app/Support/AggregatorUrl.php` (final class):
  `public static function is(?string $url): bool`. True when the URL is null,
  empty, not `http(s)`, or its host equals an entry or ends with `.{entry}`,
  or (for `x.*` entries) `RegistrableDomain::of($url)` starts with `x.`.
  `public static function domain(?string $url): ?string` →
  `RegistrableDomain::of($url)` or null.
- `app/Outreach/OutreachLimits.php` → add
  `public const GENERIC_LOCAL_PARTS = ['careers', 'jobs', 'hr', 'info', 'contact', 'talent', 'recruiting', 'people'];`
  (spec P1e lists careers/jobs/hr/info/contact; the other
  `RECIPIENT_PRIORITY` aliases are generic too).
- `app/Reports/ApplicationAudit.php` with
  `build(array $statuses, ?User $user): array`, in the style of
  `app/Reports/InventoryFunnel.php`. The pure heuristics may live in
  `app/Reports/ApplicationAuditFlags.php` (static methods, no DB).
- `app/Console/Commands/ApplicationAuditReport.php`, signature
  `reports:application-audit {--status=sent,queued} {--user=} {--json}`,
  description "Audit sent/queued applications for quality flags". `--user`
  is validated like `FunnelReport` (`ctype_digit`, "User #X not found."). An
  unknown status gives "Unknown status: X." and `FAILURE`. Valid values are
  those of `App\Enums\ApplicationStatus`.
- **Read-only:** selects only. Query
  `Application::query()->whereIn('status', …)` with `jobPosting.source`,
  `jobPosting.profile`, `user.jobPreference`, chunked by id (500).
- `job_url` of an application = the first `http(s)` URL in
  `applications.body` equal to the posting's `url` or `apply_url`. Otherwise
  the posting's `url`. Otherwise empty. `destination_domain` =
  `AggregatorUrl::domain($jobUrl)` (empty when null).
- CSV columns in order: `application_id, status, user_id, origin, sent_at,
  adapter, source_name, job_posting_id, posting_title, normalized_title,
  subject, job_url, destination_domain, recipient_email,
  recipient_local_part, flag_a_multi_role, flag_b_aggregator_url,
  flag_c_geo, flag_c_reason, flag_d_profile, flag_d_reason,
  flag_e_generic_recipient, flags_count`. Flags are `1`/`0`. A deleted
  posting (FK null) leaves its columns empty and is counted as "posting
  deleted".
- Heuristics (case-insensitive):
    - **a** — `posting_title` or `subject` split on `' · '`, `' | '`,
      `' / '`, `' & '`, `' and '`, `';'` gives 2+ parts each matching
      `/(engineer|developer|programmer|software|swe|sdet|devops|sre|frontend|front-end|backend|back-end|full[- ]?stack|manager|designer|support|success|product|architect|scientist|analyst|lead|head of|director|researcher|qa|ios|android)/i`.
      Also true for `hacker_news` postings whose header (`raw.text` before the
      first `<p>`, tags stripped, split on `|`, segment 0 ignored) has 2+
      role-like segments, or whose body has 2+ lines starting (after `-`,
      `*`, `•` or `N.`/`N)`) with a role-like phrase.
    - **b** — `AggregatorUrl::is($jobUrl)` (an empty `job_url` gets reason
      "no link").
    - **c** — a restriction found in title / location / `description_text`
      (same patterns as Phase 5 `PostingRestrictions`: region-only, `Remote
      (US…)`, work authorization / sponsorship / must reside, hybrid /
      on-site / in-office, fixed timezone) that is incompatible with the
      user's `job_preferences`: hybrid/on-site with `remote_mode =
      remote_only`; a region not named (whole word) in preference
      `locations`; work authorization always; timezone always. With no
      preference row, any restriction counts. `flag_c_reason` lists the kinds.
    - **d** — title matches
      `/\b(manager|director|head of|vp|vice president|chief|cto|ceo|intern|internship|trainee)\b/i`;
      or the profile seniority (not `unknown`) isn't in non-empty preference
      `seniorities`; or the user is a dev profile (a preference title matches
      `/(engineer|developer|programmer|software|backend|frontend|full ?stack|devops|sre|mobile|ios|android)/i`)
      and `role_family` isn't one of backend, frontend, fullstack, software,
      mobile, devops. `flag_d_reason` says which.
    - **e** — local part of `recipient_email` in
      `OutreachLimits::GENERIC_LOCAL_PARTS`.
- Summary (`.md`, console, `--json`): generated at, statuses, total, posting
  deleted, per-status totals; per flag: count, % (one decimal), top 10
  `adapter · source_name`, top 10 `destination_domain`, 10 examples
  (`application_id`, `posting_title`, `subject`, reason), most recent first by
  `coalesce(sent_at, queued_at)`; % with 2+ flags; % with none. With zero
  rows it prints "No applications to audit.", returns `SUCCESS` and writes no
  files. The directory is created if missing, and both paths are printed.

**Steps.**

1. Add the config key, `AggregatorUrl` and `GENERIC_LOCAL_PARTS`.
2. Write the report class (+ optional flags helper), then the command.
3. `vendor/bin/pint --dirty --format agent`, `composer types:check`.
4. Smoke run on the empty local DB.

**Done when.**

- `php artisan list reports` shows `reports:application-audit`. On an empty
  DB it prints "No applications to audit." and exits 0 with no files.
- `php artisan tinker --execute="var_dump(App\Support\AggregatorUrl::is('https://news.ycombinator.com/item?id=1'), App\Support\AggregatorUrl::is('https://jobs.lever.co/acme/1'), App\Support\AggregatorUrl::is('https://www.adzuna.com.br/x'));"`
  → `true, false, true`.
- No write calls (`save|update|delete|insert|upsert`, `DB::statement`) in the
  report/command files.
- `composer lint:check`, `composer types:check`,
  `php artisan test --compact` pass.

**Not in this phase.** Running on real data (Phase 2). Any fix.

### Phase 2 — Run the audit on the restored data and present the report

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: P1, P2, P3, P4 · Size: S
Origin: P1–P4; P1-D1.

**Goal.** The Stage 1 report on the real data, with the origin of each flag
(P3), presented to the owner.

**Contract.**

- Precondition: the owner restored the dump. Check read-only:
  `select status, count(*) from applications group by status`. With no
  `sent`/`queued` rows, stop and ask the owner. Never seed or generate data.
- Run `php artisan reports:application-audit` and `--json`.
- Write `storage/app/private/reports/application-audit-{Ymd-His}-analysis.md`
  with one block per flag: origin stage (source parser / normalization-AI
  profile / match `MatchingJobPostings` / eligibility `QueueApplication`,
  `CanSendApplications` / template `ApplicationTemplateRenderer`), bug vs
  model limitation, and code location. Leads to confirm against the data:
  a → `HackerNewsAdapter::map()` or client-edited subjects (`origin`,
  reviewed queueing); b → `JobPosting::applicationUrl()` uses `url`;
  c → no restriction rule in `MatchingJobPostings`; d →
  `unknown_seniority_passes`, management titles, `qa/support/product` target
  families; e → by design (`RECIPIENT_PRIORITY`).
- Also list: aggregator domains seen in the data that are missing from
  `talent.outreach.aggregator_domains` (report, don't edit), and the count of
  `queued` applications with 1+ flag (input for Phase 11).
- No code changes. Output files are never committed.

**Done when.**

- The CSV, summary `.md` and analysis `.md` exist under
  `storage/app/private/reports/`, and `git status` doesn't list them.
- The owner received: the summary table, top sources/domains, examples, the
  P3 origins and the recommendations.

**Not in this phase.** Any fix.

### Phase 3 — Multi-role HN posts: split or mark not eligible

Status: DONE
Evidence: migration applied forward (`ineligible_reason`); pint passed; phpstan 0 errors; `php artisan test --compact` 33/33; tinker via reflection: 3 plan examples OK (#1/#2 + ineligible parent; single ineligible; single normal), requirement bullets and `Software Engineer (Backend; Frontend)` do not split; `forUser()` SQL has `ineligible_reason is null`; reviewer APPROVED after 1 correction round (rule 3 made title-shaped). Deviations: rule 4 also fires for a plural last role noun; rule 3 needs bullet + title-shaped role line. Local DB has 0 postings, so no live data exercised.
Role: laravel-backend · Depends on: none · Covers: P5 · Size: M
Origin: P5 "split into one posting per role, or mark the posting as not
eligible with a reason when the split is not reliable. Never send one email
for two roles."; P5-D; spec decision "`external_id` =
`{original external_id}#{n}`… reliable only when each role has its own
clearly delimited title/line".

**Goal.** One HN posting = one role. Posts that can't be split, and the
original unsplit post, never reach the pool.

**Gap -> desired.** One posting per HN comment, titled by the first role-like
header segment -> N postings (`{id}#{n}`) for reliable splits, and a
not-eligible posting with a reason otherwise.

**Contract.**

- New migration
  `database/migrations/2026_10_10_000001_add_ineligible_reason_to_job_postings_table.php`:
  `$table->string('ineligible_reason')->nullable()->after('role_family');`
  `down()` drops the column (only for symmetry; never run).
- `JobPostingData`: add a trailing
  `public ?string $ineligibleReason = null` (keep the existing parameter
  order; adapters use named args).
- `JobPosting`: add `ineligible_reason` to `#[Fillable]` and the docblock
  (`@property string|null $ineligible_reason`).
- `FetchJobsFromSource`: row `'ineligible_reason' => $this->fit($item->ineligibleReason)`,
  and add `'ineligible_reason'` to `MUTABLE_COLUMNS`.
- `MatchingJobPostings::forUser()`: `->whereNull('job_postings.ineligible_reason')`.
- `HackerNewsAdapter::map()` now returns `list<JobPostingData>` and `fetch()`
  yields each item, keyed by `externalId` as today. Rules (role-like =
  matches `ROLE_PATTERN` and `! isNonTitle()`):
    1. **Header roles:** 2+ role-like header segments (index ≥ 1) → one
       posting per segment.
    2. **Title separators:** otherwise, if the title segment split on
       `' · '`, `' • '` or `';'` gives 2+ parts that are each role-like → one
       posting per part.
    3. **Body list:** otherwise, if the body (`htmlToText`) has 2+ lines that
       start (after `-`, `*`, `•`, `N.` or `N)`) with a role-like phrase of at
       most 120 chars → one posting per line, title = the line without the
       bullet.
    4. **Ambiguous:** otherwise, if the title segment split on `' and '`,
       `' & '`, `' / '` or `', '` gives 2+ parts that each contain a role noun
       `/\b(engineer|developer|manager|designer|scientist|analyst|architect|sdet|qa)s?\b/i`
       → one posting (current id and title) with
       `ineligibleReason: 'Multiple roles in one post; could not split reliably.'`.
    5. Otherwise, a single posting exactly as today.
- Split children: `externalId = "{id}#{n}"` (n 1-based, in order); `title`
  = that role; `location` = the role's own parenthetical when it matches
  `/(remote|onsite|on-site|hybrid|,)/i`, else the parent location;
  `isRemote` evaluated on the role text + its location, else the parent's.
  All other fields are copied from the parent (same `raw`, description, URLs,
  contacts).
- On a split, the adapter also yields the parent (`externalId = "{id}"`,
  original title) with `ineligibleReason: 'Split into one posting per role.'`.
  That way a row stored before this change leaves the pool.
  Assumption: this slightly increases `jobs_fetched` for HN.
- Admin: `app/Filament/Resources/JobPostings/Schemas/JobPostingInfolist.php`
  shows "Not eligible" (`ineligible_reason`, hidden when null).
- `role_family` is classified per child title (already done per item in
  `FetchJobsFromSource`). Contact discovery only for new target-family rows,
  unchanged.

**Steps.** Migration → `php artisan migrate` → DTO/model/job/match → adapter
→ infolist → pint/types/tests.

**Done when.**

- `php artisan migrate` adds the column, and the existing rows are untouched.
- A tinker check with a fake item (call `map()` through reflection) confirms:
  header `Acme | Backend Engineer | Frontend Engineer | Remote` → 3 items
  (`#1`, `#2`, parent ineligible); `Acme | Backend and Frontend Engineers |
  Remote` → 1 ineligible item; `Acme | Senior Backend Engineer | Remote` → 1
  normal item.
- `MatchingJobPostings` SQL contains `ineligible_reason is null`
  (`->toRawSql()`).
- `composer lint:check`, `composer types:check`,
  `php artisan test --compact` pass.

**Not in this phase.** Restrictions (5–6), other adapters.

### Phase 4 — Safe `job_url` and link line removed when empty

Status: DONE
Evidence: pint passed; phpstan 0 errors; `php artisan test --compact` 33/33; tinker: Lever apply_url wins over HN url, HN-only -> null; empty `job_url` renders `"Hi\n\nBye"` and `"I'm applying for X."`; non-empty unchanged; reviewer APPROVED. `ApplicationTemplateRenderer.php` now pint-clean. Follow-up (not built): `AccountExportController` still tokenizes `->url` (redacted anyway).
Role: laravel-backend · Depends on: 1 · Covers: P6 · Size: M
Origin: P6 "never use an aggregator/thread URL. Use only the company's own
page or ATS; otherwise omit the link line from the email."; P6-D.

**Goal.** Emails and the client job link only point to the company page or
ATS. Without one, there is no link.

**Gap -> desired.** `applicationUrl()` returns `url` -> it returns the first
non-aggregator of `apply_url`, `url`, else `null`. The renderer drops the job
link when it is empty.

**Contract.**

- `JobPosting::applicationUrl(): ?string`: for each of `[$this->apply_url,
  $this->url]`, trimmed and non-empty, return the first where
  `! AggregatorUrl::is($candidate)`. Otherwise `null`. Update the docblock
  ("company page or ATS only; null when only aggregator/thread links
  exist").
- `ApplicationTemplateRenderer::render()`: when the `$variables` array has
  the key `job_url` with value `''`, before substitution, process each
  template line containing `{{ job_url }}` (any inner whitespace):
    - remove the placeholder (and an enclosing `(` `)` with the space
      before it, if present);
    - if what is left, trimmed, is empty or matches `/^[\p{L} ]{1,30}:$/u`
      (a label such as "Job posting:" / "Vaga:"), drop the whole line;
    - otherwise keep the line with double spaces collapsed and a space before
      `.`/`,` removed.
  The existing blank-line collapsing then applies. When `job_url` is
  non-empty or missing from `$variables`, behaviour is unchanged.
- `variablesFor()` and `QueueApplication` already use
  `(string) $posting->applicationUrl()`, so null becomes `''` and the rule
  applies to profile templates and reviewed bodies alike.
- `TemplatePreviewPresenter`: replace `$posting->url` with
  `(string) $posting->applicationUrl()` in the `clientSafe(...)` calls
  (empty → nothing to tokenise).
- `ReviewDraftPresenter`: when `$posting->applicationUrl()` is null, set
  `$variables['job_url'] = ''` (so the link line is dropped from the draft)
  instead of the token.
- Client resources (`JobCardResource`, `ApplicationItemResource`,
  `ApplicationDetailResource`) already use `applicationUrl()` and need no
  change. Verify that they handle null (they pass through `safeUrl`).
- Stored `url` stays as is (attribution in admin).

**Done when.**

- Tinker: a posting with `url` = HN thread and `apply_url` =
  `https://jobs.lever.co/x/1` → `applicationUrl()` is the Lever URL. With
  `apply_url` = HN → `null`.
- Tinker: `render("Hi\n\nJob posting: {{ job_url }}\n\nBye", ['job_url' => ''])`
  → `"Hi\n\nBye"`.
  `render("I'm applying for X ({{ job_url }}).", ['job_url' => ''])` →
  `"I'm applying for X."`.
- `composer lint:check`, `composer types:check`,
  `php artisan test --compact` pass.

**Not in this phase.** The template copy (Phase 8).

### Phase 5 — Detect and store posting restrictions

Status: PENDING
Role: laravel-backend · Depends on: 3 · Covers: P7 · Size: M
Origin: P7 "Location / work-authorization eligibility versus the client's
preferences… (reason recorded)"; P7-D.

**Goal.** Each posting stores its detected location/authorization
restrictions. This is the recorded reason Phase 6 uses.

**Contract.**

- New migration
  `database/migrations/2026_10_10_000002_add_restrictions_to_job_postings_table.php`:
  `$table->jsonb('restrictions')->default(new Expression("'[]'::jsonb"))->after('ineligible_reason');`
- `app/Collection/Support/PostingRestrictions.php` (final class, deterministic,
  no AI):
  `public static function detect(?string $title, ?string $location, ?string $text): array`
  → `list<array{kind: string, value: string|null}>`, unique by kind+value.
  The text is title + location + the first 6000 chars of the description,
  lowercased. Kinds:
    - `region` (value = canonical region): `/\b(us|u\.s\.|usa|united states|canada|uk|united kingdom|eu|europe|brazil|brasil)[- ]?(only|based)\b/`,
      `/remote\s*[\(\-–,]\s*(us|u\.s\.|usa|united states|canada|uk|eu|europe|emea|latam|americas)\b/`,
      `/\b(us|u\.s\.|usa)\s+remote\b/`. Canonical values: `us`, `canada`,
      `uk`, `europe`, `brazil`, `emea`, `latam`, `americas`.
    - `work_authorization` (value = canonical region found within 40 chars,
      else null): `/(authori[sz]ed to work|work authori[sz]ation|right to work|must (be located|reside|live) in|unable to sponsor|no (visa )?sponsorship|not (able|offering) to sponsor)/`.
    - `onsite`: `/\b(on-?site|in[- ]office|in the office)\b/`, and not
      negated by `/\bnot (on-?site|in[- ]office)\b/`.
    - `hybrid`: `/\bhybrid\b/`.
    - `timezone` (value = the zone token):
      `/\b(est|edt|pst|pdt|cst|cet|cest|gmt[+-]?\d*|utc[+-]?\d*|brt)\b.{0,20}(hours|overlap|time ?zone)/`.
  Also `public const REGION_ALIASES = ['us' => ['us', 'usa', 'u.s.', 'united states', 'america'], 'canada' => ['canada'], 'uk' => ['uk', 'united kingdom', 'england', 'london'], 'europe' => ['eu', 'europe', 'european union', 'emea'], 'brazil' => ['brazil', 'brasil'], 'emea' => ['emea', 'europe', 'eu'], 'latam' => ['latam', 'latin america', 'brazil', 'brasil'], 'americas' => ['americas', 'us', 'usa', 'canada', 'latam', 'brazil', 'brasil']];`
- `FetchJobsFromSource`: row
  `'restrictions' => json_encode(PostingRestrictions::detect($item->title, $item->location, $item->descriptionText))`,
  and add `'restrictions'` to `MUTABLE_COLUMNS`.
- `JobPosting`: fillable + cast `'restrictions' => 'array'` + docblock
  `list<array{kind: string, value: string|null}>`.
- Admin `JobPostingInfolist`: "Restrictions" entry (badges
  `kind: value`, hidden when empty).
- Existing postings get restrictions on their next collection (mutable
  column). No backfill command (not asked).

**Done when.**

- `php artisan migrate` adds the column.
- Tinker: `PostingRestrictions::detect('Software Engineer', 'Flexible/U.S. Remote', 'Must be authorized to work in the US')`
  includes `region: us` and `work_authorization: us`;
  `detect('Backend Engineer', 'NYC, Hybrid', null)` includes `hybrid`;
  `detect('Backend Engineer', 'Remote', 'Fully remote, worldwide')` → `[]`.
- `composer lint:check`, `composer types:check`,
  `php artisan test --compact` pass.

**Not in this phase.** Enforcing in the match (Phase 6).

### Phase 6 — Restrictions enforced by the match

Status: PENDING
Role: laravel-backend · Depends on: 5 · Covers: P7 · Size: S
Origin: P7 "incompatible postings do not become applications"; P7-D.

**Goal.** Postings whose restrictions the client's preferences can't satisfy
leave the pool and can't be queued.

**Contract (rule P7-D; "preference location names a region" = a whole-word,
case-insensitive match of any alias in `PostingRestrictions::REGION_ALIASES[$region]`
against any `PreferenceCriteria::$locations` term).**

- In `MatchingJobPostings::forUser()`, after the existing filters:
    - For each `region` / `work_authorization` restriction with a value: the
      posting passes only if the criteria locations name that region. Build
      it in SQL as `not exists (select 1 from jsonb_array_elements(job_postings.restrictions) r where r->>'kind' in ('region','work_authorization') and r->>'value' is not null and r->>'value' <> all(?::text[]))`,
      where the bound array = the regions named by the user's locations
      (computed in PHP from `REGION_ALIASES`; empty array → any such
      restriction excludes).
    - `work_authorization` with null value: excluded
      (`not (restrictions @> '[{"kind":"work_authorization","value":null}]')`).
    - `onsite` / `hybrid`: the posting is treated as not remote. Excluded
      when `remoteMode = remote_only`. Otherwise it must match a preference
      location (`orLocationMatch`). With empty locations it is excluded.
    - `timezone`: not a filter (recorded only).
- Applies with and without a preference row (empty criteria → only postings
  with no region/authorization/onsite/hybrid restriction). Assumption:
  narrowing the pool for clients without locations is intended ("warmer
  applications").
- Update the method's docblock with the rule.
- `QueueApplication` needs no change (it re-checks through the match →
  `REJECT_NO_MATCH`).

**Done when.**

- Tinker with a user whose locations = `["Brazil"]`, remote_or_locations: a
  posting with `[{"kind":"region","value":"us"}]` is not in `forUser()`, and a
  posting with `[]` + remote is.
- `composer lint:check`, `composer types:check`,
  `php artisan test --compact` pass (existing matching tests failing because
  of the new rule are reported, not edited).

**Not in this phase.** Role/seniority (Phase 7).

### Phase 7 — Out-of-profile role/seniority rejected by the match

Status: PENDING
Role: laravel-backend · Depends on: 6 · Covers: P8 · Size: S
Origin: P8 "Out-of-profile role/seniority: the match must reject."; P8-D.

**Goal.** Management/intern titles and non-dev families stop reaching dev
profiles.

**Contract (rule P8-D) in `MatchingJobPostings::forUser()`:**

- `private const EXCLUDED_TITLE_WORDS = ['manager', 'director', 'head of', 'vp', 'vice president', 'chief', 'cto', 'ceo', 'intern', 'internship', 'trainee'];`
  For each word that **no** criteria title contains (whole word,
  case-insensitive), add
  `not (job_postings.title ~* ? or coalesce(job_posting_profiles.normalized_title,'') ~* ?)`
  with `WordPattern::toRegex($word)`.
- Dev profile = any criteria title matches
  `/(engineer|developer|programmer|software|backend|frontend|full ?stack|devops|sre|mobile|ios|android)/i`.
  For a dev profile, `job_postings.role_family` must be in `backend,
  frontend, fullstack, software, mobile, devops`, plus `qa` if a title matches
  `/(qa|test|sdet|quality)/i`, plus `support` and `customer_service` if one
  matches `/(support|success)/i`, plus `product` if one matches `/product/i`.
- No criteria titles → only the excluded-words rule applies.
- Seniority logic and `talent.matching.unknown_seniority_passes` are
  unchanged.
- Update the docblock.

**Done when.**

- Tinker with titles `["Backend Engineer"]`: postings titled "Engineering
  Manager, Data Platform" and "Software Engineer, Quality / SDET"
  (`role_family = qa`) are excluded, and "Senior Backend Engineer" passes.
  With titles `["Engineering Manager"]`, the manager posting passes.
- `composer lint:check`, `composer types:check`,
  `php artisan test --compact` pass.

### Phase 8 — New default templates EN/PT

Status: PENDING
Role: laravel-backend · Depends on: 4 · Covers: P9 · Size: S
Origin: P9 "remove the opening 'I'm writing to apply for…'; subject with a
single role; close with a concrete ask… English and Portuguese only"; P9-D.

**Goal.** New profiles start with the warmer template.

**Contract.**

- `ApplicationTemplateRenderer` constants:
    - `DEFAULT_SUBJECT_EN = '{{ job_title }} – {{ client_name }}'`
    - `DEFAULT_BODY_EN = "Hi {{ company }} team,\n\n{{ cover_letter }}\n\nJob posting: {{ job_url }}\n\nMy CV is attached.\n\n{{ links }}\n\nWorth a 15-minute chat this week about the {{ job_title }} role?\n\nBest regards,\n{{ client_name }}"`
    - `DEFAULT_SUBJECT_PT = '{{ job_title }} – {{ client_name }}'`
    - `DEFAULT_BODY_PT = "Olá, equipe {{ company }},\n\n{{ cover_letter }}\n\nVaga: {{ job_url }}\n\nMeu currículo está em anexo.\n\n{{ links }}\n\nPodemos conversar 15 minutos esta semana sobre a vaga de {{ job_title }}?\n\nAtenciosamente,\n{{ client_name }}"`
      (this also fixes the old `{links}}` typo).
- `resources/js/data/fixtures/catalog/profiles.ts` `defaultTemplates`: the
  same four strings, verbatim.
- The subject stays ≤ `CanSendApplications::MAX_SUBJECT_LENGTH` (check the
  constant; `job_title` ≤ 255 is truncated as today if applicable).
- Existing profiles are not changed. No Spanish.

**Done when.**

- `php artisan tinker --execute="dump(App\Outreach\Support\ApplicationTemplateRenderer::defaultsFor(App\Enums\ApplicationLanguage::Pt));"`
  shows the new PT copy.
- `grep -n "writing to apply\|Gostaria de me candidatar" app resources/js/data`
  returns nothing.
- `composer lint:check`, `composer types:check`, `yarn check`,
  `yarn types:check`, `php artisan test --compact` pass.

**Not in this phase.** The landing demo copy.

### Phase 9 — Destination type (generic vs named)

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: P10 · Size: S
Origin: P10 "do NOT block for now; only record the flag so response can be
measured per destination type later"; P10-D.

**Goal.** Every application exposes its destination type without changing
sending.

**Contract.**

- `Application::recipientKind(): string` → `'generic'` when the lowercased
  local part of `recipient_email` is in `OutreachLimits::GENERIC_LOCAL_PARTS`,
  else `'named'`.
- Admin `app/Filament/Resources/Applications/Tables/ApplicationsTable.php`:
  a "Recipient type" text column (`state` from `recipientKind()`, badge
  gray/info) and a `SelectFilter` "Recipient type" (generic / named) that
  filters with `split_part(recipient_email, '@', 1)` in / not in the list.
- `ApplicationAudit` (Phase 1) keeps using the same constant. No sending or
  recipient-selection change.

**Done when.**

- The admin Applications list shows the column and the filter works.
- `composer lint:check`, `composer types:check`,
  `php artisan test --compact` pass.

### Phase 10 — `Cancelled` application status

Status: PENDING
Role: inertia-frontend (with the backend enum) · Depends on: none · Covers:
P11 · Size: M
Origin: P11 (cancel flagged queued applications); P11-D.

**Goal.** A `cancelled` status exists and is shown everywhere before any row
uses it.

**Contract.**

- `App\Enums\ApplicationStatus`: `case Cancelled = 'cancelled';` label
  "Cancelled", color `gray`. It is **not** in `countedTowardsQuota()`.
- `app/Filament/Widgets/RecentTerminalApplications.php`: include
  `Cancelled` among the terminal statuses.
- Check every `ApplicationStatus::` usage (`DashboardPresenter`,
  `ChartPresenter`, `LiveSendingPresenter`, `SendScheduler`,
  `SendApplicationEmail`). Cancelled is never pending, sent or failed. Only
  change places where a `match` would become non-exhaustive.
- Frontend: `resources/js/types/contracts.ts` `ApplicationStatus` adds
  `'cancelled'`. `features/applications/status-badge.tsx` gets a style
  (neutral) and an icon (lucide `Ban`). `features/applications/applications-table.tsx`
  maps `cancelled` to the existing finished/closed bucket (if no neutral
  bucket exists, use the one `failed` uses, and add a comment).
  `features/dashboard/activity-card.tsx` gets a label for `cancelled`.
  `data/realtime/handlers.ts` treats it as terminal. Any `Record<ApplicationStatus, …>`
  must type-check.
- `lang/en.json` `"applications.status.cancelled": "Cancelled"`,
  `lang/pt.json` `"applications.status.cancelled": "Cancelada"` (plus any
  `status.cancelled` key the activity card uses).
- Fixtures (`resources/js/data/fixtures/**`): no new data. They only need to
  type-check.

**Done when.**

- `yarn check`, `yarn types:check`, `composer lint:check`,
  `composer types:check`, `php artisan test --compact` pass.
- No row has `cancelled` yet (nothing writes it in this phase).

**Not in this phase.** The cancelling command (Phase 11).

### Phase 11 — Propose and cancel flagged queued applications

Status: PENDING
Role: laravel-backend · Depends on: 1, 10 · Covers: P11 · Size: S
Origin: P11 "Applications already sent are not changed. For `queued`
applications with a flag, propose cancelling them and ask the owner for
confirmation before executing."; P11-D.

**Goal.** A command that lists flagged queued applications (dry run) and
cancels exactly the ids the owner confirms.

**Contract.**

- `app/Console/Commands/CancelFlaggedApplications.php`, signature
  `applications:cancel-flagged {--ids=} {--force}`.
    - Without `--ids`: dry run. Uses `ApplicationAudit` (Phase 1) for
      `status = queued` and prints a table of `application_id, user_id,
      posting_title, subject, flags` for rows with `flags_count >= 1`, plus
      the comma-separated id list. Writes nothing.
    - With `--ids=1,2,3` and `--force`: in a transaction, `Application::query()->whereKey($ids)->where('status', ApplicationStatus::Queued)->lockForUpdate()`,
      then update each row (model `update`, so events fire) to
      `status = cancelled`, `last_error = 'Cancelled by the owner (quality audit).'`.
      Rows not `queued` at run time are skipped and listed. `sent` rows are
      never touched. After commit, `SendingUpdated::broadcastFor($userId)` once
      per affected user. Print counts: cancelled / skipped.
    - `--ids` without `--force` → error "Pass --force to cancel." and
      `FAILURE`.
- `SendApplicationEmail` already ignores non-queued rows. Confirm, don't
  change.
- **Execution in `/exec-phase`:** build and verify the command (dry run
  only). The actual `--force` run happens only after the owner explicitly
  confirms the listed ids in that conversation. If the dump isn't restored
  yet, stop after the dry run.

**Done when.**

- Dry run on the current DB prints the table (or "No flagged queued
  applications.") and changes nothing.
- `--ids=…` without `--force` fails with the message.
- `composer lint:check`, `composer types:check`,
  `php artisan test --compact` pass.

### Phase 12 — Verification and report

Status: PENDING
Role: laravel-backend · Depends on: 1–11 · Covers: P12 · Size: S
Origin: P12 "run `composer lint:check`, `composer types:check`, `yarn check`
and `yarn types:check`; then list the changed files and how to verify
manually."

**Steps.**

1. `composer lint:check`, `composer types:check`, `yarn check`,
   `yarn types:check`, then `composer ci:check`.
2. Forbidden patterns in the feature diff (since the commit before Phase 1):
   `->poll(`, `wire:poll`, `pollingInterval`; Spanish copy
   (`grep -rniE "\b(postulación|estimad[oa]s?|saludos|vacante)\b" app resources/js lang`);
   no git-write commands in added code; no edit to existing create
   migrations (`git diff --stat` on `database/migrations` shows only the two
   new files).
3. If the dump is restored, re-run `reports:application-audit` and compare
   with the Phase 2 numbers (new applications only).
4. List changed files (`git diff --stat <base>..HEAD`) and walk P1–P12, each
   with evidence.

**Done when.**

- All gate commands pass.
- Owner manual checklist delivered:
    - `php artisan reports:application-audit` → open the CSV/summary.
    - Collect HN → Admin › Job postings: a multi-role comment appears as
      `#1…#n` + an ineligible parent, or as one posting "Not eligible".
    - A posting with a "US only" / hybrid restriction shows "Restrictions"
      and doesn't appear in the client pool of a Brazil/remote profile.
    - Queue an application for an HN posting: the email has the
      company/ATS link or no "Job posting:" line.
    - Create a new EN and PT profile: the new subject/body appear.
    - Admin › Applications: "Recipient type" column/filter.
    - `php artisan applications:cancel-flagged` (dry run) → confirm ids →
      `--ids=… --force`. No `sent` row changed.
