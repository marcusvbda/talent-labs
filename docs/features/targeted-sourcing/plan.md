# Plan — Targeted sourcing (more relevant postings for dev, support, product and CS roles)

Source spec: none yet — this plan was written from an owner request
(2026-09-26) plus a read-only audit and live API research. The **Proposal**
section below is the product contract. If the owner wants the usual
`/plan-spec` discipline, promote Proposal into `spec.md` in this folder and
re-derive the plan from it (no duplicated content between the two).
Run phases with `/execute-phases docs/features/targeted-sourcing/plan.md <phases>`
— one or a few per session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                                   | Role            | Depends on | Size | Status              |
| ----- | ------------------------------------------------------- | --------------- | ---------- | ---- | ------------------- |
| 1     | Role classifier + `role_family` on postings             | laravel-backend | none       | M    | DONE                |
| 2     | Relevance gate: discovery + matching only target roles  | laravel-backend | 1          | S    | DONE                |
| 3     | Admin: role family column/filter + settings hints       | filament-admin  | 1          | S    | DONE                |
| 4     | Query-side targeting for RemoteOK, Jobicy and Arbeitnow | laravel-backend | none       | M    | DONE                |
| 5     | New source: Himalayas (search API)                      | laravel-backend | 4          | M    | DONE                |
| 6     | New source: We Work Remotely (category RSS)             | laravel-backend | 4          | M    | DONE                |
| 7     | New source: Working Nomads                              | laravel-backend | 4          | S    | DONE                |
| 8     | New source: Hacker News "Who is hiring?"                | laravel-backend | 4          | M    | DONE                |
| 9     | Company website hint → domain resolution                | laravel-backend | 8          | M    | DONE                |
| 10    | Verification and report (before/after funnel)           | qa-tester       | 1–8 (9)    | S    | BLOCKED (owner run) |

## Audit — 2026-09-26

### Where today's postings get lost (read-only DB queries)

Two runs so far (#1 on 25 Sep, #2 on 26 Sep). 670 postings, 376 companies.

| Source    | Postings | Dev-ish titles¹ | Support/CS¹ | Product¹ | Company `verified` | Dev + verified |
| --------- | -------- | --------------- | ----------- | -------- | ------------------ | -------------- |
| Arbeitnow | 479      | 122             | 27          | 31       | 72                 | 14             |
| RemoteOK  | 100      | 27              | 7           | 4        | 14                 | 2              |
| Jobicy    | 72       | 26              | 7           | 3        | 27                 | 14             |
| Remotive  | 19       | 11              | 2           | 0        | 0                  | 0              |

¹ Title regex over the whole table, rough.

Company outreach status: `verified` 63 companies / 113 postings (≈ the "112
jobs" the owner sees on the Jobs page), `not_verifiable` 242 / 403,
`no_domain` 71 / 154.

Findings:

1. **Sources are fetched unfiltered.** No adapter sends a role/category
   filter. Arbeitnow is 71% of the volume and mostly on-site sales,
   marketing, finance and internships in FR/UK/DE. Of the 113 postings in the
   pool, ~30 are dev roles; the rest is noise the owner has to scroll past.
2. **Every posting goes through contact discovery**, relevant or not
   (`FetchJobsFromSource` dispatches `DiscoverContactsForPosting` for every
   new posting). Irrelevant postings cost SMTP probes (IP reputation) and,
   once verified, AI profile extraction (gpt-4o-mini).
3. **Matching does not know the role family.** `MatchingJobPostings` only
   filters by the client's `job_preferences`; the only client preference row
   (user 2) is **completely empty**, so the pool is returned unfiltered.
   Quick win with no code: fill Preferences → Titles (e.g. "backend",
   "frontend", "full stack", "developer", "engineer", "support", "product").
4. **The biggest leak is after collection:** only 17% of postings reach a
   `verified` company. `ResolveCompanyDomain` guesses `<slug>.com` only (71
   companies without a domain, and some guesses may be the wrong company);
   242 companies have a domain but only `catch_all`/`mx_only` contacts, which
   the outreach rules exclude. More relevant _input_ helps, but this ratio
   stays unless the domain is known from the source (Phase 9) — see D3.
5. **Remotive's public API is capped** at ~19–20 jobs and ignores
   `category` (tested: `category=software-development` returned a German
   call-centre job). It is 24h delayed by design. Low value; leave as is.

### Live API research (all public, no key, tested 2026-09-26)

| Source                 | Endpoint / filter tested                                                              | Result                                                                                                                                                                                                                                                          |
| ---------------------- | ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RemoteOK               | `GET https://remoteok.com/api?tag=backend`                                            | Works: 100 items, all backend/frontend engineering. Same shape as today (first element = legal blob).                                                                                                                                                           |
| Jobicy                 | `GET https://jobicy.com/api/v2/remote-jobs?count=100&industry=engineering`            | Works: 100 engineering jobs. Industry slugs (`?get=industries`): `engineering`, `technical-support`, `supporting`, `qa-testing`, `web-app-design`, `project-management`, … Fair use: at most one automated check per hour.                                      |
| Arbeitnow              | `GET https://www.arbeitnow.com/api/job-board-api?page=N`                              | Pagination works (100–250/page, `links.next`). `?search=` is **ignored**. Documented filters: `remote=true`, `visa_sponsorship=true`. Only local filtering can target roles.                                                                                    |
| Himalayas (new)        | `GET https://himalayas.app/jobs/api/search?q=backend&page=N`                          | Works: `totalCount` 5000, 20/page. Params: `q`, `country`, `worldwide`, `exclude_worldwide`, `seniority`, `employment_type`, `company`, `sort`, `page`. Returns 429 when rate-limited. Requires visible link back + "data from Himalayas". Refreshes every 24h. |
| We Work Remotely (new) | `GET https://weworkremotely.com/categories/<slug>.rss`                                | RSS/XML. Item counts today: `remote-programming-jobs` 25, `remote-full-stack-programming-jobs` 41, `remote-customer-support-jobs` 29, `remote-product-jobs` 7, `remote-back-end-programming-jobs` 6. Title is `"Company: Role"`. PHP `SimpleXML` is installed.  |
| Working Nomads (new)   | `GET https://www.workingnomads.com/api/exposed_jobs/`                                 | 57 items; `category_name` counts: Development 28, Customer Success 6, … No id field (id lives in `url` `/job/go/{id}/`). Small but cheap.                                                                                                                       |
| HN Who is hiring (new) | Algolia: `search_by_date?tags=story,author_whoishiring` then `GET /api/v1/items/{id}` | Sept 2026 thread: 256 top-level posts, **72 contain an e-mail address**, header line is `Company \| Role \| Location \| …` and usually has the **company website** → domain known without guessing. Best fit for e-mail outreach; mostly dev roles.             |
| Adzuna                 | not tested (needs free `app_id`/`app_key`)                                            | Large volume per country (incl. BR, PT, UK, DE) with `what=` search. Needs registration + `.env` keys → D4.                                                                                                                                                     |
| Gupy (BR)              | `portal.api.gupy.io` guesses                                                          | 404 — no stable public endpoint found. Not planned.                                                                                                                                                                                                             |

Not recommended: LinkedIn/Indeed/Glassdoor scraping (ToS, anti-bot); more
fixed single-company ATS boards (the `more-aggregators` spec already dropped
them — low e-mail reachability). Aggregator `apply_url`s in the DB never point
to an ATS (all masked by the aggregator), so "ATS harvesting" from them is not
possible either.

### Repo facts the phases rely on

| Check              | Result                                                                                                                                        |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Adapter contract   | `JobSourceAdapter::fetch(Source): iterable<JobPostingData>`; shared HTTP trait `InteractsWithJobBoardApi` (`acceptJson`, `throw`, retry 2).   |
| Fetch job          | `FetchJobsFromSource`: collects the whole iterable, then upserts in chunks; `$timeout = 120`; any exception fails the whole source run.       |
| Sources unique key | `(adapter, identifier)`; aggregators have `identifier = null`. Seeder uses `firstOrCreate(adapter, identifier)` → **one row per aggregator**. |
| Settings           | `sources.settings` JSON, edited as a generic KeyValue (string values) in `SourceForm`.                                                        |
| Discovery trigger  | `FetchJobsFromSource` → `DiscoverContactsForPosting` per new posting → company resolve/domain/SMTP → `ExtractJobPostingProfileJob`.           |
| Matching           | `App\Outreach\Queries\MatchingJobPostings::forUser()` — single source of truth for the client Jobs page.                                      |
| Migrations         | `2026_09_23_120002_create_job_postings_table.php` exists; no dependency changes needed anywhere in this plan.                                 |

## Proposal (product contract)

1. **Ask sources for the right jobs** instead of taking the global feed:
   per-source settings that turn one source into several targeted requests
   (tags, industries, queries, categories, pages).
2. **Add four sources** that carry many dev/support/product roles: Himalayas,
   We Work Remotely, Working Nomads, Hacker News "Who is hiring?".
3. **Classify every posting into a role family** at ingestion
   (deterministic title/tag rules, multilingual EN/DE/FR/PT/ES — no AI), store
   it, and only spend contact discovery on postings in the target families.
   Non-target postings stay stored and visible in the admin.
4. **Show clients only target-family postings** on the Jobs page (on top of
   their own preferences).
5. **(D3)** Use the company website a source gives us (HN) before guessing
   `<slug>.com`.

Role families (enum `RoleFamily`, string-backed):

| Case            | Value              | Label            | Examples (title, any language)                                                      |
| --------------- | ------------------ | ---------------- | ----------------------------------------------------------------------------------- |
| Backend         | `backend`          | Backend          | backend, back-end, api, php, laravel, node, golang, java, python, ruby, .net, rails |
| Frontend        | `frontend`         | Frontend         | frontend, front-end, react, vue, angular, ui engineer, web developer                |
| Fullstack       | `fullstack`        | Full stack       | full stack, fullstack, full-stack                                                   |
| Software        | `software`         | Software (other) | software engineer/developer, programmer, entwickler, développeur, desenvolvedor     |
| Mobile          | `mobile`           | Mobile           | ios, android, flutter, react native, mobile                                         |
| Devops          | `devops`           | DevOps / SRE     | devops, sre, site reliability, platform engineer, infrastructure engineer           |
| Qa              | `qa`               | QA               | qa, quality assurance, test engineer, sdet, tester                                  |
| Support         | `support`          | Support          | support engineer, technical support, help desk, it support, suporte, kundenservice  |
| CustomerService | `customer_service` | Customer service | customer success, customer service, customer care, customer experience, atendimento |
| Product         | `product`          | Product          | product manager, product owner, product analyst, product operations                 |

Rules: exclusions win (sales, account executive/manager, business
development, marketing, recruiter, accountant, legal, designer — including
"product designer"; "engineering manager" → `software`). First matching family in the order Fullstack → Backend →
Frontend → Mobile → Devops → Qa → Product → Support → CustomerService →
Software. No match → `null` ("other"). Title first; source tags/category
only as a fallback when the title matches nothing and isn't excluded.

## Owner decisions

### D1 — Geography / work model for targeting

**Answer (2026-09-26): A** — remote worldwide, Arbeitnow stays active.

Blocks: nothing (defaults below are used) · affects default settings in
Phases 4–8.
Options: **A (recommended)** remote-first worldwide + keep Arbeitnow (EU,
mostly on-site) but only its target-family postings get discovered; B remote
only → deactivate Arbeitnow, set Himalayas `worldwide=true`; C specific
countries (tell which: BR, PT, EU, US…) → Himalayas `country=`, Jobicy `geo=`.
Why: Arbeitnow is 71% of the volume and mostly on-site FR/UK/DE; whether that
is useful depends on where you can work.

### D2 — What happens to non-target postings

**Answer (2026-09-26): A** — store as "other", skip discovery, hide from clients.

Blocks: Phase 2.
Options: **A (recommended)** store them, classify as "other", skip contact
discovery and hide them from clients (admin still sees them — lets us audit
the classifier); B drop them at ingestion (smaller DB, but misclassified jobs
vanish silently).

### D3 — Company website before `<slug>.com` in domain resolution

**Answer (2026-09-26): A** — use the source's website host first, fall back to `<slug>.com`.

Blocks: Phase 9. It changes behaviour defined in
`docs/features/contact-discovery/spec.md`, so it needs the owner's explicit OK
(and ideally a spec addition there).
Options: **A (recommended)** if the posting carries a company website (HN
today; any future source that has one), use its host (without `www.`) as the
first candidate, fall back to `<slug>.com`; B keep `<slug>.com` only; C also
try `.io/.co/.ai/.dev` guesses (more domains found, but more wrong-company
matches → e-mails to strangers; not recommended).

### D4 — Adzuna (API key) as a later source

Blocks: nothing in this plan (not included). Options: A add after Phase 10 if
the numbers are still low (needs you to register a free key; `.env`
`ADZUNA_APP_ID/ADZUNA_APP_KEY`, appended, never overwritten); B skip.

## Global constraints (every phase)

- CLAUDE.md hard rules: git read-only, no destructive DB commands, no new
  dependencies, no tests unless the owner asks, English only, realtime
  (never `->poll()`), scope = the phase contract.
- New columns go **into the table's create migration** (owner's development
  rule); the owner runs `migrate:fresh` themselves. Never run it from a
  phase; say in the evidence that it is needed.
- `job-collection` invariants: adapter contract unchanged; `raw` keeps the
  full item; `companyName` from payload; dedup key `(source_id,
external_id)`; never touch `collection_run_id`/`first_seen_at` of existing
  postings; `JobPostingsUpdated` once per source run.
- Aggregators keep their original `url` (attribution link-back) and get a
  `sourceLabel()` "via X".
- New aggregators: `requiresIdentifier()` false, `identifier` null, one
  source row per adapter; multiple targeted requests are driven by
  comma-separated settings (KeyValue stores strings).
- Be polite: at most **10 HTTP requests per source per run**, sequential,
  250 ms pause between them, and adapters stop paging (keeping what they
  already have) on HTTP 429 instead of failing the source run. Any other
  non-2xx still throws.
- Adapters that make several requests must dedupe by external id before
  yielding.
- Deterministic mapping only (regex/string rules). No AI in adapters or in the
  classifier.

## Acceptance-criteria coverage

| AC                                                                                               | Phases  |
| ------------------------------------------------------------------------------------------------ | ------- |
| AC01 Every new/updated posting has `role_family` set by the classifier (or null = other)         | 1       |
| AC02 Contact discovery is dispatched only for target-family postings                             | 2       |
| AC03 Client Jobs page shows only target-family postings (plus existing preference filters)       | 2       |
| AC04 Admin Job Postings shows and filters by role family                                         | 3       |
| AC05 RemoteOK/Jobicy/Arbeitnow honour the new targeting settings; empty settings = old behaviour | 4       |
| AC06 Himalayas, WWR, Working Nomads, HN adapters return > 0 items live, fields mapped            | 5,6,7,8 |
| AC07 Seeder adds the new sources idempotently with the default targeting settings                | 4–8     |
| AC08 (D3) HN postings with a website resolve the company domain from it                          | 9       |
| AC09 A full run reports more target-family postings in the verified pool than the baseline       | 10      |

## Phases

### Phase 1 — Role classifier + `role_family` on postings

Status: DONE
Role: laravel-backend · Depends on: none · Covers: AC01 · Size: M

**Goal.** Every posting written by `FetchJobsFromSource` gets a
deterministic `role_family`.

**Contract.**

- Enum `App\Enums\RoleFamily: string implements HasLabel` with the cases,
  values and labels in the Proposal table.
- `App\Collection\Support\RoleClassifier` with
  `public function classify(string $title, array $tags = []): ?RoleFamily`
  implementing the rules in Proposal (exclusions first, family order, title
  then tags fallback). Case-insensitive, word-boundary regexes, Unicode,
  strips gender markers like `(m/w/d)`, `(f/m/x)`, `(H/F)`, `(all genders)`
  before matching. Keyword lists live as constants in the class.
- `config/talent.php` → `'collection' => ['target_role_families' => [...all
RoleFamily values...]]` (all families targeted by default).
- Column `job_postings.role_family` string nullable + index, added **inside**
  `2026_09_23_120002_create_job_postings_table.php`. `JobPosting` casts it to
  `RoleFamily`.
- `FetchJobsFromSource`: compute `role_family` per item (tags from
  `raw.tags` / `raw.jobIndustry` / `raw.category` / `raw.category_name` when
  they are strings or lists of strings) and write it in the upsert rows; add
  `role_family` to `MUTABLE_COLUMNS`.

**Steps.**

1. Enum + classifier + config key.
2. Migration column + model cast + PHPDoc property.
3. Wire into `FetchJobsFromSource`.
4. Tinker smoke: classify 20 real titles from today's DB (list them with the
   result in the evidence), e.g. "Senior Backend Engineer - Payment Squad" →
   backend, "Werkstudent Technical Support & Self-Service (m/w/d)" → support,
   "Account Executive - Financial Services" → null, "Product Manager Grower
   Experience (m/w/d)" → product.

**Done when:** project-core lint/static/type checks pass; smoke list shows
sensible results; evidence says the owner must run `migrate:fresh` +
`db:seed`.

Evidence: pint --dirty and phpstan (level 7, `--memory-limit=1G`) pass; `composer lint:check` only fails on pre-existing `ApplicationTemplateRenderer.php`. Tinker: 16 contract titles + 30 real titles classify as expected. Review APPROVED; two review notes fixed (tag fallback now only trusted when the title has an engineering-role noun, so "Content Reviewer" with android/ios tags → null; `React-Native` with hyphen → mobile). Owner must run `migrate:fresh --seed` for the new `role_family` column (create-migration edit).

### Phase 2 — Relevance gate for discovery and matching

Status: DONE
Role: laravel-backend · Depends on: 1 · Covers: AC02, AC03 · Size: S

**Goal.** Only target-family postings cost SMTP probes/AI and reach clients.
(Implements D2 option A unless the owner chose B — then this phase instead
skips non-target items before the upsert and nothing else.)

**Contract.**

- `FetchJobsFromSource`: dispatch `DiscoverContactsForPosting` only for new
  postings whose `role_family` is in `config('talent.collection.target_role_families')`.
- `MatchingJobPostings::forUser()`: add
  `whereIn('job_postings.role_family', <target families>)` to the base pool
  (a company verified through a dev posting must not surface its sales
  postings). Update the docblock.

**Done when:** checks pass; tinker shows a non-target posting is not
dispatched (inspect `jobs` table count on queue for a controlled source run or
reason from code in evidence) and `MatchingJobPostings::forUser($user)->count()`
excludes `role_family IS NULL`.

Evidence: pint + phpstan (level 7) pass. `MatchingJobPostings::forUser()->toSql()` contains `job_postings.role_family in (…10 families…)` in the base pool; discovery dispatch query in `FetchJobsFromSource` filters on the same config list. Review APPROVED. Local DB lacks `role_family` until the owner runs `migrate:fresh --seed`, so the Jobs page/fetch job error locally until then.

### Phase 3 — Admin: role family column/filter + settings hints

Status: DONE
Role: filament-admin · Depends on: 1 · Covers: AC04 · Size: S

**Contract.**

- `JobPostingsTable`: badge column "Role" (label from enum, "Other" for
  null), `SelectFilter` "Role family" with an extra "Other" option
  (`whereNull`).
- `JobPostingInfolist`: "Role family" entry.
- `SourceForm` settings KeyValue: helper text per adapter listing the
  supported keys (from Phases 4–8; plain text: "RemoteOK: tags (comma list).
  Jobicy: industries (comma list), count, geo. Arbeitnow: pages, remote.
  Himalayas: queries (comma list), pages, country, worldwide. We Work
  Remotely: categories (comma list). Working Nomads: categories (comma list).
  Hacker News: none.").
- Keep realtime as is (`Table::socket()`), no polling.

**Done when:** checks pass; admin shows the column and filter.

Evidence: pint + phpstan pass; `route:list --path=admin` boots; no `poll`. Role badge column, "Role family" filter (+ Other = whereNull) and infolist entry added. Review round 1 fix: the settings helper text is now per adapter (`SETTINGS_HINTS`, keyed by adapter value, "No settings used." fallback) instead of one global hint. Render check on the list page not possible until `role_family` exists in the local DB.

### Phase 4 — Query-side targeting for RemoteOK, Jobicy and Arbeitnow

Status: DONE
Role: laravel-backend · Depends on: none · Covers: AC05, AC07 · Size: M

**Contract.**

- Shared helper in `InteractsWithJobBoardApi`: `settingList(Source, string
$key): list<string>` (comma-split, trimmed, unique, non-empty) and a
  `pause()` (250 ms) plus the "stop paging on 429" pattern (catch
  `RequestException` with status 429 → stop, keep collected items).
- `RemoteOkAdapter`: setting `tags` → one `GET https://remoteok.com/api?tag=<tag>`
  per tag (max 10), merge, dedupe by `id`. No `tags` → current single call.
- `JobicyAdapter`: setting `industries` → one request per industry with
  `industry=<slug>` (plus existing `count`, `geo`, `tag`); `count` default
  stays 50, max 100. No `industries` → current behaviour (existing
  `industry` key still honoured).
- `ArbeitnowAdapter`: settings `pages` (int, default 1, max 5; follow
  `links.next` until reached or null) and `remote` (`"true"` → `remote=true`).
- `SourceSeeder` defaults for these rows (only applies to newly created rows —
  `firstOrCreate`): RemoteOK `tags = backend,frontend,full stack,php,javascript,react,node,devops,customer support,product`
  (max 10); Jobicy `industries = engineering,technical-support,supporting,qa-testing,web-app-design`,
  `count = 100`; Arbeitnow `pages = 3`. Remotive unchanged.
- D1 = B → seeder sets Arbeitnow `is_active = false`. D1 = C → Jobicy `geo`
  from the owner's answer.

**Done when:** checks pass; live tinker per adapter (no DB write) reports item
count and 5 titles with and without settings; running the seeder twice keeps
`Source::count()` stable.

Evidence: pint + phpstan pass. Live (no DB writes): RemoteOK tags → 10 requests / 499 items; Jobicy industries → 5 requests / 351; Arbeitnow pages=3 → 3 requests / 600; no-settings paths unchanged (99 / 50 / 250). Review round 1 fixes: helper is a single attempt (`retry(1)`) so the 10-request budget holds, and a 429 before any posting was collected throws (`stopOnRateLimit`) instead of finishing empty. Offline `Http::fake`: 500 → 1 request + throws; 429 first → RuntimeException; 429 on request 3 → keeps 2 items. Behaviour change: Jobicy `count` is now capped at 100 (documented API max). Owner action: existing RemoteOK/Jobicy/Arbeitnow rows keep their old settings (seeder only affects new rows) → edit them in /admin or run `migrate:fresh --seed`.

### Phase 5 — New source: Himalayas

Status: DONE
Role: laravel-backend · Depends on: 4 · Covers: AC06, AC07 · Size: M

**Contract.**

- `SourceAdapter::Himalayas = 'himalayas'`, label "Himalayas", color gray,
  `requiresIdentifier()` false, `sourceLabel()` "via Himalayas", adapter
  `HimalayasAdapter`.
- `GET https://himalayas.app/jobs/api/search` with `q=<query>`, `page=1..pages`
  for each query in setting `queries`; optional `country`, `worldwide`,
  `seniority` passed through. Total requests capped at 10 (queries × pages).
  Stop a query's paging when a page returns fewer than 20 jobs. 429 → stop,
  keep items.
- Mapping: `guid` → externalId and url; `title`; `companyName`; location =
  `locationRestrictions` joined with ", " or null; isRemote true; department =
  `parentCategories[0]`; employmentType; applyUrl = `applicationLink`;
  descriptionHtml = `description`, text via `htmlToText`; publishedAt =
  `pubDate` (Unix seconds); raw. Skip items without guid/title/companyName.
- Seeder row "Himalayas", settings `queries = backend developer,frontend developer,full stack developer,software engineer,customer support,product manager`,
  `pages = 1` (6 requests). D1 = C → `country`.

**Done when:** checks pass; live tinker > 0 items with fields mapped;
seeder idempotent.

Evidence: pint + phpstan pass. Live (no DB writes): seeder settings → 6 requests / 117 items (first titles: Lead Backend Developer, Senior Backend Developer, Backend Engineer…), no settings → 1 request / 9 items. Offline `Http::fake`: 429 first → RuntimeException, 429 on request 3 → partial kept, 4 queries × 5 pages → exactly 10 requests, short page stops paging. `timestampOrNull()` moved from `ArbeitnowAdapter` into the shared trait. Review: covered by the combined review of Phases 5–8.

### Phase 6 — New source: We Work Remotely (RSS)

Status: DONE
Role: laravel-backend · Depends on: 4 · Covers: AC06, AC07 · Size: M

**Contract.**

- `SourceAdapter::WeWorkRemotely = 'we_work_remotely'`, label "We Work
  Remotely", `sourceLabel()` "via We Work Remotely", no identifier.
- For each slug in setting `categories` (max 10):
  `GET https://weworkremotely.com/categories/<slug>.rss` with
  `Accept: application/rss+xml, application/xml` (override `acceptJson`),
  parse with `simplexml_load_string` (`LIBXML_NOCDATA`); invalid XML →
  throw.
- Mapping per `<item>`: externalId = `guid` (else `link`); title/company from
  `<title>` split on the first ": " (company before, role after; no ": " →
  skip); location = `<region>` if present; isRemote true; department =
  `<category>`; employmentType = `<type>`; url and applyUrl = `<link>`;
  descriptionHtml = `<description>`; publishedAt = `<pubDate>`; raw = the item
  converted to array (`json_decode(json_encode($item), true)`).
- Seeder row "We Work Remotely", `categories = remote-programming-jobs,remote-full-stack-programming-jobs,remote-back-end-programming-jobs,remote-front-end-programming-jobs,remote-customer-support-jobs,remote-product-jobs,remote-devops-sysadmin-jobs`.

**Done when:** checks pass; live tinker > 0 items, company/title split
correct on 5 samples; seeder idempotent.

Evidence: pint + phpstan pass. Live (no DB writes): seeder settings → 7 requests / 100 unique postings, company/role split correct incl. titles with a second colon; no settings → 1 request / 25 postings. Offline `Http::fake`: 429 first → RuntimeException, 429 on request 3 → partial kept, malformed XML → RuntimeException, title without ": " skipped, XML `Accept` header sent, slugs `rawurlencode`d. Deviation from the contract: the trait's optional `$headers` param uses `replaceHeaders()` (Laravel's `withHeaders()` merges and would also send the JSON Accept). Note: the full-stack feed has no `<type>`, so `employment_type` is null there. Review: covered by the combined review of Phases 5–8.

### Phase 7 — New source: Working Nomads

Status: DONE
Role: laravel-backend · Depends on: 4 · Covers: AC06, AC07 · Size: S

**Contract.**

- `SourceAdapter::WorkingNomads = 'working_nomads'`, label "Working Nomads",
  `sourceLabel()` "via Working Nomads", no identifier.
- `GET https://www.workingnomads.com/api/exposed_jobs/` (top-level array).
  Optional setting `categories` (comma list, case-insensitive match on
  `category_name`); empty → all.
- Mapping: externalId = numeric id parsed from `url` (`/job/go/(\d+)/`), else
  the url; `title`; `company_name`; `location`; isRemote true; department =
  `category_name`; url and applyUrl = `url`; descriptionHtml = `description`;
  publishedAt = `pub_date`. `tags` is a comma string → keep in raw.
- Seeder row "Working Nomads", `categories = Development,Customer Success`.

**Done when:** checks pass; live tinker > 0 items; seeder idempotent.

Evidence: pint + phpstan pass. Live (no DB writes): seeder settings (`Development,Customer Success`) → 1 request / 34 items; no settings → 57 items. Offline `Http::fake`: 429 → RuntimeException, category filter case-insensitive, item without url skipped, externalId falls back to the url, duplicates deduped. Note: the "Customer Success" category also carries phone-sales agent roles; the role classifier (Phase 1) keeps them out of the target pool. Review: covered by the combined review of Phases 5–8.

### Phase 8 — New source: Hacker News "Who is hiring?"

Status: DONE
Role: laravel-backend · Depends on: 4 · Covers: AC06, AC07 · Size: M

**Contract.**

- `SourceAdapter::HackerNews = 'hacker_news'`, label "Hacker News",
  `sourceLabel()` "via HN Who is hiring", no identifier, no settings.
- Find the thread: `GET https://hn.algolia.com/api/v1/search_by_date?tags=story,author_whoishiring&hitsPerPage=10`,
  first hit whose title starts with "Ask HN: Who is hiring?". Then
  `GET https://hn.algolia.com/api/v1/items/{objectID}` (whole tree; timeout
  30 s for this call).
- Each top-level child with non-empty `text` is one posting. Header = text
  before the first `<p>`, HTML-decoded, tags stripped, split on `|` into
  trimmed segments. Require ≥ 2 segments, else skip.
    - companyName = segment 0 with any URL/parenthetical removed.
    - title = first segment that the `RoleClassifier` (Phase 1 is not a
      dependency — use a small local regex `/(engineer|developer|programmer|
manager|designer|support|success|product|sre|devops|lead|architect|
scientist|analyst)/i`) matches, else segment 1.
    - location = first segment matching `/remote|onsite|on-site|hybrid|,/i`
      other than the title, else null; isRemote = header matches `/remote/i`.
    - employmentType = segment matching `/full[- ]?time|part[- ]?time|contract/i`.
    - externalId = child `id`; url = `https://news.ycombinator.com/item?id={id}`;
      applyUrl = first `href` in the text that isn't news.ycombinator.com,
      else url; descriptionHtml = `text`; publishedAt = `created_at`; raw = child
      without its `children`.
- Seeder row "Hacker News", no settings.

**Done when:** checks pass; live tinker reports count (expect ~200+) and 10
parsed headers (company | title | location) that look right; seeder
idempotent.

Evidence: pint + phpstan pass. Live (no DB writes): current thread (49522897) → exactly 2 requests; 256 top-level comments → 173 postings (after the correction below). Skipped: 16 without pipes, the rest without a role-like segment. RoleClassifier over the 173 titles: software 36, fullstack 19, backend 11, devops 11, product 4, frontend 3, mobile 1, null 88. Offline `Http::fake`: "Who wants to be hired?" ignored, no thread → RuntimeException, deleted/textless child skipped, header without pipes skipped, first non-HN `href` used as `apply_url` (entity-decoded, `mailto:` ignored), 500 on the items call throws. Correction to the contract (orchestrator): the "first non-title segment" title fallback was removed — it picked places/slogans as titles ("San Francisco, CA", "√ PMF"); posts with no role-like segment are now skipped. Known limitation: bare titles such as "Data Engineer", "Machine Learning Engineer", "Senior Engineer (Platform)" are `null` in the classifier by design (bare "engineer" is too broad: "Electrical Engineer"); revisit in the Phase 10 classifier audit. Review (Phases 5–8, round 1): 5, 6, 7 APPROVED; 8 CHANGES_REQUIRED → fixed: a `pause()` between the Algolia search and the items request (measured 262 ms), and the free-text header fields `title`, `company_name`, `location`, `employment_type` are cut to 255 chars (`fit()`) so an oversized segment cannot fail the whole upsert chunk (`varchar(255)` columns). Verified offline with 400-char segments.

### Phase 9 — Company website hint → domain resolution

Status: DONE
Role: laravel-backend · Depends on: 8 · Covers: AC08 · Size: M

**Contract.**

- `JobPostingData`: new last constructor param `public ?string $companyWebsite = null`
  (named-arg default → other adapters untouched).
- Column `job_postings.company_website` string nullable in the create
  migration; written by `FetchJobsFromSource` and in `MUTABLE_COLUMNS`.
- `HackerNewsAdapter`: companyWebsite = first URL in the header (or the first
  `href` in the text) whose host is not a known job board / ATS / HN
  (`ycombinator.com`, `greenhouse.io`, `lever.co`, `ashbyhq.com`,
  `workable.com`, `linkedin.com`, `github.com`, `notion.site`, `google.com`).
- `ResolveCompanyDomain`: before the `<slug>.com` guess, take the host of the
  most recent non-null `company_website` among the company's postings
  (strip `www.`); if it resolves (same `resolves()` rule), use it; else fall
  back to the current guess. Docblock updated.

**Done when:** checks pass; tinker on 5 HN postings shows the domain taken
from the website; a company without website still gets `<slug>.com`.

Evidence: pint + phpstan pass; no DB access (the local DB lacks the new columns until `migrate:fresh`). Live HN run: 121 of 173 postings carry a `company_website` (header URL first, then a body link). `registrableDomain()` via reflection: `careers.foo.com` → foo.com, `www.bar.co.uk` → bar.co.uk, `baz.com.br` → baz.com.br, `localhost`/IPs → skipped, `a.b.c.example.io` → example.io. `candidates()`: website `careers.foo.com` + "Foo Inc" → [foo.com]; `foo.io` + "Foo" → [foo.io, foo.com]; none → [foo.com]. Deviations/hardening (orchestrator, after a live run): (1) short-link hosts added to the denylist (a Greenhouse `grnh.se` link had become the company domain); (2) a link found only in the comment body is trusted only when its domain label matches the company name (`hostMatchesCompany`), so an article/partner link cannot become the domain that receives the application — header URLs are still trusted as-is; this dropped 127 → 121. Column type is `text` (not `string`) so long URLs cannot fail the upsert. Follow-up for the owner: `docs/features/contact-discovery/spec.md` still describes only the `<slug>.com` guess; it is product truth and was not edited (add a note if you want the doc to match D3).

### Phase 10 — Verification and report

Status: BLOCKED (owner run) — needs `migrate:fresh --seed` + a collection run, which only the owner runs (CLAUDE.md, create-migration rule)
Role: qa-tester · Depends on: 1–8 (and 9 if unblocked) · Covers: AC09 · Size: S

**Steps.**

1. Full deterministic gate (project-core commands).
2. Grep: no `->poll(`/`wire:poll`, no new composer/npm deps, no AI calls in
   `app/Collection`.
3. Owner runs `migrate:fresh --seed` (owner only) and "Collect jobs now";
   wait for discovery to finish.
4. Report per source: fetched, new, target-family count, companies
   `verified`, postings in the client pool. Compare with the baseline in this
   plan's Audit (pool 113, ~30 dev). Report requests per source and any 429.
5. Classifier audit: 30 random postings with `role_family` + title; list
   misclassifications to tune keyword lists.
6. AC walkthrough AC01–AC09 and owner checklist: Jobs page shows mostly
   dev/support/product/CS roles; "Open posting" links go to the aggregator
   page; admin Role filter works.

**Evidence (done without the owner's run).**

- Gate: `phpstan` level 7 (`--memory-limit=1G`; the composer script's default 128M crashes) passes with 0 errors; `pint --dirty` passes; existing Pest tests 2/2 (SQLite in memory, dev DB untouched); `composer lint:check` fails only on the pre-existing, untouched `app/Outreach/Support/ApplicationTemplateRenderer.php`. No frontend files changed, so `yarn check` was not needed. `composer ci:check` was not run as a whole for the same reason.
- Greps: no `->poll(`/`wire:poll`/`pollingInterval` in `app`/`resources`; no `composer.json`/`composer.lock`/`package.json`/`yarn.lock` changes; no AI usage in `app/Collection` (the only "Agent" match is `withUserAgent`).
- Reviews: per-phase reviews for 1–4, a combined review for 5–8, and a final integrated review of everything (2 blockers: shared-hosting domains, varchar(255) overflow — both fixed, focused re-review APPROVED).
- Offline funnel estimate on the 670 postings already in the DB (classifier applied read-only):

    | Source    | Postings | Target family | %   |
    | --------- | -------- | ------------- | --- |
    | Arbeitnow | 479      | 106           | 22% |
    | RemoteOK  | 100      | 36            | 36% |
    | Jobicy    | 72       | 24            | 33% |
    | Remotive  | 19       | 11            | 58% |

    Current client pool (verified company + profile done): 113 postings → **33** with the role gate (software 8, support 6, backend 5, customer_service 4, product 4, devops 2, qa 2, fullstack 1, frontend 1). Contact discovery would run for 177 of 670 postings instead of all 670. Only 1 fullstack + 1 frontend + 5 backend in today's pool: the extra volume has to come from the new sources.

- Live source yield with the seeded settings (no DB writes): RemoteOK tags 499 items / 10 requests; Jobicy industries 351 / 5; Arbeitnow pages=3 600 / 3; Himalayas 117 / 6; We Work Remotely 100 / 7; Working Nomads 34 / 1; Hacker News 173 / 2 (120 with a company website).

**Owner checklist (needed for AC09 and to see the feature).**

1. `php artisan migrate:fresh --seed` (owner only): the create migration gained `job_postings.role_family` (indexed) and `job_postings.company_website`. Until then the Jobs page and the fetch job error locally. It also creates the 4 new sources with targeting settings; the existing 4 rows keep their old settings otherwise (edit them in /admin → Sources if you keep your data).
2. Run "Collect jobs now", let discovery/extraction finish, then compare: postings per source, `role_family` distribution, companies `verified`, size of the client pool. Baseline: pool 113 (≈30 dev-ish), 63 verified companies.
3. Fill Preferences (titles/stack) for the client user — the only preference row is empty, so the pool is otherwise unfiltered by preference.
4. Admin: Job Postings → Role column and "Role family" filter; Sources → settings helper text per adapter; "Open posting" links go to each aggregator page.
5. Classifier audit: 30 random postings, look at `role_family` vs title. Known gaps: bare "Engineer" titles ("Senior Engineer (Platform)", "Data Engineer", "Machine Learning Engineer") classify as none by design.

## Ideas reported, not planned

- Cross-source duplicate postings (same job on RemoteOK and Himalayas) show
  twice on the Jobs page; application is already once per company, so it is
  cosmetic. Could dedupe by `company_id + normalized title` later.
- `not_verifiable` (242 companies with `catch_all`/`mx_only` contacts) is the
  largest remaining leak; relaxing it is a `verification-status` product
  decision, not a sourcing one.
- Role family as a client preference (checkboxes) instead of free-text titles.
- Scheduling runs (`interval_minutes` is stored only); Jobicy asks for at
  most hourly polling, Himalayas refreshes daily.
- `external_id` is `string(255)` and is not truncated: a source that falls back to a URL as id (Working Nomads, We Work Remotely, Himalayas guid) would fail its upsert chunk if a URL exceeded 255 chars. Real feeds do not do that today; a `text` external_id would remove the risk.
- `config('talent.collection.target_role_families')` is a hand-written list of the 10 `RoleFamily` values; adding an enum case later would silently exclude it from discovery and matching.
- `docs/features/contact-discovery/spec.md` still describes only the `<slug>.com` guess (D3 changed that); it is product truth and was not edited.
- Postings that were `role_family = null` and later become a target family (classifier tuning) are not re-dispatched to discovery, since only newly inserted postings are.
- Classifier: generic "Engineer" roles and data/ML titles are `null`; decide whether they belong in a family.
