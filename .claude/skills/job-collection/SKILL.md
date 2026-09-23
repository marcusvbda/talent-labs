---
name: job-collection
description: Domain invariants for sources, source adapters, collection runs ("levas"), source runs, job postings and "today's jobs". Load before touching any of them.
---

# Job collection — invariants

Cheat sheet. Product truth: Part B of `docs/features/job-collection-mvp/spec.md`.

## Vocabulary

- Owner's "leva" = **collection run** (`CollectionRun`, UI "Run").
  Label: `Run #{id} · {started_at app tz, "22 Sep 2026 22:50"}`.
- Posting model/table is `JobPosting` / `job_postings` — **never** `Job`/`jobs`
  (collides with the queue table).

## Adapters

- Contract: `App\Collection\Contracts\JobSourceAdapter::fetch(Source $source): iterable<JobPostingData>`.
- `JobPostingData`: readonly DTO — `externalId, title, companyName, location,
  isRemote, department, employmentType, url, applyUrl, descriptionHtml,
  descriptionText, publishedAt, raw`.
- `SourceAdapter` enum (`greenhouse`, `lever`, `ashby`, `remotive`,
  `remote_ok`, `arbeitnow`, `jobicy`) resolves class, label, and
  `requiresIdentifier()` (false for the aggregators `remotive`, `remote_ok`,
  `arbeitnow`, `jobicy`, whose `identifier` must be null; they read `settings`:
  Remotive `category/search/limit`, Jobicy `count/geo/industry/tag`,
  RemoteOK/Arbeitnow none).
- HTTP: `Http::timeout(20)->retry(2, 500)->acceptJson()->withUserAgent('talent-labs/0.1 (local)')`, throw on non-2xx.
- Direct field mapping only — no AI/NLP extraction. `raw` keeps the full item.
- `companyName` = payload value, else `sources.name`.
- Aggregators: label "via Remotive" / "via RemoteOK" / "via Arbeitnow" /
  "via Jobicy", keep original `url` (required attribution link-back), call
  sparingly.

## Dedup and ownership

- Unique key `(source_id, external_id)`.
- New posting: `collection_run_id = current run`, `first_seen_at = now`.
- Existing posting: update mutable fields + `last_seen_run_id`, `last_seen_at`.
  **Never** change `collection_run_id` or `first_seen_at`.
- "Today's jobs" = postings whose `collection_run.started_at` is today in the
  app timezone (`config('app.timezone')`).

## Runs

- One run at a time: starting fails if any run is `pending`/`running` or no
  source is active. Guard inside a lock/transaction (double-click safe).
- `CollectionRunStatus`: pending, running, completed, partial, failed.
  Final: all source runs completed → completed; some failed → partial; all
  failed → failed.
- `SourceRunStatus`: pending, running, completed, failed. One per active
  source; unique `(collection_run_id, source_id)`.
- Bus batch on queue `collection`, `allowFailures()`, `finally` → finalize
  (callbacks capture only the run id).
- `FetchJobsFromSource`: `$tries = 1`; catches exceptions → source run
  `failed` + truncated message, never rethrows; `failed()` also marks failure.
- Finalize is idempotent; sends a database notification to `triggered_by`
  with `isEventDispatched: true` and a link to the run view page.
- "Mark as failed" on pending/running runs: run + unfinished source runs →
  failed, cancel the batch.
- `sources.interval_minutes` is stored only; nothing is scheduled.

## Realtime channels (public, ids only)

| Channel               | Event                  | Emitted by                                     |
| --------------------- | ---------------------- | ---------------------------------------------- |
| `collection_runs`     | `CollectionRunUpdated` | `CollectionRun` saved/deleted                  |
| `collection_run_{id}` | `CollectionRunUpdated` | that run or any of its `SourceRun`s saved      |
| `job_postings`        | `JobPostingsUpdated`   | explicit, once per source run after the upsert |
| `sources`             | `SourceUpdated`        | `Source` saved/deleted                         |

Never once per posting. Never `->poll()`.
