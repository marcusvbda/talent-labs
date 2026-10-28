# Plan — Job Mining (Stage 1 report, Stage 3 contact-first collection)

Spec: `docs/features/job-mining/spec.md` · SHA-256 `a0af70e63d59c11695b9b86a2602fe10545679448989c1fec018f25cf534d83d`
Run phases with `/exec-phase job-mining <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — 2026-10-28

Target: `## Pending changes` (Stage 1 P3–P4, Stage 3 P5–P6). Everything else in
the spec body is an invariant.

Implemented: 2 · Partial: 1 · Divergent: 0 · Missing: 4 · Unverifiable: 0

| Requirement | State | Evidence |
| ----------- | ----- | -------- |
| P3/P4 — audit report on real data, origin per flag, stop and show the owner | Missing | `reports:application-audit` exists (`app/Reports/ApplicationAudit.php`) but never ran on real data; P1-D2: run it on the local database (11 `sent` applications, 2026-10-28) |
| P5 — AI runs only for postings of verified companies | Implemented | `app/Ai/Jobs/ExtractJobPostingProfileJob.php` returns early unless `company.outreach_status = verified`; `DiscoverCompanyContacts` and `DiscoverContactsForPosting` only dispatch it for verified companies |
| P5 — contact validated **before** the posting is stored; unverified postings not stored | Missing | `app/Jobs/FetchJobsFromSource.php` upserts every fetched posting, then dispatches `DiscoverContactsForPosting` for new target-family postings |
| P5 / P5-D1 — negative company result remembered | Partial | `companies.outreach_status` `no_domain` / `not_verifiable` is already final until `contacts:recheck` / admin retry; collection does not use it to skip postings |
| P5-D3 — non-target role families not stored | Missing | `FetchJobsFromSource` stores them and only skips their discovery |
| P6 — remove contact filters in the admin | Missing | `app/Filament/Resources/JobPostings/Tables/JobPostingsTable.php`: "Show not-verifiable" toggle + its default hiding in `modifyQueryUsing`, "Contact status" select filter |
| P6 / P6-D1 — remove contact filters in the client app | Implemented | No such filter: `app/Http/Requests/Client/JobFiltersRequest.php` (q, language, seniority, remote, today, stack), `resources/js/features/jobs/job-filters.tsx` |

Notes from the audit:

- `ResolveCompanyDomain` takes its website candidates from the company's
  stored postings (`company_website` of the 3 latest). With contact-first
  collection the postings are not stored yet, so the fetched websites must be
  passed in.
- An SMTP session takes up to 45 s per company; `FetchJobsFromSource` has
  `$timeout = 120` per source. Undecided companies therefore need their own
  job.
- `Illuminate\Bus\Batch::add()` calls `allOnQueue($this->options['queue'])`
  and `bulk(..., queue: $this->options['queue'])`: jobs added to the run batch
  run on `collection`, whatever their own queue (checked in
  `vendor/laravel/framework/src/Illuminate/Bus/Batch.php`).
- The contacts code was changed by hand on 2026-10-28 (`de97a8b`: recheck
  command, cooldown, give-up, DNS/SMTP verdicts). Preserve that logic; only
  add the website pass-through.
- `OutreachLimits::RECIPIENT_PRIORITY` holds all six probed aliases, so
  `outreach_status = verified` (≥1 `smtp_verified` contact) means "the pool
  can send to it" (`MatchingJobPostings` requires exactly that).
- No automated tests cover collection or contacts (`tests/Feature/Outreach`
  only); none may be written (CLAUDE.md).

## Status board

| Phase | Title | Role | Depends on | Origin | Size | Status |
| ----- | ----- | ---- | ---------- | ------ | ---- | ------ |
| 1 | Stage 1 audit report on real data | laravel-backend | none | P3, P4, P1-D2 | S | DONE |
| 2 | Company discovery from fetched websites | laravel-backend | none | P5 | S | DONE |
| 3 | Contact-first collection gate | laravel-backend | 2 | P5, P5-D1..D4 | M | PENDING |
| 4 | Remove admin contact filters | filament-admin | none | P6, P6-D1 | S | DONE |
| 5 | Verification and report | laravel-backend | 2, 3, 4 | all | S | PENDING |

## Owner decisions

### D1 — Which data does the Stage 1 audit run on?

Blocks: Phase 1 · Options: A the local database as it is (11 `sent`
applications of user 2, 2026-10-28, after the Stage 2 fixes), B a restored
dump of the environment that sent the poor applications, C cancel Stage 1 ·
Why: P1-D1 asked for a dump that never existed · Answer: A (2026-10-28) →
spec P1-D2.

### D2 — How long is a company's negative result remembered?

Blocks: Phase 3 · Options: A until `contacts:recheck` / admin retry
(recommended), B auto-expire after 30 days · Why: P5 left it open ·
Answer: A (2026-10-28) → spec P5-D1.

### D3 — What happens to postings of a company whose probe is inconclusive?

Blocks: Phase 3 · Options: A drop them, retry on a later run after the 6 h
cooldown (recommended), B staging table until a verdict · Answer: A
(2026-10-28) → spec P5-D2.

### D4 — Postings outside the target role families?

Blocks: Phase 3 · Options: A not stored (recommended), B stored without
validation as today · Answer: A (2026-10-28) → spec P5-D3.

### D5 — Where do per-company validation jobs run?

Blocks: Phase 3 · Options: A inside the run batch on `collection`, run ends
when every posting is decided (recommended), B after the run on `contacts`
· Answer: A (2026-10-28) → spec P5-D4.

## Global constraints (every phase)

- Git is read-only for subagents; only the `/exec-phase` orchestrator commits
  and pushes (one commit per phase, explicit paths, never force-push).
- Never destroy data: no migrate:fresh/refresh/reset/rollback, db:wipe, DROP,
  TRUNCATE, and no deleting existing `job_postings` rows. This plan adds **no
  migration**.
- No new composer/npm packages. Never write or modify tests; running existing
  ones is fine.
- Everything in English. Product locales are EN/PT only (no Spanish).
- Realtime, never polling: no `->poll()`, `wire:poll`, `$pollingInterval`.
  `JobPostingsUpdated` on `job_postings` is never emitted once per posting.
- Job postings: model `JobPosting` / table `job_postings` (never `Job`).
  Dedup key `(source_id, external_id)`. On an existing posting never change
  `collection_run_id` or `first_seen_at`; `source_contacts` is kept when a run
  brings none.
- Preserve the contacts logic changed by hand in `de97a8b` (cooldown
  `RunCompanyDiscovery::PENDING_COOLDOWN_HOURS = 6`, give-up
  `PENDING_GIVE_UP_DAYS = 7`, cache lock `contacts:company:{id}` 90 s, SMTP
  verdict rules). Only the additions this plan names.
- Jobs on the database queue must keep `$timeout` < 90 (`retry_after`).
- Assumptions used by the plan (owner may overrule):
    - **A1.** "Domain verified for sending" = `companies.outreach_status =
      verified`.
    - **A2.** A posting already in `job_postings` that a run sees again is
      refreshed as today (mutable columns, `last_seen_*`), without
      re-validation and without the role-family drop. Only **new** postings go
      through the gate.
    - **A3.** Postings stored before this change stay untouched (no cleanup).
    - **A4.** The admin "Contact" column stays; P6 names only the filters.
    - **A5.** `source_runs.jobs_fetched` stays "distinct external ids fetched";
      `jobs_new` counts only new postings actually stored. No "dropped"
      counter (it would need a migration).

## Requirement coverage

| Requirement | Phases |
| ----------- | ------ |
| P3, P4 | 1 |
| P5 (validate before storing, AI only after storing a verified posting) | 2, 3 |
| P5-D1 (negative result remembered) | 3 |
| P5-D2 (inconclusive → drop, retry later) | 3 |
| P5-D3 (non-target families not stored) | 3 |
| P5-D4 (validation inside the run) | 3 |
| P6, P6-D1 | 4 |
| Spec invariants (2.3, 2.4, 2.6, 2.8) | 3, 5 |

## Phases

### Phase 1 — Stage 1 audit report on real data

Status: DONE
Evidence: `reports:application-audit --status=sent,queued` run on the local database (11 `sent` applications of user 2, unchanged); report written to `docs/features/job-mining/assets/application-audit-report.md` covering flags a–e (a 0, b 9, c 1, d 0, e 11; all findings are model limitations, the Stage 2 fixes hold); no data changed. P4: the owner still has to read the report.
Role: laravel-backend · Depends on: none · Covers: P3, P4, P1-D2 · Size: S
Origin: P3 "For each flag, state where it originates in the pipeline (source
parser, normalization, match, eligibility, template) and whether it is a bug
or a limitation of the model." · P4 "Stop after Stage 1 and show the report to
the owner." · P1-D2 "The audit runs on the local database as it is: 11 `sent`
applications of user 2, sent on 2026-10-28 after the Stage 2 fixes."

**Goal.** Produce the Stage 1 report from the local application data and stop
for the owner.

**Gap -> desired.** The audit command exists but has never run on real data →
a report per flag with its pipeline origin and bug/limitation verdict, framed
as "do the Stage 2 fixes hold" (the original poor applications are not in this
database).

**Contract.**

- Command (read-only): `php artisan reports:application-audit
  --status=sent,queued` (flags a multi-role title/subject, b aggregator or
  missing job URL, c restriction incompatible with preferences, d
  role/seniority outside the profile, e generic recipient). It writes
  `storage/app/private/reports/application-audit-{Ymd-His}.csv` / `.md`
  (never committed).
- Report file: `docs/features/job-mining/assets/application-audit-report.md`
  with, per flag: count and %, the application ids and examples, top
  sources/domains, origin (source parser, normalization, match, eligibility,
  template), verdict (bug / model limitation), and whether a Stage 2 fix
  (spec 2.7) should have prevented it. A header states the data (local
  database, 11 `sent` applications of user 2, sent 2026-10-28 16:42–16:45,
  after the Stage 2 fixes) and that the sample is too small for percentages to
  generalize.
- Data as of planning: sources hacker_news (2), we_work_remotely (1),
  himalayas (1), jobicy (4), remote_ok (3); recipients all role aliases
  (careers, jobs, recruiting, hr), so flag e is expected on all 11 by design
  (generic recipient is a model limitation, named contacts are out of scope).
- Read-only: no data change, no `applications:cancel-flagged --force`.

**Steps.**

1. Confirm the local database still has the applications (`select status,
   count(*) from applications group by status`); if it changed, report the
   new numbers in the header.
2. Run the command, read the generated `.md`/`.csv`.
3. Write the report file; link it from spec section 3 only through
   `/exec-phase`'s fold step.
4. Stop and show the report to the owner (P4).

**Done when.**

- `docs/features/job-mining/assets/application-audit-report.md` exists with
  every flag a–e covered.
- No row in the database changed.

**Not in this phase.** Any code fix suggested by the report.

### Phase 2 — Company discovery from fetched websites

Status: DONE
Evidence: pint passed, phpstan (1G) 0 errors on changed files, `php artisan test --compact` 33/33; code-reviewer APPROVED. `normalize()` is now `public static`; `isCoolingDown()` stays a public instance method (Phase 3 calls it on an injected instance).
Role: laravel-backend · Depends on: none · Covers: P5 · Size: S
Origin: P5 "During collection, before a posting is stored in the database,
the company's contact is validated."

**Goal.** Let a company be resolved and probed from data that is not stored
yet (company name and the fetched `company_website` values), with no change
for existing callers.

**Gap -> desired.** `ResolveCompanyForPosting::handle()` needs a stored
`JobPosting`, and `ResolveCompanyDomain` only reads websites of stored
postings → both accept the raw values; old call sites behave exactly as today.

**Contract.**

- `app/Contacts/Actions/ResolveCompanyForPosting.php`: add
  `public function forName(string $companyName): Company` doing the current
  `Company::firstOrCreate(['normalized_name' => $this->normalize($companyName)],
  ['name' => $companyName])`. `handle(JobPosting $posting)` calls
  `forName($posting->company_name)` then links the posting as today.
- `app/Contacts/Actions/ResolveCompanyDomain.php`:
  `public function handle(Company $company, array $websites = []): void`
  (`@param list<string> $websites`). Candidate websites = the given
  `$websites` first (in order), then the 3 latest stored `company_website`
  values as today; `candidates()` already dedupes registrable domains and
  appends `<slug>.com`. Everything else unchanged.
- `app/Contacts/Actions/RunCompanyDiscovery.php`:
  `public function handle(Company $company, array $websites = []): void`
  passes `$websites` to `ResolveCompanyDomain::handle()`. Lock, cooldown and
  refresh logic unchanged; no other public method is added.
- `app/Models/Company.php`: add
    - `public function isVerifiedForSending(): bool` →
      `$this->outreach_status === OutreachStatus::Verified` (A1);
    - `public function isClosedForSending(): bool` → `outreach_status` is
      `OutreachStatus::NoDomain` or `OutreachStatus::NotVerifiable` (P5-D1).
    With PHPDoc in the model's style.

**Steps.**

1. Implement the four changes above.
2. `vendor/bin/pint --dirty --format agent`, `composer types:check`.

**Done when.**

- Existing callers (`DiscoverContactsForPosting`, `DiscoverContactsForCompany`,
  `ContactsRecheck`, `RetryCompanyDiscovery`) compile unchanged and behave
  as before (default `$websites = []`).
- `composer types:check` and `composer lint:check` pass;
  `php artisan test --compact` passes.

**Not in this phase.** Any change to `FetchJobsFromSource` (Phase 3).

### Phase 3 — Contact-first collection gate

Status: PENDING
Role: laravel-backend · Depends on: 2 · Covers: P5, P5-D1, P5-D2, P5-D3,
P5-D4 · Size: M
Origin: P5 "Only if the company has a domain verified for sending is the
posting processed with AI and stored … A posting whose company has no domain
verified for sending is not processed with AI and is not stored … the
company's negative result is remembered", decisions P5-D1..D4.

**Goal.** A new posting reaches `job_postings` only when its company is
verified for sending; then its AI profile extraction is queued.

**Gap -> desired.** Today every fetched posting is upserted, then contact
discovery runs per new target posting → existing postings are refreshed as
today; new postings are filtered by role family, grouped by company, and
stored only for verified companies (directly, or after a per-company
validation job added to the run batch).

**Contract.**

- New `app/Actions/Collection/UpsertJobPostings.php`:
  `public function handle(array $rows): void` (`@param list<array<string,
  mixed>> $rows`). Moves from `FetchJobsFromSource` the `MUTABLE_COLUMNS`
  constant and the upsert call:
  `JobPosting::upsert($rows, ['source_id', 'external_id'], [...MUTABLE_COLUMNS,
  'source_contacts' => DB::raw('coalesce(excluded.source_contacts,
  job_postings.source_contacts)')])`, in chunks of 200. `company_id` is **not**
  a mutable column (only set on insert).
- `app/Jobs/FetchJobsFromSource.php` (`$tries = 1`, `$timeout = 120`
  unchanged). In `handle()`, after fetching `$items` keyed by external id:
    1. Split into existing (external id already stored for the source) and new,
       in chunks of 200 as today. Row building (`title()`, `fit()`, `tags()`,
       role classification, restrictions, 255-char truncation, `published_at`
       in app tz, `raw` json, timestamps) is unchanged and reused for both.
    2. Existing → `UpsertJobPostings::handle()` as today (A2).
    3. New → drop rows whose `role_family` is not in
       `config('talent.collection.target_role_families')` (P5-D3; null =
       dropped).
    4. Group remaining new rows by
       `ResolveCompanyForPosting::normalize($item->companyName)`; per group
       `$company = forName(first item companyName)`:
        - `isVerifiedForSending()` → set `company_id` on the rows, upsert,
          then `ExtractJobPostingProfileJob::dispatch($id)` for each stored new
          id (`JobPosting::query()->where('source_id', …)->whereIn('external_id',
          …)->pluck('id')`);
        - `isClosedForSending()` → drop (P5-D1);
        - pending and `RunCompanyDiscovery::isCoolingDown($company)` → drop
          (P5-D2);
        - otherwise → `new StoreCompanyPostings($sourceRun->id, $company->id,
          $rows, $websites)` where `$websites` = distinct non-null
          `companyWebsite` of the group; add it with
          `$this->batch()->add([$job])` (P5-D4); if `$this->batch()` is null,
          `dispatch($job)->onQueue('collection')`.
    5. `jobs_fetched = count($items)`; `jobs_new` = new postings stored inline.
       Source run `completed`, source `last_run_*` as today.
    6. `JobPostingsUpdated` once (as today). Remove the
       `DiscoverContactsForPosting` dispatch loop and its import.
- New `app/Jobs/StoreCompanyPostings.php` (`Batchable`, `Queueable`,
  `$tries = 1`, `$timeout = 75`). Constructor
  `(public int $sourceRunId, public int $companyId, public array $rows, public
  array $websites)` — plain arrays only (`$rows` already JSON-encoded where the
  upsert needs it). Middleware:
  `[(new WithoutOverlapping((string) $this->companyId))->releaseAfter(15)->expireAfter(120)]`
  so two groups of the same company don't probe in parallel (assumption; if
  `release` on a batched job with `$tries = 1` fails it, set `$tries = 5` and
  keep exceptions caught). `handle(RunCompanyDiscovery $discovery)`:
    1. Return if `$this->batch()?->cancelled()` or the company / source run is
       missing.
    2. If the company is not verified, not closed and not cooling down →
       `$discovery->handle($company, $this->websites)`, then `$company->refresh()`.
    3. If `isVerifiedForSending()` → count which rows' external ids are not yet
       stored, set `company_id`, `UpsertJobPostings::handle()`, dispatch
       `ExtractJobPostingProfileJob` per stored new id, then in a transaction
       `SourceRun::query()->lockForUpdate()->find(...)`, add the count to
       `jobs_new` and `save()` (fires the existing `collection_run_{id}`
       realtime hook), then `RealtimeEvent::dispatch('job_postings',
       'JobPostingsUpdated', ['source_run_id' => $this->sourceRunId])` once
       (catch `BroadcastException` → `report()`).
    4. Otherwise drop (nothing stored).
    5. Any `Throwable` → `Log::warning('StoreCompanyPostings failed', [...ids,
       'exception' => $e])`, never rethrown.
- `FinalizeCollectionRun` is unchanged: the batch `finally` already runs after
  added jobs, and it aggregates `jobs_new` from source runs.
- `DiscoverContactsForPosting` stays (used by `contacts:backfill`); collection
  no longer dispatches it. `DiscoverCompanyContacts`' own AI dispatch for
  verified companies stays.

**Steps.**

1. Create `UpsertJobPostings` and move the upsert into it.
2. Rework `FetchJobsFromSource::handle()` as above.
3. Create `StoreCompanyPostings`.
4. Confirm with Boost `search-docs` that `WithoutOverlapping::releaseAfter`
   works for batched jobs; adjust `$tries` per the contract note.
5. Pint, `composer types:check`, `php artisan test --compact`.
6. Manual smoke (owner or executor, local): `php artisan collection:run` with
   the queue workers up; check a run's source runs and that new postings all
   belong to verified companies:
   `select count(*) from job_postings jp join companies c on c.id = jp.company_id where jp.collection_run_id = <run> and c.outreach_status <> 'verified'`
   → 0, and no new posting with `company_id` null for that run.

**Done when.**

- A new posting of a verified company is stored with `company_id` and gets an
  `ExtractJobPostingProfileJob` on queue `ai`.
- New postings of `no_domain` / `not_verifiable` companies, of pending
  companies inside the cooldown, and outside the target role families are not
  stored.
- A new company is probed once per run inside the run's batch; the run moves
  to `completed`/`partial`/`failed` only after those jobs finish; its new-jobs
  count equals the postings stored.
- Existing postings are refreshed exactly as before (never
  `collection_run_id` / `first_seen_at`; `source_contacts` kept).
- `JobPostingsUpdated` is never emitted per posting.
- `composer types:check`, `composer lint:check`, `php artisan test --compact`
  pass.

**Not in this phase.** Admin filters (Phase 4); cleaning up legacy postings
(A3); a "dropped" counter (A5).

### Phase 4 — Remove admin contact filters

Status: DONE
Evidence: both filters and the default hiding removed from `JobPostingsTable`; pint passed, phpstan 0 errors, tests 33/33; code-reviewer APPROVED. Not exercised in a browser.
Role: filament-admin · Depends on: none · Covers: P6, P6-D1 · Size: S
Origin: P6 "Remove the filters by validated / not-validated contact, both in
the admin and in the client app." · P6-D1 "The client app has no contact
filter; P6 applies to the admin job postings list only."

**Goal.** The admin Job postings list no longer filters by contact validation.

**Gap -> desired.** `JobPostingsTable` hides not-verifiable postings by
default and has "Show not-verifiable" and "Contact status" filters → no
default hiding and neither filter.

**Contract.**

- File: `app/Filament/Resources/JobPostings/Tables/JobPostingsTable.php`.
- `modifyQueryUsing` keeps only `->with(['collectionRun', 'source',
  'company'])` (drop the `->when(! show_not_verifiable …)` clause and the
  then-unused `HasTable` parameter/import).
- Remove `Filter::make('show_not_verifiable')` and
  `SelectFilter::make('contact_status')`.
- Keep the "Contact" column (A4), grouping, sort, other filters (run, source,
  adapter, role family, "Today's runs only"), actions and
  `->socket(channel: 'job_postings', event: 'JobPostingsUpdated')`.
- Remove imports that become unused (`OutreachStatus`; `ContactStatus` stays
  for the column).
- No client-app change (P6-D1).

**Steps.**

1. Edit the table as above.
2. Pint, `composer types:check`.

**Done when.**

- `/admin/job-postings` lists postings of every outreach status by default and
  shows neither filter; the other filters still work.
- `composer types:check`, `composer lint:check` pass.

**Not in this phase.** The Companies resource filters (company-level, not P6).

### Phase 5 — Verification and report

Status: PENDING
Role: laravel-backend · Depends on: 2, 3, 4 · Covers: all · Size: S
Origin: spec x code gap (plan close-out)

**Goal.** Prove the delivered phases match the spec and hand the owner a
manual checklist.

**Contract / Steps.**

1. Full gate: `composer ci:check`.
2. Forbidden-pattern grep over the changed files and `app/`, `resources/`:
   `->poll(`, `wire:poll`, `pollingInterval`; Spanish copy (`es.json`,
   `'es'` locale keys, Spanish words in new strings); no git writes in code
   or scripts.
3. Walk P5, P5-D1..D4, P6, P6-D1 against the code, citing file:line.
4. Owner manual checklist:
    - Run `php artisan collection:run` with the workers up; the run stays
      `running` until company checks finish, then finishes with a new-jobs
      count; the notification shows it.
    - Query from Phase 3 step 6 returns 0.
    - A company already `not_verifiable` gets no new posting and no new probe
      (`contact_checked_at` unchanged).
    - `/admin/job-postings`: no "Show not-verifiable" / "Contact status"
      filters; the table refreshes in realtime during a run.
    - AI usage (`ai_usage_records`) only grows for postings of verified
      companies.
5. Report results; `/exec-phase` then folds P5/P6 into the spec body
   (sections 1 Flow, 2.4, 2.5 Job postings, 2.8) and moves Stage 3 out of
   `## Pending changes`; Stage 1 (P3, P4) moves out once the owner has seen
   the Phase 1 report.

**Done when.**

- `composer ci:check` passes.
- The grep finds nothing new.
- Every covered requirement has evidence in the report.

**Not in this phase.** Re-running the Stage 1 audit (Phase 1).
