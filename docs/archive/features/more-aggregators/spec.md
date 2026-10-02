# talent-labs — More aggregator sources (spec addition)

> **Prompt to give the agent:** "Read this file end to end. This is a small
> addition on top of the already-DONE `job-collection-mvp` and
> `contact-discovery-mvp` (if that one is also done). Reuse the existing
> harness — don't recreate it. Copy Part B into
> `docs/features/more-aggregator-sources/spec.md`, then execute it. Ask
> before any new dependency (there should be none needed here — all 3 new
> sources are public, unauthenticated JSON APIs)."

## Part 0 — Why

`job-collection-mvp` seeded 3 single-company ATS sources (Stripe/Greenhouse,
Palantir/Lever, Linear/Ashby) plus 1 aggregator (Remotive). The 3 ATS
companies dominate posting _volume_ (hundreds of postings each) but
contribute only 3 distinct companies — and structured-ATS companies are
the worst fit for email outreach (formal apply flow, unlikely to have a
working `careers@`/`hr@` alias). The product goal is companies reachable by
email, which correlates with **company diversity**, which aggregators give
for free — each posting is a different employer.

This round: add more aggregator-type sources (no new single-company ATS
sources), so the collected pool skews toward the kind of company contact
discovery actually works for.

## Part 0.1 — Sources confirmed live (2026-09-23)

All three are public, unauthenticated JSON, no API key:

| Aggregator | Endpoint                                             | Shape                                                                                                                                                                                                                                                                                                                                                                                       |
| ---------- | ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RemoteOK   | `GET https://remoteok.com/api`                       | top-level **array**; first element is a `legal`/metadata object, not a job — skip it. Job fields: `id`, `company`, `position`, `description` (HTML), `tags` (array), `date` (ISO), `location`, `salary_min`/`salary_max`, `url`, `apply_url`, `company_logo`.                                                                                                                               |
| Arbeitnow  | `GET https://www.arbeitnow.com/api/job-board-api`    | `{ "data": [...] }`. Fields: `slug`, `company_name`, `title`, `description` (HTML), `remote` (bool), `url`, `tags`, `job_types`, `location`, `created_at` (Unix seconds). No `id` field — use `slug` as the external id. Mostly DACH/EU listings, mixed industries (not tech-only).                                                                                                         |
| Jobicy     | `GET https://jobicy.com/api/v2/remote-jobs?count=50` | `{ "jobs": [...], "jobCount": n, ... }`. Fields: `id`, `url`, `jobSlug`, `jobTitle`, `companyName`, `companyLogo`, `jobIndustry` (array), `jobType` (array), `jobGeo` (comma string), `jobLevel`, `jobExcerpt`, `jobDescription` (HTML), `pubDate` (ISO). Supports query params `count`, `geo`, `industry`, `tag` — not used in this round beyond `count` (default settings JSON, see B.2). |

**Attribution requirement (real, not optional):** RemoteOK's own API response
demands a followed link back to the RemoteOK URL and to be credited as the
source when its data is displayed; Jobicy's response carries a
`friendlyNotice` asking the same. Neither adapter needs to violate this —
`sourceLabel()` (already in the codebase from job-collection-mvp, used for
Remotive as "via Remotive") gets the same treatment for these two:
`"via RemoteOK"`, `"via Arbeitnow"`, `"via Jobicy"` — and the existing
"Open posting" action already links to `url`, which for all three adapters
is the aggregator's own listing page (not a scraped company page), which is
exactly what satisfies the link-back requirement. Nothing new to build here
beyond the label — flag it in the phase evidence so it isn't missed later.

## Part B — Product spec

### B.1 New adapters

Add three cases to the existing `App\Enums\SourceAdapter` enum (alongside
`Greenhouse`, `Lever`, `Ashby`, `Remotive` — don't touch those four):
`RemoteOk = 'remote_ok'`, `Arbeitnow = 'arbeitnow'`, `Jobicy = 'jobicy'`.
None of them `requiresIdentifier()` (same as Remotive — they're global feeds,
not scoped to one company or board). `sourceLabel()` returns `"via RemoteOK"`
/ `"via Arbeitnow"` / `"via Jobicy"` respectively.

Read the existing `RemotiveAdapter` first — these three follow the exact
same shape (`JobSourceAdapter::fetch(Source $source): iterable<JobPostingData>`,
using the shared `InteractsWithJobBoardApi` HTTP trait) and the same
`SourceAdapter::adapter()` resolution switch, extended with the three new
cases.

**`RemoteOkAdapter`**

- `GET https://remoteok.com/api`.
- Skip the first array element if it doesn't have an `id`/`position` field
  (it's RemoteOK's own legal/metadata blob, not a job).
- Mapping: `id` → externalId (string), `position` → title, `company` →
  companyName, `location` → location (nullable — often empty string, treat
  empty as null), isRemote = true (RemoteOK is remote-only by definition),
  department = null (no equivalent field), employmentType = null, `url` →
  url, `apply_url ?? url` → applyUrl, `description` → descriptionHtml,
  stripped (reuse the existing `htmlToText` helper) → descriptionText,
  `date` → publishedAt (`CarbonImmutable::parse`).
- Skip items missing `id`, `position`, `company`, or `url` (same
  malformed-item policy as the existing adapters).

**`ArbeitnowAdapter`**

- `GET https://www.arbeitnow.com/api/job-board-api`, read `data[]`.
- No numeric id in the payload — use `slug` as externalId.
- Mapping: `slug` → externalId, `title` → title, `company_name` →
  companyName, `location` → location, `remote` → isRemote,
  `job_types[0] ?? null` → employmentType, department = null, `url` → url
  and applyUrl (no separate apply link), `description` → descriptionHtml,
  stripped → descriptionText, `created_at` is **Unix seconds** →
  `CarbonImmutable::createFromTimestamp`.
- Same malformed-item skip policy (missing slug/title/company_name/url).

**`JobicyAdapter`**

- `GET https://jobicy.com/api/v2/remote-jobs` with query params built from
  `source.settings` (same pattern as `RemotiveAdapter`'s settings handling):
  `count` (int, default 50 if unset), `geo`, `industry`, `tag` — send only
  the keys actually present in settings, same as Remotive.
- Read `jobs[]` from the response (not the top-level object).
- Mapping: `id` → externalId (string), `jobTitle` → title, `companyName` →
  companyName, `jobGeo` → location, isRemote = true (Jobicy is remote-only),
  `jobIndustry[0] ?? null` → department, `jobType[0] ?? null` →
  employmentType, `url` → url and applyUrl, `jobDescription` →
  descriptionHtml, stripped → descriptionText, `pubDate` → publishedAt.
- Same malformed-item skip policy.

All three: full raw item → `raw`. Reuse the shared HTTP client trait exactly
as the existing four adapters do (timeout, retry, `acceptJson`, user agent,
`throw()` on non-2xx) — don't write a new one.

### B.2 Seeder changes

Extend the existing `SourceSeeder` (idempotent `firstOrCreate` on
`adapter + identifier`, exactly like the current Remotive row) — **add**, don't
modify the existing Remotive row. The fixed-company ATS sources (Stripe/
Palantir/Linear) no longer exist in the seeder and are not re-added:

| name      | adapter   | identifier | settings        | is_active |
| --------- | --------- | ---------- | --------------- | --------- |
| RemoteOK  | remote_ok | null       | —               | true      |
| Arbeitnow | arbeitnow | null       | —               | true      |
| Jobicy    | jobicy    | null       | `{"count": 50}` | true      |

### B.3 Fixed-company ATS sources

Removed. Stripe/Palantir/Linear were dropped from the seeder for good by the
aggregators-only change, so there is nothing to deprioritize and no manual
`is_active` step for the owner.

### B.4 Nothing else changes

Collection flow, realtime, admin resources (Sources/Runs/Job
Postings/Companies), contact discovery pipeline (if already built) — all
already source-agnostic (they iterate "active sources" and dispatch per
adapter via `SourceAdapter::adapter()`). Adding 3 enum cases and 3 adapter
classes should require **zero changes** to any of that code. If executing
this reveals it isn't actually source-agnostic somewhere, that's a bug to
fix, not a sign this spec needs to grow.

### B.5 Acceptance criteria

- **AC01** — `SourceAdapter` has the 3 new cases with correct `sourceLabel()`
  values; the 4 existing cases (Greenhouse/Lever/Ashby/Remotive) are
  unchanged.
- **AC02** — Each of the 3 new adapters, called live in tinker against the
  real endpoint (no DB write), returns more than 0 `JobPostingData` items
  with the mapped fields populated as specified.
- **AC03** — `SourceSeeder` run twice adds exactly the 3 new sources once
  each (idempotent) and leaves the existing Remotive source untouched
  (`Source::count()` goes from 1 to 4, not higher on a second run).
- **AC04** — A full "Collect jobs now" run with all 4 sources active
  completes, and the 3 new sources' postings appear in Job Postings with
  the correct `sourceLabel()` shown and correct `url`/`apply_url`.
- **AC05** — Company diversity increased: after the run, `Company::count()`
  reflects meaningfully more distinct companies than before (report the
  before/after number — don't just assert it, show it), because RemoteOK/
  Arbeitnow/Jobicy each posting is typically a different employer.
- **AC06** — If `contact-discovery-mvp` is already built in this repo, the
  new postings flow through it exactly like existing ones (company
  resolution → domain → contact discovery) with no adapter-specific
  changes needed there.

### B.6 Verification

1. One live tinker call per new adapter (no DB write), report item count +
   first item's key fields.
2. `db:seed` run twice, report `Source::count()` before/after (expect 1 →
   4 → 4).
3. One full collection run with all 4 sources active; report per-source
   counts, total `jobs_new`, and the before/after `Company::count()`.
4. Confirm in the admin: Job Postings shows the 3 new sources' badges/labels
   correctly, "Open posting" links resolve to the aggregator's page (not a
   broken URL).

### B.7 Out of scope

Query-param exposure for Jobicy's `geo`/`industry`/`tag` in the admin UI
beyond what `SourceResource`'s existing generic `KeyValue` settings field
already allows (it already supports arbitrary key-value settings per
source — no new form field needed); deactivating the ATS sources
automatically; any other new aggregator beyond these 3; retry/backoff
tuning per source; anything already out of scope in the prior two specs.
