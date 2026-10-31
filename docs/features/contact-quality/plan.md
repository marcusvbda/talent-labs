# Plan — Contact Quality

Spec: `docs/features/contact-quality/spec.md` · SHA-256 `84ee1e67cd4f8f779c553adfa6b81748c1f307fd5c6040b142c89e2f14f500c2`
Run phases with `/exec-phase contact-quality <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — 2026-10-31

Implemented: 1 · Partial: 2 · Divergent: 0 · Missing: 17 · Unverifiable: 0
Out of scope by owner decision: P17. The local database is empty, so published-email coverage is unmeasured.

| Requirement | State | Evidence |
| ----------- | ----- | -------- |
| P1 Shared extractor, every source | Partial | Only `app/Collection/Adapters/HackerNewsAdapter.php` (`emails()`) |
| P2 Discard rules | Partial | Only `NON_CONTACT_EMAIL_DOMAINS` in the same adapter |
| P2a Domain match | Missing | — |
| P3 Tiers | Missing | `app/Enums/ContactConfidence.php` only |
| P4 Contact columns | Missing | `2026_09_23_130001_create_contacts_table.php` |
| P5 Probe published emails | Missing | `SmtpProbe` reusable; verdict logic private in `DiscoverCompanyContacts` |
| P6 Storage, AI and pool gates | Missing | `FetchJobsFromSource`, `StoreCompanyPostings`, `ExtractJobPostingProfileJob`, `MatchingJobPostings` |
| P7 Recipient order | Missing | `SelectRecipientForCompany` |
| P7a Daily cap per tier-A address | Missing | — |
| P8 Send checks | Missing | `SendApplicationEmail::preCheck()` |
| P9 Tier snapshot | Missing | `2026_09_24_100005_create_applications_table.php` |
| P10 Ordering | Missing | `QueueRandomApplications` (`inRandomOrder`), `JobPoolQuery` |
| P11 Admin source and tier | Missing | `ContactsRelationManager` |
| P12 Replies and report | Missing | `ApplicationStatus`, no `replied_at` |
| P13 Backfill | Missing | — |
| P14 Coverage measurement | Missing | — |
| P15 Named contacts in production | Missing | Gated by P14 |
| P16 Never scrape LinkedIn/search engines | Implemented | No such code; kept as a constraint |
| P18 Privacy and suppression on every tier | Missing | Table does not exist |
| P18a Suppression table and checks here | Missing | Defined in `landing-and-legal` P13, not built |

## Status board

| Phase | Title | Role | Depends on | Origin | Size | Status |
| ----- | ----- | ---- | ---------- | ------ | ---- | ------ |
| 1 | Shared published-email extractor | laravel-backend | none | P1, P2, P2a | M | DONE |
| 2 | Contact schema, sources and derived tiers | laravel-backend | none | P3, P4 | M | DONE |
| 3 | Alias paths ignore posting contacts | laravel-backend | 2 | P4 | S | DONE |
| 4 | Probe and record published emails | laravel-backend | 1, 3 | P5 | M | DONE |
| 5 | Storage and AI gates accept tier A | laravel-backend | 4 | P6 | M | DONE |
| 6 | Pool, recipient, cap, send checks, snapshot | laravel-backend | 5 | P6, P7, P7a, P8, P9 | M | DONE |
| 7 | Tier ordering | laravel-backend | 6 | P10 | S | DONE |
| 8 | Admin shows source and tier | filament-admin | 6 | P11 | S | DONE |
| 9 | Backfill command | laravel-backend | 4 | P13 | S | DONE |
| 10 | Suppression table and probe skip | laravel-backend | 4 | P18, P18a | M | DONE |
| 11 | Suppression on queueing and sending | laravel-backend | 6, 10 | P18, P18a | M | DONE |
| 12 | Reply endpoint and tier report | laravel-backend | 6 | P12 | M | PENDING |
| 13 | "I got a reply" client action | inertia-frontend | 12 | P12 | S | PENDING |
| 14 | Guarded company page fetcher | laravel-backend | 10 | P14, P16 | M | PENDING |
| 15 | Named-contact coverage report (stops for the owner) | laravel-backend | 14 | P14, P16, P18 | M | PENDING |
| 16 | Named contacts in production | laravel-backend | 15 | P15, P18 | M | BLOCKED (D15, D16) |
| 17 | Verification and report | laravel-backend | 1-15 (16 if unblocked) | spec x code gap | S | PENDING |

## Owner decisions

Spec decisions D1-D14 are already recorded in `spec.md` and embedded in the phases below.

### D15 — Does Stage 2 go to production after the P14 report?

Blocks: Phase 16 · Options: A build it, B stop at the measurement · Why: P15 exists only if the report justifies it; the owner decides on seeing the numbers · Answer: pending (after Phase 15).

### D16 — What is the retention rule for named contacts?

Blocks: Phase 16 · Options: to be decided with the privacy policy (`landing-and-legal` P11) · Why: named contacts are personal data (P18) · Answer: pending.

## Global constraints (every phase)

- Never run git writes; `/exec-phase` makes the commits. Never `migrate:fresh/refresh/reset/rollback`, `db:wipe`, DROP or TRUNCATE of data. New forward migrations only (D14); never edit an existing migration. Running `php artisan migrate` is allowed.
- Never add, remove or upgrade packages. Never write or modify tests. If an existing test fails because of intended behaviour, stop and report it.
- Everything in English in the repo, except PT values in `lang/pt.json`. No Spanish copy.
- Clients never see a recipient, a contact, a tier or a source (D10). `JobCardResource`, `ApplicationItemResource`, `ApplicationDetailResource` and `ReviewDraftPresenter` stay free of them.
- Realtime, never `->poll()` or `wire:poll`. Query-builder writes need an explicit `RealtimeEvent::dispatch`, and public channels carry ids only.
- Every SMTP connection goes through `SmtpProbe`; it never transmits a message. Every company website fetch goes through `PublicNetworkGuard`.
- Never: LinkedIn scraping, search-engine scraping, paid search, sending to an unverified guess (P16). No paid finder API (D12).
- Dedup rules stay: `(source_id, external_id)`, and an existing posting never changes `collection_run_id`, `first_seen_at` or `company_id`.
- Tiers B and C need `smtp_verified` and a company with `outreach_status = verified`. Only tier A may be `catch_all` or `inconclusive`, and only `rejected` excludes it.
- Queue job `$timeout` stays below the database queue's `retry_after` (90).
- Checks per phase: `vendor/bin/pint --dirty --format agent`, `composer types:check`, and the focused tests named in the phase. Frontend phases also run `yarn check`.

## Requirement coverage

| Requirement | Phases |
| ----------- | ------ |
| P1, P2, P2a | 1 |
| P3, P4 | 2, 3 |
| P5 | 4 |
| P6 | 5, 6 |
| P7, P7a, P8, P9 | 6 |
| P10 | 7 |
| P11 | 8 |
| P12 | 12, 13 |
| P13 | 9 |
| P14 | 14, 15 |
| P15 | 16 |
| P16 | Global constraint, 14, 17 |
| P17 | Out of scope (D12) |
| P18, P18a | 10, 11, 15, 16 |

## Phases

### Phase 1 — Every source extracts published emails

Status: DONE
Evidence: tinker example returns `['jobs@acme.io', 'hiring@acme.io']`; `pint --dirty`, `composer types:check` and the existing test suite pass; `code-reviewer` APPROVED. Deviations: mailto matched with single or double quotes and `rawurldecode`d; `JSON_THROW_ON_ERROR` on the `source_contacts` encode.
Role: laravel-backend · Depends on: none · Covers: P1, P2, P2a · Size: M
Origin: P1 "Extract emails published in the posting for every source"; P2 discard rules; P2a domain match

**Goal.** One extractor fills `source_contacts` with kind `email` for every source.

**Gap -> desired.** Only `HackerNewsAdapter::emails()` extracts, with private constants -> a shared class used by that adapter and by `FetchJobsFromSource::row()`.

**Contract.**

- New `app/Collection/Support/PublishedEmailExtractor.php` (final class, static methods):
    - `extract(?string $html, ?string $text, ?string $applyUrl): array` returns `list<string>`, order: `mailto:` in `$applyUrl`, `mailto:` hrefs in `$html` (without `?query`, entities decoded), then addresses in the text. The text is `$text`, or `$html` with tags stripped and entities decoded when `$text` is null. Bracketed obfuscation only (`[at]`, `(at)`, `{at}`, `[dot]`, `(dot)`, `{dot}`); bare " at " is never rewritten. Same `EMAIL_PATTERN` as the adapter. Lowercased, trimmed of `.,;:)`, validated with `FILTER_VALIDATE_EMAIL`, unique, at most `MAX_EMAILS = 5`.
    - `matchesCompany(string $email, Company $company): bool`: true when `$company->domain` is empty or `domain_status` is not `found`; true when the email's domain is in `talent.outreach.free_mail_domains`; otherwise `RegistrableDomain::of(emailDomain) === $company->domain`. Compare with the company domain lowercased.
    - `emailsOf(?array $sourceContacts): array` returns `list<string>` of the entries with kind `email`.
- Discards: local part in `noreply`, `no-reply`, `donotreply`, `do-not-reply`, or in `talent.outreach.non_application_local_parts`; domain in `NON_CONTACT_EMAIL_DOMAINS` (`news.ycombinator.com`, `ycombinator.com`, moved here as a public const); domain equal to or a subdomain of an entry in `talent.outreach.aggregator_domains` (supports `name.*` like `AggregatorUrl::is`, which may be called with `https://{domain}`); domain equal to or a subdomain of an entry in `talent.outreach.ats_domains`; TLD in `png, jpg, jpeg, gif, svg, webp, css, js` (asset names like `logo@2x.png`). Free-mail is accepted (D4).
- `config/talent.php`, under `outreach`:
    - `ats_domains`: `greenhouse.io`, `lever.co`, `ashbyhq.com`, `workable.com`, `recruitee.com`, `teamtailor.com`, `smartrecruiters.com`, `bamboohr.com`, `breezy.hr`, `personio.de`, `personio.com`, `join.com`, `myworkdayjobs.com`, `jobvite.com`, `trinethire.com`, `wellfound.com`, `angel.co`, `applytojob.com`, `rippling.com`, `workatastartup.com`.
    - `non_application_local_parts`: `privacy`, `legal`, `dpo`, `gdpr`, `compliance`, `security`, `abuse`, `press`, `media`, `support`, `help`, `billing`, `accounts`, `sales`, `marketing`, `accommodations`, `accessibility`. `info` and `contact` stay allowed.
    - `free_mail_domains`: `gmail.com`, `googlemail.com`, `outlook.com`, `hotmail.com`, `live.com`, `yahoo.com`, `icloud.com`, `me.com`, `proton.me`, `protonmail.com`, `gmx.com`, `gmx.de`, `aol.com`, `zoho.com`, `uol.com.br`, `bol.com.br`, `terra.com.br`.
- `FetchJobsFromSource::row()`: `source_contacts` = the adapter's `sourceContacts` plus one `SourceContactData(SourceContactKind::Email, email: $e)` per extracted email from `descriptionHtml`, `descriptionText`, `applyUrl` that the adapter did not already return (compare lowercased). Founders and hiring managers from YC stay untouched. Still `null` when empty, so the upsert's `coalesce` keeps stored contacts.
- `HackerNewsAdapter::emails()` delegates to `PublishedEmailExtractor::extract($html, $this->htmlToText($html), null)`; remove `EMAIL_PATTERN`, `MAX_EMAILS`, `NON_CONTACT_EMAIL_DOMAINS` from the adapter if nothing else uses them. `Str` import removed if unused. HN gets the new discards too. `NON_COMPANY_HOSTS` is unrelated and stays.
- Assumption: extraction happens in `row()` rather than in every adapter, so a future adapter is covered automatically.

**Steps.**

1. Add the config lists.
2. Create the extractor from the adapter's logic.
3. Delegate from the adapter and merge in `row()`.

**Done when.**

- In `php artisan tinker`, `PublishedEmailExtractor::extract('<a href="mailto:Jobs@Acme.io?subject=x">x</a>', 'hiring [at] acme [dot] io, noreply@acme.io, privacy@acme.io, x@boards.greenhouse.io, logo@2x.png', null)` returns `['jobs@acme.io', 'hiring@acme.io']`.
- `vendor/bin/pint --dirty --format agent` and `composer types:check` pass; `php artisan test --compact --filter=HackerNews` passes (or matches no tests).

**Not in this phase.** Contacts rows, probing, gates.

### Phase 2 — Contacts carry a source and a derived tier

Status: DONE
Evidence: `php artisan migrate` ran; columns and the four partial unique indexes verified in `pg_indexes`; `reports:funnel` runs with the new rows; `pint --dirty`, `composer types:check` and the existing suite pass; `code-reviewer` APPROVED. Note: `down()` deletes non-`alias_probe` rows before restoring the old unique index (never run). Later phases must add source filters where `smtp_verified` is read without one (`InventoryFunnel` confidence counts, `MatchingJobPostings`, `SendApplicationEmail`, `BackfillOutreachStatus`).
Role: laravel-backend · Depends on: none · Covers: P3, P4 · Size: M
Origin: P3 "Contact tiers, per contact"; P4 "Data (forward migrations only)"

**Goal.** The schema and model can represent posting and named contacts, with the tier derived.

**Gap -> desired.** `contacts` holds aliases only, unique on `(company_id, local_part)` -> `source`, `job_posting_id`, `person_name`, `person_title`, with partial unique indexes per source.

**Contract.**

- New migration `add_source_columns_to_contacts_table` (new timestamp, after the existing ones):
    - `source` string, not null, default `alias_probe`; `job_posting_id` nullable FK to `job_postings`, `cascadeOnDelete`; `person_name` nullable string; `person_title` nullable string.
    - Drop the unique `(company_id, local_part)`. Add through `DB::statement` (PostgreSQL 17):
        - `contacts_alias_unique`: unique `(company_id, local_part)` where `source = 'alias_probe'`
        - `contacts_named_unique`: unique `(company_id, email)` where `source = 'named'`
        - `contacts_posting_unique`: unique `(job_posting_id, email)` where `source = 'posting' and job_posting_id is not null`
        - `contacts_posting_verdict_unique`: unique `(company_id, email)` where `source = 'posting' and job_posting_id is null`
    - Index `job_posting_id`. Write a working `down()`, but never run it.
- Why one row per posting and email: multi-role HN posts share one email across `{id}#n` postings, and a single `job_posting_id` per email could not represent that. A row with `job_posting_id = null` holds only a `rejected` verdict for a posting that was never stored.
- New `app/Enums/ContactSource.php`: `AliasProbe = 'alias_probe'`, `Posting = 'posting'`, `Named = 'named'`, with `HasLabel` (`Alias probe`, `Published in posting`, `Named`).
- New `app/Enums/ContactTier.php`: `A = 'a'`, `B = 'b'`, `C = 'c'`, with `HasLabel` (`A — Published`, `B — Named`, `C — Role alias`) and `HasColor` (`success`, `info`, `gray`).
- `ContactConfidence`: add `Inconclusive = 'inconclusive'` (label `Inconclusive`, color `gray`) and `Rejected = 'rejected'` (label `Rejected`, color `danger`).
- `app/Models/Contact.php`: fillable and `@property` docs for the new columns; cast `source`; `jobPosting()` BelongsTo; `tier(): ?ContactTier`:
    - `Posting` and `job_posting_id` not null and confidence not `Rejected` -> A;
    - `Named` and `SmtpVerified` -> B;
    - `AliasProbe` and `SmtpVerified` -> C;
    - otherwise null.
- `app/Models/JobPosting.php`: `contacts()` HasMany; `hasSendableTierA(): bool` (a contact with source `posting` and confidence not `rejected`).
- `app/Reports/InventoryFunnel.php`: add `inconclusive` and `rejected` to `$contacts` and to the `GlobalStock` phpstan type. `FunnelReport` indexes `$contacts[$confidence->value]` for every enum case and would fail otherwise. `mx_only_share` must divide by the alias-only total (exclude the two new keys) so that `port25_warning` keeps its meaning.
- Assumption: `tier` is derived (spec P4), never stored.

**Steps.**

1. Write the migration and run `php artisan migrate`.
2. Add the enums, then update the models and the funnel.

**Done when.**

- `php artisan migrate` runs, and Boost `database-schema` shows the columns and four partial indexes.
- `php artisan reports:funnel` runs without error.
- `composer types:check` passes; `php artisan test --compact --filter=SendingDatabaseGuards` passes.

**Not in this phase.** Writing posting contacts.

### Phase 3 — Alias discovery ignores posting contacts

Status: DONE
Evidence: tinker (in a rolled-back transaction) showed a `posting` `careers` contact coexisting with the `alias_probe` row of the same company, the alias `updateOrCreate` leaving the posting row untouched, and `SelectRecipientForCompany` ignoring the posting row; `pint --dirty`, `composer types:check` and the existing suite pass (`--filter=Contact` matches no tests); `code-reviewer` APPROVED, verdict logic identical branch by branch. Left for later: `MatchingJobPostings` contacts `whereExists` has no source filter (rewritten in Phase 6); the Companies table Retry modal says "Existing contacts are removed." though only alias contacts are now.
Role: laravel-backend · Depends on: 2 · Covers: P4 · Size: S
Origin: P4 (new contact source must not break alias behaviour)

**Goal.** A published `careers@` never overwrites, or is mistaken for, the probed alias.

**Gap -> desired.** Alias code assumes every contact is an alias -> it filters by `source = alias_probe`, and the verdict logic is shared.

**Contract.**

- New `app/Contacts/Support/SmtpVerdict.php`, moving the logic out of `DiscoverCompanyContacts`:
    - `isAccepted(?int $code): bool`: 2xx.
    - `rejectsMailbox(?int $code, ?string $text): ?bool`: `true` for a 5xx unknown-mailbox answer, `null` when inconclusive. Same rules as `catchAllVerdict()` today: null code, a code below 500 or at 600 and above -> null; text `^5\.7\.\d+` -> null; text `^5\.(?:1\.\d+|4\.1)\b` -> true; `BLOCK_PATTERN` match -> null; otherwise true.
- `DiscoverCompanyContacts`: `catchAllVerdict()` uses `SmtpVerdict` with identical behaviour; `updateOrCreate` key becomes `['local_part' => $alias, 'source' => ContactSource::AliasProbe]`.
- `RetryCompanyDiscovery`: delete only `source = alias_probe` contacts, so published and named contacts survive a retry.
- `SelectRecipientForCompany`: add `->where('source', ContactSource::AliasProbe)`.
- `BackfillOutreachStatus`: `has_verified_contact` also filters `source = alias_probe`.

**Steps.**

1. Extract `SmtpVerdict` without behaviour change.
2. Apply the source filters.

**Done when.**

- A contact with source `posting`, local part `careers`, can coexist with the alias row of the same company (checked in tinker inside `DB::beginTransaction()` and `rollBack()`).
- `composer types:check` passes; `php artisan test --compact --filter=Contact` passes (or matches no tests).

**Not in this phase.** Probing published emails.

### Phase 4 — Published emails are probed and recorded

Status: DONE
Evidence: with a fake `SmtpProbe` and `DnsLookup` bound in the container (rolled-back transaction, no network) every D6 code mapping gave the expected verdict, a second call reused the stored verdict without probing, an eligible posting got its tier-A contact and a posting with `ineligible_reason` got none, and `dispatchForPostings` skipped ineligible and non-target postings and queued nothing once a posting contact existed; `pint --dirty`, `composer types:check` and the existing suite pass (`--filter=Collection` matches no tests); `code-reviewer` APPROVED. Notes for Phase 5: the dispatch for known postings does not require a verified company (within the contract; Phase 5 may gate it); known postings whose emails are all rejected are re-dispatched each run (cheap, unique lock 1h, verdicts reused for the TTL); emails skipped by the 25s budget are stored `Inconclusive` and reused for 30 days.
Role: laravel-backend · Depends on: 1, 3 · Covers: P5 · Size: M
Origin: P5 "A published email is probed with the existing SmtpProbe when possible"

**Goal.** Postings already stored get tier-A contacts with a verdict.

**Gap -> desired.** `source_contacts` emails are unused -> known postings and postings of verified companies get `contacts` rows.

**Contract.**

- New `app/Contacts/Actions/VerifyPublishedEmails.php`, `handle(Company $company, array $emails): array` returning `array<string, ContactConfidence>`. Constants: `VERDICT_TTL_DAYS = 30`, `SESSION_START_BUDGET_SECONDS = 25`.
    - Reuse: a contact of that company with that email, source `posting`, `checked_at` within the TTL -> its confidence, no probe. This prevents re-probing a `rejected` email on every run.
    - The rest, grouped by email domain, one session per domain: `host = DnsLookup::mxHosts($domain)[0] ?? $domain`; `SmtpProbe::probe($host, ['nonexistent-'.random.'@'.$domain, ...$emails])`. No new session once the budget has elapsed; the unprobed emails are `Inconclusive`.
    - Mapping (D6): session failed or null code -> `Inconclusive`; 2xx and random address rejected -> `SmtpVerified`; 2xx and random address accepted -> `CatchAll`; 2xx and random address inconclusive -> `Inconclusive`; `SmtpVerdict::rejectsMailbox` true -> `Rejected`; otherwise `Inconclusive`.
- New `app/Contacts/Actions/RecordPublishedContacts.php`, `handle(Company $company, ?JobPosting $posting, array $verdicts): void`. Each verdict is an `updateOrCreate` with `source = posting`, `local_part = Str::before($email, '@')`, `confidence`, `checked_at = now()` (fresh verdicts only; a reused one keeps its date). Key `[job_posting_id, email]` for a sendable verdict with a posting. Key `[company_id, email, job_posting_id = null]` when the verdict is `Rejected` or the posting is null.
- New `app/Contacts/Jobs/SyncPublishedContacts.php`: `__construct(public int $jobPostingId)`, queue `contacts`, `ShouldBeUnique` (`uniqueFor` 3600, `uniqueId` = posting id), `$tries = 1`, `$timeout = 75`. It catches `Throwable` and logs a warning like `DiscoverContactsForPosting`. Steps: load the posting; return when `company_id` is null; `emailsOf` filtered by `matchesCompany`; verify; record; then `RealtimeEvent::dispatch('companies', 'CompanyUpdated', ['id' => $company->id])` when anything was written, guarded for `BroadcastException` as elsewhere.
- Wiring (all after the existing saves, not inside the failure-marking `try`):
    - `FetchJobsFromSource`: known postings (the `existingRows` path) and postings stored for verified companies: when the posting has emails, a company, `ineligible_reason` null, a target `role_family` and no `contacts` row with source `posting`, dispatch `SyncPublishedContacts`. Query per chunk, never per row.
    - `StoreCompanyPostings::store()`: same for its newly stored ids that have emails.
- Assumption: a stored verdict is not re-probed before the TTL ends and a sendable row is not re-probed by this job while it exists. A bounce at send time doesn't change the contact (not in the spec, reported in Phase 17).

**Steps.**

1. Create the verifier and the recorder.
2. Create the job and wire the dispatches.

**Done when.**

- `php artisan tinker` with a fake `SmtpProbe` bound in the container shows each code mapping above, and a second call reuses the stored verdict without probing.
- No `contacts` row is written for a posting with `ineligible_reason` set.
- `composer types:check` passes; `php artisan test --compact --filter=Collection` passes (or matches no tests).

**Not in this phase.** Storing postings of unverified companies; using these contacts to send.

### Phase 5 — A sendable published email opens the storage and AI gates

Status: DONE
Evidence: with a fake `SmtpProbe` and `DnsLookup` (rolled-back transaction, no network, no AI provider), a new posting with a published email at an unverified company was stored with a tier-A contact and a queued profile job; the posting whose only email was `Rejected` was not stored and a verdict row with null `job_posting_id` exists; a posting without emails was not stored; `SyncPublishedContacts` queued the profile job for tier A and not for a rejected-only email; the profile job gate passed with a tier-A contact and returned early without; `pint --dirty`, `composer types:check` and the existing suite pass (33 tests; `--filter=Collection` matches no tests). `code-reviewer` found one race in round 1 (the profile job queued by `StoreNewPostings` could run before the contact existed and return at the gate); fixed by re-queuing it from `StorePublishedPostings` after recording, re-review APPROVED. Notes: `SyncPublishedContacts::dispatchForPostings` stays in `StoreCompanyPostings` (verified path) and receives every returned id, not only new ones (harmless: skips postings with a contact, job is unique); `StorePublishedPostings` does not re-check whether the company became verified while it waited on the lock; a very narrow window remains where a profile job overlapping the contact insert holds the unique lock and the re-queue is dropped.
Role: laravel-backend · Depends on: 4 · Covers: P6 · Size: M
Origin: P6 "Storage and AI gates accept a posting when its company is verified for sending or the posting has at least one sendable tier-A email"

**Goal.** A new posting with a sendable tier-A email is stored and profiled even if its company is not verified.

**Gap -> desired.** New postings of unverified companies are dropped -> those with a sendable email are kept; the rest behave as today.

**Contract.**

- New `app/Actions/Collection/StoreNewPostings.php`, `handle(Company $company, SourceRun $sourceRun, array $rows): array`, moved from `StoreCompanyPostings::store()` (upsert with `company_id`, dispatch `ExtractJobPostingProfileJob` for new ids, increment `jobs_new` under lock, `JobPostingsUpdated` event). Returns `array<string, int>`, posting id by external id, for the rows of this call.
- New `app/Jobs/StorePublishedPostings.php`: `Batchable, Queueable`; `__construct(int $sourceRunId, int $companyId, array $rows)`; `$tries = 6`, `$timeout = 75`, `$failOnTimeout = true`; middleware `WithoutOverlapping((string) $companyId)->releaseAfter(15)->expireAfter(120)->shared()`. It catches `Throwable` and logs like `StoreCompanyPostings`. A separate job because a discovery probe plus a published-email probe can each use up to 45s, which doesn't fit `StoreCompanyPostings`' 75s.
    - Steps: return if the batch is cancelled. Keep rows with `ineligible_reason` null. Decode emails, filter with `matchesCompany`. Run `VerifyPublishedEmails` once over the unique emails. A row is stored when at least one of its emails is not `Rejected`. `StoreNewPostings` stores those rows. `RecordPublishedContacts` runs per stored posting, with `$posting = null` for `Rejected` verdicts of unstored rows.
- `StoreCompanyPostings`: add `->shared()` to its middleware; when the company is still not verified after discovery, send rows having emails to `StorePublishedPostings` (`$this->batch()?->add([...])`, else `dispatch()->onQueue('collection')`); `store()` calls `StoreNewPostings`.
- `FetchJobsFromSource`: at the `isClosedForSending() || isCoolingDown()` branch, rows with emails go to `$deferred` as `StorePublishedPostings` instead of being dropped; others are dropped as today. Before `new StoreCompanyPostings`, nothing changes.
- `ExtractJobPostingProfileJob::handle`: return early only when the company is not verified **and** `! $posting->hasSendableTierA()`.
- `DiscoverContactsForPosting`: the `elseif` also accepts `hasSendableTierA()`.
- `SyncPublishedContacts`: after recording, dispatch `ExtractJobPostingProfileJob` when the posting now has a sendable tier-A email and `needingProfile`.
- `ExtractPostingProfiles` command: its base query accepts verified companies or postings with a sendable tier-A contact.
- Assumption: with a cooling-down company whose domain is still unknown, any address passing P2 counts (P2a); a later domain doesn't remove the contact.
- These postings are not visible to clients until Phase 6.

**Steps.**

1. Extract `StoreNewPostings` with no behaviour change.
2. Add the job and the routing.
3. Update the AI gates.

**Done when.**

- With a fake probe, a new posting with a published email at a `not_verifiable` company is stored with a tier-A contact and a queued profile job. The same posting whose only email is `Rejected` isn't stored, and a verdict row with null `job_posting_id` exists.
- A posting without emails at an unverified company is still dropped; verified companies behave as before.
- `composer types:check` passes; `php artisan test --compact --filter=Collection` passes (or matches no tests).

**Not in this phase.** Client pool and sending.

### Phase 6 — Clients can apply through tier A

Status: DONE
Evidence: migration ran; in a rolled-back transaction against the dev database, a posting at a `not_verifiable` company with a catch-all tier-A contact entered `MatchingJobPostings::forUser`, `QueueApplication` wrote `recipient_tier = a` and `recipient_source = posting`, and `preCheck` passed `CheckingCompany` and `ConfirmingRecipient` (it then stopped at `checking_gmail`, because the fake integration token cannot refresh); a posting with only another posting's tier-A contact, or only a `Rejected` one, was not in the pool and never selected; with 5 applications today to the address, an unverified company got `REJECT_NO_RECIPIENT` and a verified one fell back to the tier-C alias; `pint --dirty`, `composer types:check` (0 errors) and `--filter=Outreach` (31 tests) pass. `code-reviewer` APPROVED in round 1 with no blocking findings. Notes: the cap check isn't locked across clients (as the contract says); a verified company with only a `named` contact now enters the pool, and the alias branch of the pool filter now requires `source = alias_probe`; a capped tier-A posting at an unverified company stays in the pool and is rejected at queue time (Phase 7 adds `sendableToday` for random sending); emails are lowercased at extraction, so the exact-match cap query is safe.
Role: laravel-backend · Depends on: 5 · Covers: P6, P7, P7a, P8, P9 · Size: M
Origin: P6 (pool); P7 recipient order; P7a daily cap; P8 send checks; P9 snapshot

**Goal.** Tier-A postings reach the pool and are sent to the published address, with a snapshot and a cap.

**Gap -> desired.** Pool, recipient and send checks require a verified company and alias -> A then B then C per posting.

**Contract.**

- New migration `add_recipient_snapshot_to_applications_table`: nullable strings `recipient_tier` (length 1) and `recipient_source` (length 32); index `(recipient_email, queued_at)`. Old rows stay null.
- `app/Models/Application.php`: fillable and `@property`. `recipientKind()` stays untouched.
- `config/talent.php`, `outreach`: `'tier_a_daily_cap' => (int) env('OUTREACH_TIER_A_DAILY_CAP', 5)`. Add the key to `.env.example` only.
- New `app/Outreach/Actions/SelectRecipientForPosting.php`, `handle(JobPosting $posting): ?Contact`:
    1. Tier A: contacts of this posting with source `posting`, confidence not `rejected`, ordered by `id`, skipping any address at the cap: `Application::query()->countedToday()->where('recipient_email', $email)->count() >= cap` across all clients. First one wins (D7: never another posting's).
    2. When `$posting->company` is verified: tier B: source `named`, `smtp_verified`, first by `id`.
    3. When verified: tier C: `SelectRecipientForCompany::handle($company)`.
    4. Otherwise null.
- `QueueApplication`: use `SelectRecipientForPosting`; write `recipient_tier = $contact->tier()?->value`, `recipient_source = $contact->source->value`. Rejection strings unchanged. Assumption: the cap check isn't locked across clients, so it may be exceeded by a few under concurrency.
- `MatchingJobPostings::forUser`: replace the `companies.outreach_status` where and the contacts `whereExists` with one group:
    - (`companies.outreach_status = verified` and an `alias_probe`/`smtp_verified` contact in `RECIPIENT_PRIORITY` of the company, or a `named`/`smtp_verified` contact), **or**
    - a contact with `contacts.job_posting_id = job_postings.id`, `source = posting`, `confidence <> rejected`.
    Update the doc comment accordingly (its "has not applied to that company" sentence is stale: the code checks the posting; don't change that behaviour).
- `SendApplicationEmail::preCheck`:
    - `CheckingCompany` fails when the company is not verified **and** the contact isn't tier A of this application's posting. Message unchanged.
    - `ConfirmingRecipient` fails when `$contact` is null, or its `company_id` differs, or it is neither (tier A with `job_posting_id === $application->job_posting_id`) nor (`source` is `alias_probe`/`named` with `smtp_verified`). Message unchanged. Every other check stays.
- `JobPosting` needs no further change. Consumers of the pool (`JobPoolQuery`, `DashboardPresenter`, `LanguageUnlockCounts`, `PreferencesPreviewPresenter`, `TemplatePreviewPresenter`, `ReviewController`, `InventoryFunnel`) inherit it.
- Keep `SelectRecipientForCompany`'s public signature (Phase 3 filter stays).
- A job link is not guaranteed: `applicationUrl()` is null for an email-only HN post, and the renderer already drops that line. No change.

**Steps.**

1. Migration and model.
2. Recipient action and config.
3. `QueueApplication`, pool, send checks.

**Done when.**

- For a user and posting at an unverified company with a tier-A contact: the posting is in `MatchingJobPostings::forUser`, `QueueApplication` creates an application with `recipient_tier = 'a'`, `recipient_source = 'posting'`, and `preCheck` passes `CheckingCompany` and `ConfirmingRecipient` with `OUTREACH_INTERCEPT_TO` set.
- With 5 applications today to that address and a verified company, the recipient falls back to tier C; with an unverified company, `REJECT_NO_RECIPIENT`.
- A tier-A contact of another posting is never selected.
- `composer types:check` passes; `php artisan test --compact --filter=Outreach` passes.

**Not in this phase.** Ordering, admin, suppression.

### Phase 7 — Tier orders random sending and breaks match ties

Status: DONE
Evidence: in a rolled-back transaction with a Free user and no preferences, ranks were `{A: 1, C: 3}`; under `sort=match` with equal scores tier A came before C; the default sort kept its newest-first order and did not select the rank; random sending with one slot queued the tier-A posting; with the cap set to 1 after that send, `sendableToday` dropped the capped tier-A posting for another user and kept the verified one; `JobCardResource` output has no rank or tier key (both resources build explicit arrays); `pint --dirty`, `composer types:check` (0 errors) and `--filter=Outreach` (31 tests) pass; `--filter=JobPool` matches no tests. `code-reviewer` APPROVED in round 1. Notes: the day bounds and status list in `uncappedTierASql()` are copied from `Application::countedToday` (PHPStan rejected embedding the scope), so they must change together; `withRecipientTierRank` and `sendableToday` each embed the tier-A subquery, which is fine for now.
Role: laravel-backend · Depends on: 6 · Covers: P10 · Size: S
Origin: P10 "random sending takes tier A, then B, then C, then match score"; D9

**Goal.** Better contacts go first without changing what clients see.

**Gap -> desired.** `inRandomOrder` and score-only sort -> tier first for random sending; a tie-break under `sort=match`.

**Contract.**

- `MatchingJobPostings`:
    - `withRecipientTierRank(Builder $query): Builder` adds select `case when <uncapped tier A exists> then 1 when companies.outreach_status = 'verified' and <named smtp_verified exists> then 2 else 3 end as recipient_tier_rank`.
    - `sendableToday(Builder $query): Builder` keeps postings whose company is verified or which have an uncapped tier-A contact, so capped postings don't use up candidates.
    - "Uncapped" as SQL: `(select count(*) from applications a where a.recipient_email = contacts.email and a.status in (<countedTowardsQuota>) and a.queued_at between <today start> and <today end>) < cap`. Same day bounds as `Application::countedToday`.
- `QueueRandomApplications::handle`: candidates = `MatchScore::addSelects(sendableToday(withRecipientTierRank(forUser(...))), $criteria)`, with the existing age filter and `limit`, ordered by `recipient_tier_rank` asc, `match_score` desc, then random. Build `$criteria` as `JobPoolQuery` does. Random stays only as the final tie-break, so different clients don't all get the same postings (Assumption).
- `JobPoolQuery`: when `sort=match`, add the rank to the inner query and order by `match_score` desc, `recipient_tier_rank` asc, then the existing newest-first keys. The default sort doesn't change, and it doesn't select the rank.
- `JobCardResource` and `JobDetailResource` must not expose `recipient_tier_rank`.

**Steps.**

1. Add the two query helpers.
2. Update random sending and the pool sort.

**Done when.**

- With postings in tiers A and C for a Free user with no preferences, random sending queues tier A first.
- With equal scores under `sort=match`, tier A comes before C; the default sort's order is unchanged.
- `curl`-free check: `JobCardResource` output has no rank key.
- `composer types:check` passes; `php artisan test --compact --filter=JobPool` and `--filter=Outreach` pass.

**Not in this phase.** Any client-visible tier.

### Phase 8 — Admin shows source and tier

Status: DONE
Evidence: `ContactsRelationManager` gained `source` and `tier` badges (`—` when null), a linked `Posting` column, hidden-by-default `person_name`/`person_title`, `Local part`, an eager-loaded `jobPosting` and the unchanged `->socket(...)`; `JobPostingInfolist` has the new section description; `ApplicationInfolist` and `ApplicationsTable` show the recipient tier (and source in the infolist) from the snapshot; no `->poll()` added; `pint --dirty` and `composer types:check` (0 errors) pass; `--filter=Filament` matches no tests. `code-reviewer` round 1 found one blocking issue (the infolist `—` lived only inside `formatStateUsing`, which Filament doesn't call for a blank state); fixed with `->placeholder('—')` on both entries, confirmed against `TextEntry::render` (the placeholder renders for a blank state), checks re-run green. Notes: the screens were not rendered in a browser, so check `/admin` once by eye; the `ContactSource` badge has no colour because the enum has no `HasColor`.
Role: filament-admin · Depends on: 6 · Covers: P11 · Size: S
Origin: P11 "Admin company/contact screens always show source and tier"

**Goal.** The owner can see where each contact came from and how each application was addressed.

**Gap -> desired.** Contacts list shows email, alias, confidence, checked_at -> also source, tier and posting.

**Contract.**

- `ContactsRelationManager`: `source` badge; `tier` badge via `->state(fn (Contact $c) => $c->tier())`, placeholder `—`; `jobPosting.title` labelled `Posting`, linked to `JobPostingResource` view; `person_name`, `person_title` toggleable, hidden by default; relabel `local_part` to `Local part`. Eager-load `jobPosting`. Keep `->socket(channel: 'companies', event: 'CompanyUpdated')`.
- `JobPostingInfolist`: section description becomes `Contacts exposed by the source. Emails published in the posting can be application recipients (tier A).`
- `ApplicationInfolist`: after `recipient_email`, entries `recipient_tier` (`Recipient tier`, formatted with `ContactTier` label, `—` when null) and `recipient_source` (`Recipient source`, formatted with `ContactSource` label).
- `ApplicationsTable`: toggleable `recipient_tier` badge column. The existing `recipient_kind` column and filter stay.
- Copy is English (admin panel).

**Steps.**

1. Update the relation manager.
2. Update the posting and application screens.

**Done when.**

- `/admin` company view lists source and tier for alias and posting contacts; an application view shows the snapshot; a null snapshot shows `—`.
- No `->poll()` added. `composer types:check` passes; `php artisan test --compact --filter=Filament` passes (or matches no tests).

**Not in this phase.** Suppression resource (`landing-and-legal`).

### Phase 9 — Stored postings can be backfilled

Status: DONE
Evidence: on the dev data a dry run scanned 267 postings (67 with emails, 0 new) and left a hash of `job_postings` unchanged; `--limit=abc` fails with exit 1; in a rolled-back transaction with `source_contacts` cleared and the posting contacts removed, a dry run reported 54 postings with emails and 57 new emails, and `--force --limit=1` queued exactly one `SyncPublishedContacts`, wrote the email entry in the collection path's shape and left `collection_run_id`, `first_seen_at` and `company_id` untouched; after the rollback the hash matched; `pint --dirty` and `composer types:check` (0 errors) pass. `code-reviewer` round 1 found one blocking issue (stored emails were compared case-sensitively, so a stored `Jobs@Acme.com` would be re-added and re-queued every run); fixed by lowercasing the stored list before the diff, checks re-run green. Notes: only emails passing `matchesCompany` are stored or counted (the collection path stores all and filters later), so emails whose company domain becomes known later are not backfilled by this command; under `--force` the table counts stop at the limit; the realtime payload is empty (no ids) because a backfill has no source run; no role-family filter, as the contract doesn't ask for one; scheduling was not added.
Role: laravel-backend · Depends on: 4 · Covers: P13 · Size: S
Origin: P13 "Backfill for stored postings: contacts:extract-published {--limit=} {--force}"

**Goal.** Postings stored before Phase 1 get their emails and contacts.

**Gap -> desired.** No backfill -> a dry-run-by-default command.

**Contract.**

- New `app/Console/Commands/ContactsExtractPublished.php`, signature `contacts:extract-published {--limit= : Maximum number of postings to queue in this run} {--force : Actually update and queue the postings}`. Validate `--limit` as in `ContactsRecheck` (`/^[1-9]\d*$/`, else error and `FAILURE`).
- `JobPosting::query()->whereNull('ineligible_reason')->whereNotNull('company_id')->chunkById(200, ...)`: extract from `description_html`, `description_text`, `apply_url`; the candidates are postings with at least one email not yet in `source_contacts` or without a `posting` contact.
- Dry run: table with `scanned`, `with emails`, `new emails`, `would queue`, then `Dry run: N posting(s) would be updated and queued. Pass --force to apply.`
- `--force`: merge into `source_contacts` keeping every existing entry, through a query-builder update that doesn't touch `collection_run_id`, `first_seen_at` or `company_id`; dispatch `SyncPublishedContacts`; dispatch `JobPostingsUpdated` once at the end; print `Queued N posting(s).`
- Never deletes anything. Postings dropped by the old gate were never stored: they return through the next collection run, not through this command.

**Steps.**

1. Create the command.

**Done when.**

- `php artisan contacts:extract-published` writes nothing; `--limit=abc` fails; `--force --limit=1` queues at most one job.
- `composer types:check` passes.

**Not in this phase.** Scheduling it.

### Phase 10 — Suppression list exists and stops probing

Status: DONE
Evidence: `php artisan migrate` ran the new migration; in a rolled-back transaction with fake `DnsLookup` and `SmtpProbe`, no row and an unconfirmed domain row left discovery at 1 probe and the published-email check at 2; a confirmed domain row left discovery at 0 probes and the published-email check at 1 (only the other domain); adding a confirmed email row left it at 0 and an empty result; a row with both `domain` and `email` was rejected by the check constraint and a duplicate domain by the unique index; `pint --dirty`, `composer types:check` (0 errors) and `--filter=Outreach` (31 tests) pass; `--filter=Contact` matches no tests. `code-reviewer` APPROVED in round 1. Notes: no Filament resource or opt-out form exists yet (`landing-and-legal` P12, P13), so rows are added by hand and only take effect with `confirmed_at` set; the model lowercases and trims on write but does not reduce a subdomain to its registrable domain, so a row saved as `www.acme.com` never matches: the future admin/opt-out UI must store the registrable domain; a suppressed company stays pending until the usual 7-day give-up; the list does one small indexed query per check, uncached.
Role: laravel-backend · Depends on: 4 · Covers: P18, P18a · Size: M
Origin: P18 "applies to every tier and stops probing too"; P18a

**Goal.** A suppressed company or address is never probed.

**Gap -> desired.** No table -> `outreach_suppressions` (schema from `landing-and-legal` P13) and a list service.

**Contract.**

- New migration `create_outreach_suppressions_table`: `id`; `domain` nullable string; `email` nullable string; `reason` nullable string; `source` string; `confirmed_at` nullable timestamp; timestamps. Raw check that exactly one of `domain` and `email` is set. Partial unique indexes on `domain` and on `email` where not null. Values stored lowercase.
- New `app/Enums/SuppressionSource.php`: `OptOut = 'opt_out'`, `Admin = 'admin'`, with `HasLabel`.
- New `app/Models/OutreachSuppression.php`: fillable, casts.
- New `app/Contacts/Support/OutreachSuppressionList.php`:
    - `coversDomain(string $domain): bool`: a confirmed row (`confirmed_at` not null) on `RegistrableDomain::of($domain) ?? strtolower($domain)`.
    - `covers(string $email): bool`: a confirmed row on the exact lowercase email, or `coversDomain` of its domain.
- `DiscoverCompanyContacts::handle`: just before the MX lookup, when `coversDomain($domain)`: `$this->markInconclusive($company)` and return, with no probe.
- `VerifyPublishedEmails`: emails with `covers()` true are left out of the result: no probe and no row, so they aren't sendable.
- No Filament resource or opt-out form here (`landing-and-legal` P12, P13). Until they ship, rows are only added by hand; say so in the phase report.

**Steps.**

1. Migration, enum, model, service.
2. Add the probe skips.

**Done when.**

- `php artisan migrate` runs; with a confirmed domain row, a fake `SmtpProbe` isn't called from either path; an unconfirmed row has no effect.
- `composer types:check` passes; `php artisan test --compact --filter=Contact` passes (or matches no tests).

**Not in this phase.** Queueing and sending.

### Phase 11 — Suppressed recipients are not queued or sent

Status: DONE
Evidence: in a rolled-back transaction with Queue, Notification and Storage faked and `intercept_to` set, an application queued to a tier-A address before any suppression; an unconfirmed row didn't affect the selector and a confirmed one made it return null; `QueueApplication` then rejected with `REJECT_SUPPRESSED`, shown as "This company asked not to receive applications." in EN and "Esta empresa pediu para não receber candidaturas." in PT, and a suppressed company domain with no tier-A contact gave the same rejection while no suppression and no recipient still gave `REJECT_NO_RECIPIENT`; `preCheck` on a queued application to the suppressed address returned null with a strict mock transport that throws on any call, leaving the row `cancelled` with the expected `last_error`, no stage, and 0 counted towards quota; `pint --dirty`, `composer types:check` (0 errors) and `--filter=Outreach` (31 tests) pass. `code-reviewer` APPROVED in round 1. Notes: tier B now takes the first non-suppressed named contact by id; at tier C a suppressed top-priority alias returns null instead of trying a lower one; the queue-time check counts every tier-A email including rejected ones, so a posting can show the suppressed message when the real cause is no recipient (only on a reject that was happening anyway); `afterTerminal` on a cancelled row also runs the repeated-failures check, which could in a rare case re-trigger the auto-pause (the contract asked for `afterTerminal`).
Role: laravel-backend · Depends on: 6, 10 · Covers: P18, P18a · Size: M
Origin: P18, P18a

**Goal.** The list applies to every tier at queue and send time.

**Gap -> desired.** Applications ignore suppression -> rejected at queue time, cancelled before delivery.

**Contract.**

- `SelectRecipientForPosting`: skip any contact with `covers($contact->email)`, in every tier. `SelectRecipientForCompany`'s result goes through the same check.
- `QueueApplication`: new `public const string REJECT_SUPPRESSED = 'This company asked not to receive applications.'`. When no recipient was selected and the company's domain or any of its posting's tier-A contacts is covered, reject with it; otherwise `REJECT_NO_RECIPIENT`. `QueueRandomApplications` already continues on rejections.
- `QueueRejectionMessage::EXACT`: map to `queue.reject.suppressed`.
- `SendApplicationEmail::preCheck`, at the start, before the checks: when `covers($application->recipient_email)`: set status `Cancelled`, `last_error = 'Recipient asked not to receive applications.'`, `$recorder->reset($application)`, `$this->afterTerminal($application)`, return null. Cancelled rows don't count against quota or failures.
- `ClientErrorMessage::PREFIXES`: `'Recipient asked not to receive applications.' => 'applications.error.suppressed'`.
- `lang/en.json` and `lang/pt.json`, next to the existing keys:
    - `queue.reject.suppressed`: EN `This company asked not to receive applications.` / PT `Esta empresa pediu para não receber candidaturas.`
    - `applications.error.suppressed`: EN `This company asked not to receive applications.` / PT `Esta empresa pediu para não receber candidaturas.`
- Assumption: suppressed postings stay in the pool and are rejected when queued, as `landing-and-legal` P13 describes.

**Steps.**

1. Update selection and queueing.
2. Add the send check and the copy.

**Done when.**

- With a confirmed row: `QueueApplication` rejects with the client message in EN and PT; a queued application to that address ends `cancelled`, with no transport call.
- `composer types:check` passes; `php artisan test --compact --filter=Outreach` passes.

**Not in this phase.** Opt-out form, Filament resource.

### Phase 12 — Replies can be recorded and reported

Status: PENDING
Role: laravel-backend · Depends on: 6 · Covers: P12 · Size: M
Origin: P12 "a client action 'I got a reply' on sent applications (replied_at), and a read-only report reports:tier-performance"

**Goal.** Reply rates per tier can be measured.

**Gap -> desired.** No reply data -> `replied_at`, an endpoint and a report.

**Contract.**

- New migration `add_replied_at_to_applications_table`: nullable timestamp `replied_at`.
- `Application`: fillable-independent `forceFill` write, cast `datetime`, `@property`.
- Route, in the same group as `applications.counts` in `routes/web.php`: `Route::post('/applications/{application}/reply', [ApplicationsController::class, 'reply'])->name('applications.reply');`
- `ApplicationsController::reply(MarkApplicationRepliedRequest $request, Application $application): ApplicationDetailResource`: `Gate::authorize('view', $application)`; 422 unless status is `sent`; `replied_at = $validated['replied'] ? ($application->replied_at ?? now()) : null`; loads the same relations as `show`. New `app/Http/Requests/Client/MarkApplicationRepliedRequest.php`: `replied` required boolean. Same `client` middleware as the group.
- `ApplicationItemResource`: add `'repliedAt' => $application->replied_at?->toIso8601String()`. Check that the `Application` selected columns include it.
- New `app/Reports/TierPerformance.php` and `app/Console/Commands/TierPerformanceReport.php`, signature `reports:tier-performance {--days=30} {--json}`, `--days` validated like `FunnelReport` (1 to 365). Read-only: `status = sent` and `sent_at` in the window, grouped by `recipient_tier` (`null` shown as `unknown`): sent, replies, reply rate (one decimal, `0.0%` with no sends). Note under the table: `Replies are client-reported, so the counts are a minimum.`

**Steps.**

1. Migration, model and resource.
2. Endpoint.
3. Report.

**Done when.**

- `POST` marks and unmarks a client's own sent application; another client's gets 403; a queued one gets 422.
- `php artisan reports:tier-performance` and `--json` run on an empty database and show zeros.
- Run `php artisan wayfinder:generate` if the route isn't generated in dev. `composer types:check` passes; `php artisan test --compact --filter=Applications` passes.

**Not in this phase.** UI.

### Phase 13 — Clients can say "I got a reply"

Status: PENDING
Role: inertia-frontend · Depends on: 12 · Covers: P12 · Size: S
Origin: P12

**Goal.** A button on the application detail sheet.

**Gap -> desired.** Detail sheet is read-only -> a sent application can be marked and unmarked.

**Contract.**

- `resources/js/types/contracts.ts`: `ApplicationItem` gains `repliedAt: ISODateTime | null`. Update the fixtures that build `ApplicationItem` (`resources/js/data/fixtures/`), plus a fixture handler for the action, so `VITE_USE_FIXTURES` keeps working.
- `resources/js/data/hooks/use-application.ts` (or a sibling following `use-queue-applications.ts`): a mutation posting `{ replied: boolean }` to the Wayfinder route `applications.reply`, updating the detail and list caches. No `useMemo`/`useCallback`. No polling.
- `resources/js/features/applications/application-detail-sheet.tsx`: when `status === 'sent'`, a section after the CV with a `Button`: `repliedAt === null` shows `applications.detail.reply_action`; otherwise a line with `applications.detail.replied_on` and a button with `applications.detail.reply_undo`. The button is disabled while saving. On failure show `applications.detail.reply_error`.
- `lang/en.json` and `lang/pt.json`:
    - `applications.detail.reply_action`: `I got a reply` / `Recebi uma resposta`
    - `applications.detail.reply_undo`: `Undo` / `Desfazer`
    - `applications.detail.replied_on`: `Reply recorded on :date` / `Resposta registrada em :date`
    - `applications.detail.reply_error`: `We could not save this. Try again.` / `Não foi possível salvar. Tente novamente.`
- Nothing about tier, source or recipient appears (D10).

**Steps.**

1. Contract and fixtures.
2. Mutation.
3. UI and copy.

**Done when.**

- On `/applications`, a sent application's sheet marks and undoes a reply, survives reload and works in EN and PT; other statuses show no button.
- `yarn check` passes; `yarn build` if the bundle is at risk.

**Not in this phase.** Reply counts on the dashboard.

### Phase 14 — Company pages can be fetched safely

Status: PENDING
Role: laravel-backend · Depends on: 10 · Covers: P14, P16 · Size: M
Origin: P14 "company's /about, /team, /company pages (fetched through PublicNetworkGuard, robots.txt respected, 1 request/s per domain, short timeouts)"

**Goal.** A safe, polite fetcher for Stage 2.

**Gap -> desired.** No website fetcher -> one that can't reach private networks and respects robots.txt.

**Contract.**

- New `app/Contacts/Support/CompanyPageFetcher.php`, `fetch(string $domain): array` returning `array<string, string>` path => plain text, for `/about`, `/team`, `/company`.
    - Returns `[]` when `OutreachSuppressionList::coversDomain($domain)`.
    - `https://` only. Before each request `PublicNetworkGuard::publicAddresses($host)`; `[]` means skip. Pin with the Laravel HTTP client option `['curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$address}"]]]`, keeping the hostname for TLS.
    - No automatic redirects (`allow_redirects => false`). Follow at most 2 by hand, only to the same registrable domain, re-running the guard each time.
    - Connect timeout 3s, total 5s; body cap 512 KB (stop reading and skip above it); `text/html` only.
    - User agent `talent-labs/0.1 (+company page check)`.
    - At least 1 second between requests to a domain, robots.txt included (`usleep`, measured per call).
- New `app/Contacts/Support/RobotsRules.php`: parses robots.txt without a new package: the `User-agent: *` group, `Disallow`/`Allow` prefix rules with longest match, `isAllowed(string $path): bool`. A 404 allows; a fetch failure or 5xx disallows everything (Assumption, conservative).
- Text: strip `script`/`style`, then tags, decode entities, collapse whitespace, cap at 8000 characters.
- Assumption: only these three paths, never crawling links (P16).

**Steps.**

1. Robots parser.
2. Fetcher.

**Done when.**

- A host resolving to `127.0.0.1` or `169.254.169.254` is never requested; a redirect to another domain isn't followed; a `Disallow: /about` skips the path; `Http::fake` checks pass in tinker.
- `composer types:check` passes.

**Not in this phase.** AI extraction, report.

### Phase 15 — Named-contact coverage report

Status: PENDING
Role: laravel-backend · Depends on: 14 · Covers: P14, P16, P18 · Size: M
Origin: P14 "reports:named-contact-coverage {--sample=50} … Stop and show the report to the owner"

**Goal.** Numbers to decide on Stage 2, with no sending and nothing stored.

**Gap -> desired.** Unknown coverage -> a read-only report.

**Contract.**

- New `app/Ai/Agents/ExtractCompanyPeople.php`, modelled on `ExtractJobPostingProfile` (`#[Provider(Lab::OpenAI)]`, `#[Model('gpt-5-nano')]`, `MODEL`, `CACHE_KEY = 'company_people_extraction'`, structured output, `BuildsCompactAgentContext`): input is page text and posting text; output `people: list<{name, title}>`, evidence only, never invented, no emails.
- New `app/Contacts/Support/NamedEmailCandidates.php`, `for(string $name, string $domain): array`: in order `first@`, `first.last@`, `flast@`, `firstlast@`; lowercase; `Str::ascii`; first and last words of the name; empty for single-word names.
- New `app/Contacts/Support/HiringPowerTitle.php`, `matches(?string $title): bool`: founder, co-founder, CEO, CTO, head of engineering, head of people, head of talent; kind `hiring_manager` from `source_contacts` always counts.
- New `app/Reports/NamedContactCoverage.php` and `app/Console/Commands/NamedContactCoverageReport.php`, signature `reports:named-contact-coverage {--sample=50} {--input-usd-per-mtok=} {--output-usd-per-mtok=} {--json}`.
    - Sample: verified companies with a known domain, not suppressed, taken evenly across sources through their postings, random within a source.
    - Names: YC `source_contacts` founders and hiring managers; the agent over posting text and `CompanyPageFetcher` pages, with a `HiringPowerTitle` filter.
    - Probe: for a company with a name and `is_catch_all = false`, `SmtpProbe` with a random address plus the candidates. Accepted while the random address is rejected = verified. Inconclusive sessions are counted apart.
    - Output: companies sampled, per source; % with a plausible name; % with a verified named email; % catch-all; % inconclusive; input and output tokens; USD only if both price options are given (no price exists in the repo, never invent one).
    - **Writes nothing** except `ai_usage_records` for the agent calls (`job_posting_id` null). No `contacts` rows, no names persisted, no application. No personal data in logs. `--json` output holds aggregates only.
    - When every session is inconclusive, warn that port 25 is probably blocked.
- Runs on a machine with real collected data and outbound port 25; the local database is empty today.
- **Stop after the report: `/exec-phase` reports it to the owner, and Phase 16 stays blocked.**

**Steps.**

1. Agent and helpers.
2. Report and command.

**Done when.**

- `php artisan reports:named-contact-coverage --sample=0` is rejected; on an empty database it reports zeros with no error.
- With fakes, row counts in `contacts` and `applications` don't change after a run.
- `composer types:check` passes.

**Not in this phase.** Storing named contacts.

### Phase 16 — Named contacts in production

Status: BLOCKED (D15, D16)
Role: laravel-backend · Depends on: 15 · Covers: P15, P18 · Size: M
Origin: P15 "Only if the owner approves after P14"; P18 privacy

**Goal.** Store verified named decision-makers as tier B.

**Gap -> desired.** No `named` contacts -> a job that creates them. Selection, pool, send checks and admin already handle them (Phases 6, 8).

**Contract.**

- New `app/Contacts/Jobs/DiscoverNamedContacts.php`: queue `contacts`, `$tries = 1`, `$timeout = 75`, `ShouldBeUnique` per company. Only for a verified company with `is_catch_all = false`, not suppressed, not checked within 30 days.
- Reuses `CompanyPageFetcher`, `ExtractCompanyPeople`, `HiringPowerTitle`, `NamedEmailCandidates`.
- Probe only when the random address gets a conclusive rejection. When more than one candidate is accepted, take the first in the P15 order.
- Stored: `source = named`, `confidence = smtp_verified`, `email`, `local_part`, `person_name`, `person_title`, `checked_at`, and nothing else about the person (P18).
- Roles: founder, CEO, CTO, head of engineering/people/talent, the hiring manager named in the posting.
- Never store or send an unverified guess. No LinkedIn, no search engines (P16).
- Retention: implement D16's rule (answer pending). Trigger and schedule: confirm with the owner together with D15.

**Steps.**

1. Re-read D15 and D16; if either is unanswered, stop.
2. Build the job and the retention rule.

**Done when.**

- With fakes, a verified company gets one tier-B contact and applications select it ahead of tier C. A catch-all company gets none.
- The retention rule from D16 runs as decided. `composer types:check` passes.

**Not in this phase.** Paid finder API (D12).

### Phase 17 — Verification and report

Status: PENDING
Role: laravel-backend · Depends on: 1-15 (16 if unblocked) · Covers: all · Size: S
Origin: spec x code gap (mandatory final phase)

**Goal.** Prove the feature and hand the owner a checklist.

**Gap -> desired.** Phases done individually -> an integrated check.

**Contract.**

- Full gate: `composer ci:check`.
- Forbidden patterns, in the files changed by this plan: `->poll(`, `wire:poll`, `$pollingInterval`, Spanish copy. Also search app code for `linkedin` scraping or search-engine fetches and for git write commands in scripts.
- Client exposure: `grep` `JobCardResource`, `JobDetailResource`, `ApplicationItemResource`, `ApplicationDetailResource`, `ReviewDraftPresenter` and `resources/js` for `recipient_tier`, `recipient_source`, `recipient_tier_rank`, `source_contacts`. Expect no match.
- Requirement-by-requirement walkthrough of P1-P18a, each with its evidence file.
- Report these to the owner, without building them:
    - a bounce at send time doesn't mark a tier-A contact as bad for other clients;
    - `reports:funnel` counts verified companies only, so it doesn't show tier-A postings of unverified companies;
    - `landing-and-legal` P13 must reuse the table and checks built here;
    - run `/update-feature-spec job-mining` for §2.4 and §2.8 (P6).

**Steps.**

1. Run the gate and the greps.
2. Do the walkthrough and write the report.

**Done when.**

- `composer ci:check` passes, or each failure is reported with its output.
- The owner's manual checklist is delivered:
    - with `OUTREACH_INTERCEPT_TO` set, run a collection and find a posting with a published email in `/admin`, with source and tier;
    - queue it from a client account and check the `[TEST — would go to …]` subject shows the published address;
    - confirm the client UI shows no recipient or tier;
    - mark a reply and run `php artisan reports:tier-performance`;
    - add a confirmed suppression row by hand and confirm queueing is rejected;
    - run `php artisan contacts:extract-published` as a dry run;
    - review the Phase 15 report and answer D15 and D16.

**Not in this phase.** New behaviour.
