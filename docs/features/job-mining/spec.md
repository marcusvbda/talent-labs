# Job Mining

> The body of this spec describes the **current behaviour of the code**
> (filled by `/update-feature-spec` on 2026-10-10). Changes the owner wants go
> under `## Pending changes`.
>
> Scope (confirmed by the owner): the whole collection pipeline — sources,
> adapters, collection runs, source runs, job postings, the automatic schedule
> and their Filament admin screens. Nothing in the code is literally named
> "job mining"; the code calls it "collection".

## 1. What it is

Collects job postings from configured **sources** (job boards / ATS feeds) in
**collection runs**, stores them as **job postings**, and exposes the
pipeline to admins in Filament under the navigation group **Collection**.

Vocabulary:

- **Collection run** (`CollectionRun`, UI "Run"): one execution over every
  active source. Label: `Run #{id} · {started_at in app timezone, "j M Y H:i"}`
  (just `Run #{id}` while `started_at` is null).
- **Source run** (`SourceRun`): one source inside one run.
- **Job posting**: `JobPosting` / `job_postings` (never `Job`, which collides
  with the queue table).

Flow:

1. A run is started manually (admin button or `collection:run`) or by the
   scheduler (`collection:tick`, every minute).
2. One `FetchJobsFromSource` job per active source runs on the `collection`
   queue as a bus batch.
3. Each job calls the source's adapter, upserts postings, classifies the role
   family, updates counters and broadcasts realtime events.
4. When the batch finishes the run is finalized (status, counters,
   notification, `JobsCollected` client event).

## 2. Requirements

### 2.1 Sources

- Table `sources`: `name`, `adapter` (`SourceAdapter`), `identifier`
  (nullable), `settings` (JSON, nullable), `interval_minutes`, `is_active`,
  `last_run_at`, `last_run_status`.
- Sources are **not created or deleted in the admin** (`canCreate()` is false,
  no delete action). They come from `SourceSeeder` (idempotent
  `firstOrCreate` keyed by `adapter` + `identifier`; seeds a starter set, with
  the aggregators active and the company ATS sources and Adzuna / Y Combinator
  inactive). `Source::hasHistory()` tells whether it has runs or postings.
- Admin list (`SourceResource`): name, adapter badge, identifier, inline
  "Active" toggle, interval, last run at, last run status; filters by adapter
  and active; edit action; realtime via `socket('sources', 'SourceUpdated')`.
- Admin edit: `name` and `adapter` are read-only. `identifier` is shown and
  required only for adapters that require one, and is unique per adapter.
  `settings` (key/value, with a per-adapter helper hint) is shown only for
  adapters that do not require an identifier. `interval_minutes` (min 1,
  default 60) is **stored only**; automatic collection follows the schedule
  page. `is_active` toggle.

### 2.2 Adapters

- Contract `App\Collection\Contracts\JobSourceAdapter::fetch(Source): iterable<JobPostingData>`.
- `JobPostingData` (readonly): `externalId, title, companyName, location,
  isRemote, department, employmentType, url, applyUrl, descriptionHtml,
  descriptionText, publishedAt, raw, companyWebsite, sourceContacts`.
- `SourceAdapter` enum (13): `greenhouse`, `lever`, `ashby` (company ATS,
  require `identifier`) and `remotive`, `remote_ok`, `arbeitnow`, `jobicy`,
  `himalayas`, `we_work_remotely`, `working_nomads`, `hacker_news`,
  `adzuna`, `y_combinator` (aggregators, identifier must be null, configured
  through `settings`). The enum resolves label, badge color,
  `requiresIdentifier()`, `sourceLabel()` ("via Remotive", "via HN Who is
  hiring", …; ATS adapters use their own label) and the adapter class.
- Supported `settings` keys per adapter:

    | Adapter                       | Keys                                                                                  |
    | ----------------------------- | ------------------------------------------------------------------------------------- |
    | greenhouse / lever / ashby    | `locations` (comma list, keep postings whose location contains any term; empty = all), `website` |
    | remotive                      | `category`, `search`, `limit`                                                         |
    | remote_ok                     | `tags` (comma list, max 10, one request per tag; empty = whole feed)                  |
    | arbeitnow                     | `pages` (1–5, default 1), `remote` (`"true"` = remote only)                           |
    | jobicy                        | `industries` (comma list, max 10), `count`, `geo`, `tag`                              |
    | himalayas                     | `queries`, `pages` (1–5), `country`, `worldwide`, `seniority`                         |
    | we_work_remotely              | `categories` (feed slugs; default category when empty)                                |
    | working_nomads                | `categories` (matched against the category name)                                      |
    | hacker_news                   | none                                                                                  |
    | adzuna                        | `country` (default `br`), `queries`, `pages` (1–3); needs `ADZUNA_APP_ID` / `ADZUNA_APP_KEY` (`config/services.php`) |
    | y_combinator                  | `roles` (`/jobs/role` slugs; default `software-engineer, product-manager, support`)   |

- Shared HTTP (`InteractsWithJobBoardApi`): `Http::timeout(20)->retry(2, 500)
  ->acceptJson()->withUserAgent('talent-labs/0.1 (local)')`, throws on
  non-2xx. Multi-request adapters are capped at 10 requests per run, pause
  250 ms between requests, and stop on HTTP 429 keeping what was collected; a
  429 before anything was collected fails the source run.
- Direct field mapping, no AI/NLP in the adapters; `raw` keeps the full
  item. `companyName` comes from the payload.
- `JobPostingData` also carries a trailing `ineligibleReason` (nullable).
  `hacker_news` posts one role per posting: when a comment has 2+ role-like
  header segments, title parts separated by ` · `, ` • ` or `;`, or 2+ title-
  shaped role lines in the body, the adapter yields one posting per role
  (`external_id` = `{id}#{n}`, n 1-based, same `raw`, description and
  contacts) plus the original comment with `ineligibleReason` "Split into one
  posting per role.". When the title only looks multi-role (` and `, ` & `,
  ` / `, `, ` between role nouns) the single posting is marked "Multiple roles
  in one post; could not split reliably.". Otherwise it is one normal posting.

### 2.3 Collection runs

- `StartCollectionRun::handle(?User)`: takes cache lock
  `collection-runs:start` (10 s) and a DB transaction. Refuses with
  `CollectionRunException` when another run is `pending`/`running` ("A
  collection run is already in progress.") or no source is active ("There are
  no active sources to collect."). Creates the run (`pending`,
  `triggered_by`, `sources_total`), one `pending` source run per active
  source, dispatches a bus batch on queue `collection` with
  `allowFailures()` and a `finally` that finalizes the run (capturing only the
  run id), then sets `batch_id`, `status = running`, `started_at = now`.
- `CollectionRunStatus`: `pending`, `running`, `completed`, `partial`,
  `failed` (`isInProgress()` = pending or running). `SourceRunStatus`:
  `pending`, `running`, `completed`, `failed`.
- `FetchJobsFromSource` (`$tries = 1`, `$timeout = 120`): skips if the source
  run is missing or the batch was cancelled; marks the source run `running`;
  fetches; on success sets `completed`, `jobs_fetched` (distinct external ids),
  `jobs_new`, `finished_at`, and updates `sources.last_run_at` /
  `last_run_status`. Any exception is caught: source run → `failed` with
  `error_message` truncated to 1000 chars, source `last_run_status = failed`,
  a warning is logged, never rethrown. `failed()` covers timeouts/killed
  workers the same way.
- `FinalizeCollectionRun` (idempotent): aggregates counters from source runs;
  status = `completed` if no source failed, `failed` if none succeeded,
  otherwise `partial`; sets `finished_at`; dispatches the `JobsCollected`
  client event (`jobs` channel, `jobs.collected`, ids and counts only) when
  `jobs_new > 0`; if `triggered_by` is set, sends a Filament database
  notification "Run #N finished — X new jobs from Y sources (Z failed)" with
  a "View run" link (color by status).
- `MarkCollectionRunFailed`: only for in-progress runs ("This run is not in
  progress." otherwise); unfinished source runs → `failed` with "Marked as
  failed by an admin."; run → `failed`; cancels the batch.
- Commands: `collection:run` starts a run now (`triggered_by = null`);
  `collection:tick` (scheduled every minute, `withoutOverlapping`,
  `onOneServer`) starts a run when the current `HH:MM` in the schedule
  timezone is one of the configured times and the schedule is enabled. The slot
  (`Y-m-d H:i`) is claimed atomically in `collection_schedules.last_slot_key`,
  so there is one run per slot and missed slots are not caught up. A refused
  start is recorded as `Skipped: …`.

### 2.4 Job postings

- Unique key `(source_id, external_id)`; processed in chunks of 200, one
  `upsert` per chunk (model events are skipped, hence the explicit realtime
  event).
- New posting: `collection_run_id` = current run, `first_seen_at` = now. On
  an existing posting only mutable columns are refreshed (`title`,
  `company_name`, `location`, `is_remote`, `department`, `employment_type`,
  `role_family`, `ineligible_reason`, `restrictions`, `url`, `apply_url`,
  `company_website`, descriptions, `published_at`, `raw`, `last_seen_run_id`, `last_seen_at`); never
  `collection_run_id` or `first_seen_at`. `source_contacts` is kept when a run
  brings none.
- String columns are truncated to 255 chars. An unusable title
  (`PostingTitle::isUsable`) keeps the stored title if usable, otherwise a
  placeholder "Open position at {company}" (replaced later by profile
  extraction). `published_at` is stored in the app timezone.
- `role_family` (`RoleFamily`: backend, frontend, fullstack, software,
  mobile, devops, qa, support, customer_service, product; null = "Other") is
  set by `RoleClassifier` from the title, with source tags
  (`tags`, `jobIndustry`, `category`, `category_name` in `raw`) as a fallback
  only when the title has a generic engineering noun. Exclusion keywords
  (sales, marketing, recruiting, designer, …) win and yield null.
- After a successful upsert, `JobPostingsUpdated` is broadcast once per source
  run on `job_postings` (never once per posting). Contact discovery
  (`DiscoverContactsForPosting`) is dispatched only for **new** postings whose
  `role_family` is in `talent.collection.target_role_families`.
- `ineligible_reason` (nullable string): a posting with a reason never
  reaches the client pool (`MatchingJobPostings::forUser()` requires it null).
- `restrictions` (jsonb list of `{kind, value}`, default `[]`) is detected at
  collection time by `PostingRestrictions::detect()` (deterministic, from
  title, location and the first 6000 chars of the description). Kinds:
  `region` (us, canada, uk, europe, brazil, emea, latam, americas),
  `work_authorization` (value = region found nearby, or null), `onsite`,
  `hybrid` and `timezone`. Existing postings get it on their next collection.
- `JobPosting::applicationUrl()` returns the first of `apply_url`, `url` that
  is not an aggregator/thread URL (`AggregatorUrl`, hosts in
  `talent.outreach.aggregator_domains`), else `null`. It feeds both the email
  `{{ job_url }}` and the client job link; the stored `url` is untouched.
- `talent.collection.window_days` (env `COLLECTION_WINDOW_DAYS`, default 5):
  days, today included in the app timezone, that a posting stays in the client
  pool, counted from the `started_at` of the run that first collected it
  (`PostingDay`). "Today" is the app timezone's start of day.

### 2.5 Admin screens (Filament, group "Collection")

- **Runs** (`CollectionRunResource`, read-only, no create): list sorted by id
  desc with status badge, sources ok/failed/total, jobs fetched, new jobs,
  triggered by, started at, finished at (with duration); header action
  "Collect jobs now"; row actions View, "View jobs" (job postings filtered by
  that run) and "Mark as failed" (in-progress only). Realtime
  `collection_runs` / `CollectionRunUpdated`. View page: infolist + "Source
  runs" relation manager (source, adapter, status, jobs fetched, new jobs,
  error, started/finished), "Mark as failed" header action, realtime listener
  on `collection_run_{id}`.
- **Collect jobs now** (`CollectJobsNowAction`): confirmation modal ("This will
  collect jobs from N active sources."); disabled with tooltip when a run is in
  progress or no source is active; success notification "Run #N started" /
  danger notification with the exception message.
- **Collection schedule** (`CollectionSchedulePage`, slug
  `collection-schedule`): status block (state, next run, last result, latest
  run), form with toggle "Automatic collection", times (`HH:MM` 24 h, 1–12,
  distinct) and timezone; header actions Pause / Resume (confirmed) and "Run
  now". Singleton row `collection_schedules` id 1 (`CollectionSchedule`,
  defaults: enabled, `06:00` and `18:00`, `app.timezone`; seeded by
  `CollectionScheduleSeeder`). Realtime `collection_schedule` /
  `CollectionScheduleUpdated`.
- **Job postings** (`JobPostingResource`, read-only, no create): list grouped
  by run (newest first), sorted by `published_at` desc; columns title,
  company, remote, contact status, role, location, adapter, published at;
  filters: run (last 50), source, adapter, role family (incl. "Other"),
  "Today's runs only", "Show not-verifiable" (off by default: hides postings
  whose company outreach status is not verifiable) and contact status; row
  actions View and "Open posting". View page shows all fields, "Not eligible"
  (the `ineligible_reason`, hidden when null), "Restrictions" (badges
  `kind: value`, hidden when empty), source contacts (labelled "not used as
  application recipients") and the description.
  Realtime `job_postings` / `JobPostingsUpdated`.

### 2.6 Realtime channels (public, ids only)

| Channel               | Event                       | Emitted by                                     |
| --------------------- | --------------------------- | ---------------------------------------------- |
| `collection_runs`     | `CollectionRunUpdated`      | `CollectionRun` saved/deleted                  |
| `collection_run_{id}` | `CollectionRunUpdated`      | that run or any of its `SourceRun`s saved      |
| `job_postings`        | `JobPostingsUpdated`        | `FetchJobsFromSource`, once per source run     |
| `sources`             | `SourceUpdated`             | `Source` saved/deleted                         |
| `collection_schedule` | `CollectionScheduleUpdated` | `CollectionSchedule` saved/deleted             |
| `jobs`                | `jobs.collected`            | `FinalizeCollectionRun` (client side)          |

### 2.7 Application quality

- **Audit** (`php artisan reports:application-audit {--status=sent,queued}
  {--user=} {--json}`, read-only): audits applications and writes
  `storage/app/private/reports/application-audit-{Ymd-His}.csv` and `.md`
  (never committed), printing the summary (`--json` prints JSON). Heuristic
  flags per application: a multi-role title/subject, b aggregator or missing
  job URL, c restriction incompatible with the client's preferences, d
  role/seniority outside the profile, e generic recipient. The summary gives
  per flag the count, %, top sources and domains and 10 examples, plus % with
  2+ flags and % with none. Flag c is a looser quality heuristic than the
  match: it also counts timezone and work-authorization restrictions the
  match would let through, and any restriction when the client has no
  preference row. With no rows it prints "No applications to audit." and
  writes nothing. Logic in `App\Reports\ApplicationAudit` and
  `ApplicationAuditFlags`.
- **Match** (`MatchingJobPostings::forUser()`, single source of truth for the
  pool and for queueing; `QueueApplication` re-checks through it):
    - a region / work-authorization restriction passes only when a preference
      location names that region (whole word, via
      `PostingRestrictions::REGION_ALIASES`); a work-authorization restriction
      without a region is excluded;
    - an `onsite` or `hybrid` restriction makes the posting count as not
      remote: excluded under `remote_only`, otherwise it must match a
      preference location (excluded with no locations);
    - `timezone` is recorded but never excludes;
    - titles with manager, director, head of, VP, vice president, chief, CTO,
      CEO, intern, internship or trainee are excluded unless a preference
      title contains that word;
    - for a development profile (a preference title names an engineering
      role) `role_family` must be backend, frontend, fullstack, software,
      mobile or devops, plus qa / support and customer_service / product when
      a preference title names them;
    - these rules apply with and without a preference row (empty criteria
      only keep postings with no region, authorization, onsite or hybrid
      restriction). Seniority and `talent.matching.unknown_seniority_passes`
      are unchanged.
- **Email link line:** when the rendered `job_url` is empty, the template
  line holding `{{ job_url }}` is removed (a bare label such as "Job
  posting:" goes with it; an enclosing `( )` is dropped and the sentence
  kept). Applies to profile templates, reviewed drafts and previews.
- **Default templates** (new profiles only; existing profiles unchanged):
  subject `{{ job_title }} – {{ client_name }}` in EN and PT; body opens with
  "Hi {{ company }} team," / "Olá, equipe {{ company }},", then the cover
  letter, the job line, the CV note, links and a closing ask for a 15-minute
  chat. Mirrored in `resources/js/data/fixtures/catalog/profiles.ts`.
- **Recipient type:** `Application::recipientKind()` is `generic` when the
  local part of `recipient_email` is in `OutreachLimits::GENERIC_LOCAL_PARTS`
  (careers, jobs, hr, info, contact, talent, recruiting, people), else
  `named`. It is derived, not stored, and does not change sending. The admin
  Applications table has a "Recipient type" badge column and filter.
- **Cancelled status:** `ApplicationStatus::Cancelled` ("Cancelled" /
  "Cancelada", gray, not counted toward the quota) is shown in the admin
  terminal widget and the client app. A cancelled application keeps blocking
  that company for the user (unique `(user_id, company_id)`).
- **Cancel flagged queued applications** (`php artisan
  applications:cancel-flagged {--ids=} {--force}`): without `--ids` it is a
  dry run listing queued applications with 1+ audit flag and their ids.
  `--ids=1,2,3` requires `--force` ("Pass --force to cancel."); it then
  cancels, in a transaction with row locks, only the ids still `queued`
  (`last_error` "Cancelled by the owner (quality audit)."), lists the skipped
  ones and broadcasts `SendingUpdated` once per affected user. `sent`
  applications are never changed. It runs only on ids the owner confirms.

## 3. Attachments and references

None.

## 4. Open points

- Per-adapter field mapping (which payload field feeds which
  `JobPostingData` field, and each adapter's endpoint) was **not** verified
  adapter by adapter; only the settings keys and request caps above were
  checked against the code.
- No automated tests were inspected for this feature; the spec does not list
  them.
- Authorization of the admin screens (who may reach the Collection group) was
  not inspected; no per-resource policy exists for these models in
  `app/Policies`.
- The `job-collection` skill (`.claude/skills/job-collection/SKILL.md`) is
  out of date relative to the code (see the update report); it points to
  `docs/features/job-collection-mvp/spec.md`, which does not exist.
- Decided by the owner (delegated "decide what is best"; goal: make the search
  more efficient and the applications warmer). Confirmed by the owner in
  `/plan-feature` on 2026-10-10:
    - Scope: P5–P11 stay in this spec even though they touch applications,
      matching, eligibility and the email template.
    - P5: a split posting gets `external_id` = `{original external_id}#{n}`
      (n = role position in the post), so `(source_id, external_id)` stays the
      dedup key. A split is "reliable" only when each role has its own clearly
      delimited title/line; otherwise the post is marked not eligible with a
      reason.
    - P6: the aggregator/thread domain list starts with `news.ycombinator.com`
      and `reddit.com` plus the domains of the aggregator sources; the Stage 1
      report confirms the final list.
    - P7 and P8: built only from client preference fields that already exist;
      new fields are proposed only if the Stage 1 report shows they are
      needed. The rule is shown to the owner before implementing.
- P11: cancelling queued applications changes existing data; it only runs
  after the owner's explicit confirmation (already required by P11).
- Stage 2 questions (P5–P11) were decided on 2026-10-10 and are implemented
  (section 2.7). The final aggregator domain list is the one in
  `talent.outreach.aggregator_domains`; it was not confirmed against real
  data because no Stage 1 report has been produced yet.

## Pending changes

Context (owner): in local testing the system already sent several
applications and many came out poor (for example a Hacker News post with two
roles sent with one subject and a `job_url` pointing to the HN thread). The
audit command and the Stage 2 fixes are built (section 2.7); what remains is
running the audit on real data.

### Stage 1 — Audit report on real data

- **P3.** For each flag, state where it originates in the pipeline (source
  parser, normalization, match, eligibility, template) and whether it is a bug
  or a limitation of the model.
- **P4.** Stop after Stage 1 and show the report to the owner.

Decisions:

- **P1-D1.** The audit runs locally: the owner restores into the local
  database a dump of the environment that sent the applications. The audit
  reads data only and never changes it. (2026-10-10: no dump is available, so
  the plan phase that runs it was waived; run `reports:application-audit`
  when real data exists.)
- **P-D3.** Stage 2 does not wait for the approval of the Stage 1 report. The
  Stage 1 report is still to be produced and shown to the owner.

### Out of scope for now

- AI-generated personalized first line, discovery of named contacts, and
  per-posting CV variants. They will be their own specs.
