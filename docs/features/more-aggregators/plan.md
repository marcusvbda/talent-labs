# Plan — More aggregator sources

Source spec: `docs/features/more-aggregators/spec.md` · SHA-256 `d7b1076a31972c355aa57fce0a77efd5e546ff166ff5eebb4c280fa7d623e31d`
Product truth: Part B of the spec above (it already lives in its feature
folder; the "copy Part B into `more-aggregator-sources/`" line of the prompt is
not followed — docs are never duplicated).
Run phases with `/execute-phases docs/features/more-aggregators/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                            | Role            | Depends on | Size | Status       |
| ----- | ------------------------------------------------ | --------------- | ---------- | ---- | ------------ |
| 1     | RemoteOK, Arbeitnow and Jobicy adapter classes   | laravel-backend | none       | M    | DONE         |
| 2     | Register the 3 adapters in `SourceAdapter`       | laravel-backend | 1          | S    | DONE         |
| 3     | Seed the 3 new sources                           | laravel-backend | 2          | S    | DONE         |
| 4     | Verification and report                          | orchestrator    | 3          | S    | IN_PROGRESS  |

## Audit — 2026-09-23

| Check | Result |
| ----- | ------ |
| Spec location | Already in `docs/features/more-aggregators/`; no copy made. |
| Adapter contract | `App\Collection\Contracts\JobSourceAdapter::fetch(Source $source): iterable` returning `JobPostingData`. |
| `JobPostingData` | `externalId string, title string, companyName string, location ?string, isRemote ?bool, department ?string, employmentType ?string, url string, applyUrl ?string, descriptionHtml ?string, descriptionText ?string, publishedAt ?CarbonImmutable, raw array`. |
| Shared HTTP trait | `App\Collection\Adapters\Concerns\InteractsWithJobBoardApi`: `http()`, `items()`, `stringOrNull()`, `boolOrNull()`, `parseDate()`, `htmlToText()`, `requireIdentifier()`. Everything the 3 adapters need exists. |
| Reference adapter | `RemotiveAdapter`: reads `jobs[]`, skips items missing id/title/company/url, private `query(Source)` builds params only from set keys, int param via `filter_var(..., FILTER_VALIDATE_INT, min_range 1)`. |
| `SourceAdapter` enum | 4 cases. `getLabel()`, `getColor()`, `adapter()` are exhaustive `match` (new cases **must** be added or they throw `UnhandledMatchError`). `requiresIdentifier()` is `$this !== self::Remotive` — **must change** or the new aggregators would require an identifier. `sourceLabel()` has a `default` arm — new cases need explicit arms. |
| Source-agnostic surfaces (B.4) | No other `Remotive`/`remotive` reference in `app/`, `resources/`, `config/`, `routes/`. `JobPostingsTable` adapter filter iterates `SourceAdapter::cases()`. Contact discovery (`app/Contacts/**`) never reads the adapter. |
| Where `sourceLabel()` shows | `/app` Today's jobs (`TodaysJobs.php:62,96`). Admin Job Postings shows `source.name` + adapter badge (`getLabel()`), same as Remotive today — unchanged. |
| Uniqueness | `sources` has the partial unique index `(adapter, identifier) WHERE identifier IS NOT NULL` (aggregators-only change) — new identifier-less rows never conflict. |
| Seeder | `SourceSeeder` has only Remotive, `firstOrCreate` on `adapter + identifier`. Stripe/Palantir/Linear were removed for good earlier — conflicts with spec B.2/B.3/AC03/AC04 (→ D1). |
| Current DB | 1 source (Remotive), 0 postings, 0 companies (owner ran `migrate:fresh --seed`). |
| Contact discovery | Built (`app/Contacts/**`, `companies`, `contacts`) → AC06 applies. |
| Settings form | `SourceForm` `KeyValue settings` shows for every adapter without identifier, helper text is Remotive-specific ("Keys: category, search, limit") → D2. |
| Commands | `vendor/bin/pint --dirty --format agent`, `composer types:check`, `composer lint:check`, `composer test`, `composer ci:check`, `php artisan test --compact`. |
| Known failure | `php artisan test` fails on 2 example tests: sqlite can't run the Postgres-only `change_notifications_data_to_json` migration. Pre-existing, not caused by this feature; `composer test`/`ci:check` will fail on it. |
| Dependencies | None needed. |
| Skill cheat sheet | `.claude/skills/job-collection/SKILL.md` says the enum has 4 cases and only `remotive` skips the identifier — goes stale in Phase 2. |

## Owner decisions

### D1 — Which seed/source world do B.2, B.3, AC03 and AC04 assume?

**Resolved 2026-09-23: A** (owner).

Blocks: Phase 3 (and the numbers checked in Phase 4) · Options:
**A (recommended)** Follow the current repo: Stripe/Palantir/Linear stay gone.
The seeder adds RemoteOK/Arbeitnow/Jobicy next to Remotive → `Source::count()`
goes 1 → 4 → 4; B.3 is moot; AC04 runs with all 4 aggregators. The spec text
(B.2, B.3, AC03, AC04, B.6 step 2–3) is updated in place to match, since you
asked for existing docs to be edited instead of adding new ones.
**B** Follow the spec literally: re-add Stripe/Palantir/Linear to the seeder
and aim for 7 sources.
· Why: the spec was written before the aggregators-only change, which removed
those three sources for good. Re-adding them reverses that; ignoring the spec
silently would break the "spec is product truth" rule.

### D2 — Make the settings helper text adapter-aware? (non-blocking)

**Resolved 2026-09-23: A** (owner) — built in Phase 2.

Blocks: nothing · Options: **A (recommended)** Yes — one line in
`SourceForm`: Remotive "Keys: category, search, limit", Jobicy "Keys: count,
geo, industry, tag", RemoteOK/Arbeitnow "No settings used"; added to Phase 2.
**B** Leave it; the Remotive keys show for every aggregator. · Why: B.7 rules
out new form fields but the current copy would mislead when editing a Jobicy
source. It's outside the spec, so it's only built if you say yes.

## Global constraints (every phase)

- Never run git writes; never commit. Never add/upgrade dependencies.
- Never write or modify tests (running existing ones is fine).
- Don't touch the `Greenhouse`, `Lever`, `Ashby`, `Remotive` cases/classes
  beyond adding the new arms to the enum's `match` blocks.
- Adapters use `InteractsWithJobBoardApi::http()` — no new HTTP client, no
  per-source retry tuning (B.7).
- Direct field mapping only; `raw` = the full item. Malformed items (missing a
  required field) are skipped, never thrown.
- B.4: zero changes to the collection flow, realtime, admin resources or
  contact discovery. If something turns out not to be source-agnostic, fix it
  as a bug and report it.
- No `->poll()` / `wire:poll`. English everywhere.
- Live API calls: at most one per adapter per verification step.

## Acceptance-criteria coverage

| AC   | Phases |
| ---- | ------ |
| AC01 | 2, 4   |
| AC02 | 1, 4   |
| AC03 | 3, 4   |
| AC04 | 4      |
| AC05 | 4      |
| AC06 | 4      |

## Phases

### Phase 1 — RemoteOK, Arbeitnow and Jobicy adapter classes

Status: DONE
Evidence: pint + `composer types:check` pass (0 errors); live fetch RemoteOK 99 / Arbeitnow 250 / Jobicy 50 items, first items fully mapped; RemoteOK title/company HTML-entity-decoded (payload sent `&amp;`), 0 left; code-reviewer APPROVED.
Role: laravel-backend · Depends on: none · Covers: AC02 · Size: M
Spec: B.1, Part 0.1

**Goal.** Three new classes in `app/Collection/Adapters/`, each
`implements JobSourceAdapter` and `use InteractsWithJobBoardApi`, shaped
exactly like `RemotiveAdapter`. Not wired into the enum yet (they are callable
directly), so the repo stays valid.

**Contract.**
- `RemoteOkAdapter::fetch(Source $source): iterable`
  - `$this->http()->get('https://remoteok.com/api')`; body is a top-level array
    → `$this->items($response->json())`.
  - Skip any item without `id` or `position` (this drops the leading
    legal/metadata element) and any item missing `id`, `position`, `company`
    or `url`.
  - externalId `stringOrNull(id)`, title `position`, companyName `company`,
    location `stringOrNull(location)` (empty → null), isRemote `true`,
    department `null`, employmentType `null`, url `url`,
    applyUrl `stringOrNull(apply_url) ?? url`, descriptionHtml `description`,
    descriptionText `htmlToText(descriptionHtml)`, publishedAt
    `parseDate(date)`, raw = item.
- `ArbeitnowAdapter::fetch(Source $source): iterable`
  - `$this->http()->get('https://www.arbeitnow.com/api/job-board-api')`, read
    `$response->json('data')` via `items()`. First page only.
  - Skip items missing `slug`, `title`, `company_name` or `url`.
  - externalId `slug`, title `title`, companyName `company_name`, location
    `stringOrNull(location)`, isRemote `boolOrNull(remote)`, department `null`,
    employmentType `stringOrNull(job_types[0] ?? null)`, url and applyUrl
    `url`, descriptionHtml `description`, descriptionText `htmlToText(...)`,
    publishedAt: `created_at` is Unix **seconds** →
    `CarbonImmutable::createFromTimestamp((int) $value)` only when it's an int
    or numeric string, else `null`; raw = item.
- `JobicyAdapter::fetch(Source $source): iterable`
  - `$this->http()->get('https://jobicy.com/api/v2/remote-jobs', $this->query($source))`,
    read `$response->json('jobs')` via `items()`.
  - Private `query(Source $source): array<string, string|int>` like
    Remotive's: `count` = `filter_var(settings.count, FILTER_VALIDATE_INT,
    min_range 1)`, defaulting to `50` when unset/invalid (always sent);
    `geo`, `industry`, `tag` sent only when `stringOrNull()` is non-null.
    Settings from the `KeyValue` field arrive as strings.
  - Skip items missing `id`, `jobTitle`, `companyName` or `url`.
  - externalId `stringOrNull(id)`, title `jobTitle`, companyName
    `companyName`, location `stringOrNull(jobGeo)`, isRemote `true`,
    department `stringOrNull(jobIndustry[0] ?? null)`, employmentType
    `stringOrNull(jobType[0] ?? null)`, url and applyUrl `url`,
    descriptionHtml `jobDescription`, descriptionText `htmlToText(...)`,
    publishedAt `parseDate(pubDate)`, raw = item. Guard the `[0]` reads with
    `is_array()`.

**Steps.**
1. Read `RemotiveAdapter` and `InteractsWithJobBoardApi`.
2. Write the three classes.
3. Live check in tinker, one call each, no DB write:
   `iterator_to_array((new App\Collection\Adapters\JobicyAdapter)->fetch(new App\Models\Source(['settings' => ['count' => 50]])))`
   (unsaved `Source`), and the same for RemoteOK and Arbeitnow. Report item
   count and the first item's externalId, title, companyName, location, url,
   applyUrl, publishedAt.

**Done when.**
- Each adapter returns > 0 items live with the mapped fields populated (AC02).
- `vendor/bin/pint --dirty --format agent` and `composer types:check` pass.

**Not in this phase.** Enum cases, seeder, collection runs.

### Phase 2 — Register the 3 adapters in `SourceAdapter`

Status: DONE
Evidence: pint + `composer types:check` pass (0 errors); tinker prints all 7 cases matching the contract (values, labels, `via …` labels, `requiresIdentifier()`, adapter classes); SKILL.md bullets and `SourceForm` settings helper (D2=A) updated; code-reviewer APPROVED. Create-form UI not clicked through (owner check).
Role: laravel-backend · Depends on: 1 · Covers: AC01 · Size: S
Spec: B.1, Part 0.1 (attribution)

**Goal.** The enum knows the three new adapters, so they can be selected in the
Sources form and resolved by the collection flow.

**Contract.** In `App\Enums\SourceAdapter`:
- New cases: `RemoteOk = 'remote_ok'`, `Arbeitnow = 'arbeitnow'`,
  `Jobicy = 'jobicy'` (after `Remotive`).
- `getLabel()`: `RemoteOK`, `Arbeitnow`, `Jobicy`.
- `getColor()`: `gray` for all three (same as Remotive, the aggregator colour).
- `requiresIdentifier()`: `false` for Remotive, RemoteOk, Arbeitnow, Jobicy;
  `true` for Greenhouse, Lever, Ashby (use an exhaustive `match`, no `default`).
- `sourceLabel()`: `via RemoteOK`, `via Arbeitnow`, `via Jobicy` (explicit
  arms; keep Remotive's and the `default` arm).
- `adapter()`: `RemoteOkAdapter::class`, `ArbeitnowAdapter::class`,
  `JobicyAdapter::class`.
- The 4 existing cases' values/labels/colours are unchanged.
- Update `.claude/skills/job-collection/SKILL.md` "Adapters" bullets so the
  enum list and "`requiresIdentifier()` false for" list include the new cases
  (harness cheat sheet must match the code).
- `SourceForm` `KeyValue settings` helper text becomes adapter-aware
  (D2 = A): Remotive `Keys: category, search, limit`, Jobicy
  `Keys: count, geo, industry, tag`, RemoteOK/Arbeitnow `No settings used`.

**Steps.**
1. Edit the enum; update the skill bullets.
2. Tinker: for each case print `value`, `getLabel()`, `sourceLabel()`,
   `requiresIdentifier()`, `get_class(adapter())`.

**Done when.**
- Tinker output matches the contract for all 7 cases (AC01; also confirms
  Greenhouse/Lever/Ashby still resolve).
- The Sources create form lists RemoteOK/Arbeitnow/Jobicy, hides the
  identifier and shows settings for them.
- `vendor/bin/pint --dirty --format agent` and `composer types:check` pass.

**Not in this phase.** Seeder rows.

### Phase 3 — Seed the 3 new sources

Status: DONE
Evidence: `db:seed --class=SourceSeeder` twice → `Source::count()` 1 → 4 → 4 (Remotive, RemoteOK, Arbeitnow, Jobicy); pint + `composer types:check` pass; spec B.2/B.3/AC03/AC04/B.6 updated to the 4-source world, SHA re-recorded.
Role: laravel-backend · Depends on: 2 · Covers: AC03 · Size: S
Spec: B.2, B.3

**Goal.** `db:seed` creates the three aggregator sources idempotently.

**Contract (D1 = A).**
- `SourceSeeder` `$sources` gets, after Remotive:
  - `RemoteOK`, `SourceAdapter::RemoteOk`, identifier `null`, settings `null`
  - `Arbeitnow`, `SourceAdapter::Arbeitnow`, identifier `null`, settings `null`
  - `Jobicy`, `SourceAdapter::Jobicy`, identifier `null`, settings `['count' => 50]`
  - all `is_active => true`, same `firstOrCreate` on `adapter + identifier`.
- Update the spec text in place to the 4-source world: B.2 (no Stripe/
  Palantir/Linear rows; `firstOrCreate`), B.3 (removed — no fixed-company
  sources exist), AC03 (`1 → 4 → 4`), AC04 ("all 4 sources"), B.6 steps 2–3.
  Re-record the spec SHA-256 in this plan's header.

**Steps.**
1. Report `Source::count()`.
2. Edit the seeder; run `php artisan db:seed --class=SourceSeeder` twice,
   reporting the count after each.

**Done when.**
- Count goes 1 → 4 → 4 and existing rows are untouched (AC03).
- `vendor/bin/pint --dirty --format agent` and `composer types:check` pass.

**Not in this phase.** Running a collection.

### Phase 4 — Verification and report

Status: IN_PROGRESS
Role: orchestrator (qa-tester for checks) · Depends on: 3 · Covers: AC01–AC06 · Size: S
Spec: B.5, B.6, Part 0.1

**Goal.** Prove the feature end to end and hand the owner a checklist.

**Steps.**
1. Gate: `composer lint:check`, `composer types:check`, `yarn check`,
   `php artisan test --compact` (report the known sqlite failure separately
   from anything new).
2. Grep: no `->poll(`/`wire:poll`; no new HTTP client in the adapters
   (`Http::` only inside the trait); no change under `app/Contacts`,
   `app/Filament` (except the D2 helper text in `SourceForm`), collection jobs/actions (B.4).
3. Record `Company::count()` and `JobPosting::count()` before.
4. Trigger one "Collect jobs now" run with all sources active (needs
   `composer dev`: queue + Reverb). Report per-source status, postings
   fetched, total `jobs_new`, and `Company::count()` after (AC04, AC05).
5. AC06: `JobPosting::whereHas('source', fn ($q) => $q->whereIn('adapter', ['remote_ok','arbeitnow','jobicy']))`
   — report how many have `company_id` set and how many of those companies
   have a domain/contacts after the queue drains.
6. AC04 labels/links: in `/app` Today's jobs, the three sources show
   `via RemoteOK` / `via Arbeitnow` / `via Jobicy`; `url` for those postings
   points at remoteok.com / arbeitnow.com / jobicy.com; for RemoteOK
   `apply_url` is set.
7. Walk AC01–AC06 with evidence. Owner checklist: open a few "Open posting"
   links per new source; create a second Jobicy source with `geo` settings;
   note the attribution requirement (Part 0.1) is met by the labels + links.

**Done when.**
- Every AC has evidence or a named blocker; report delivered; nothing
  committed.
