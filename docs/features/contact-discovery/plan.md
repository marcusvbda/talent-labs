# Plan — Contact Discovery MVP

Source spec: `docs/features/contact-discovery/spec.md` · SHA-256 `e95a5d9d797468b6a09a2dc2bd80052be1b0c3fa7c62125512d85f84fcbf1eae`
Product truth: Part B of `docs/features/contact-discovery/spec.md`. (The spec header
asks to copy Part B into `docs/features/contact-discovery-mvp/spec.md`. That was not
done because `CLAUDE.md` forbids duplicated docs. This folder is the feature folder.)
Run phases with `/execute-phases docs/features/contact-discovery/plan.md <phases>`, one
or a few per session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                              | Role            | Depends on | Size | Status         |
| ----- | -------------------------------------------------- | --------------- | ---------- | ---- | -------------- |
| 1     | Port-25 connectivity check (evidence only)         | laravel-backend | none       | S    | DONE           |
| 2     | Enums, companies/contacts tables, models, realtime | laravel-backend | none       | M    | DONE           |
| 3     | `job_postings.company_id` + company resolution     | laravel-backend | 2          | S    | DONE           |
| 4     | DNS lookup + domain resolution                     | laravel-backend | 2          | S    | DONE           |
| 5     | SMTP probe client (never sends)                    | laravel-backend | 1          | S    | DONE           |
| 6     | Contact discovery action                           | laravel-backend | 1, 4, 5    | S    | DONE           |
| 7     | Orchestration job + wiring into collection         | laravel-backend | 3, 6       | M    | DONE           |
| 8     | Backfill command + retry backend                   | laravel-backend | 7          | S    | DONE           |
| 9     | Companies admin resource                           | filament-admin  | 2, 8       | M    | DONE           |
| 10    | Job postings: Contact column + filter              | filament-admin  | 2, 3       | S    | DONE           |
| 11    | Verification and report                            | laravel-backend | 1–10       | M    | BLOCKED (gate) |

## Audit — 2026-09-23

| Check                      | Result                                                                                                                                                                                                                                                                         |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Framework                  | Laravel 13.33.0, PHP 8.4.3, `filament/filament` ^5, `marcusvbda/filament-realtime-driver` ^0.1.2, PostgreSQL, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`                                                                                                              |
| Migrations                 | 10 migrations, all ran, none pending. No `companies`/`contacts` tables. `job_postings` has no `company_id`.                                                                                                                                                                    |
| Data                       | 1045 `job_postings` but only **15 distinct `company_name`** (Stripe 683, Palantir 312, Linear 31, 12 Remotive companies). 4 sources (Stripe/greenhouse, Palantir/lever, Linear/ashby, Remotive). 1 run. Queue empty, 0 failed jobs.                                            |
| Realtime trait             | `App\Models\Concerns\BroadcastsRealtime::broadcastRealtime(string $channel, string $event, array $payload)`, used by `Source`/`CollectionRun` in `booted()` `saved`/`deleted` hooks                                                                                            |
| `FetchJobsFromSource`      | Query-builder `upsert` in chunks of 200. New-posting count = chunk external ids minus existing ones. `MUTABLE_COLUMNS` and insert rows exclude `company_id`, so an upsert never clears it. Emits `job_postings`/`JobPostingsUpdated` once after success.                       |
| Queue worker               | `AppServiceProvider::boot()` registers `queue:work database --queue=collection,default`. **The `contacts` queue is not consumed**, so Phase 7 adds it.                                                                                                                         |
| Queue timing               | `DB_QUEUE_RETRY_AFTER` default 90 s, so a contacts job `$timeout` must stay under 90                                                                                                                                                                                           |
| Locks                      | `Cache::lock` already used (`StartCollectionRun`) on the database cache store                                                                                                                                                                                                  |
| DNS helper                 | Boost `search-docs` finds no Laravel DNS helper → use PHP `dns_get_record()`                                                                                                                                                                                                   |
| SMTP                       | No new dependency needed: raw PHP stream sockets. Symfony Mailer is present but not used, because its transports exist to send mail.                                                                                                                                           |
| Port 25                    | **Checked in Phase 1: usable** (see Phase 1 evidence).                                                                                                                                                                                                                         |
| Admin patterns             | Resources split into `Pages/`, `Schemas/`, `Tables/`, `RelationManagers/`. View-page realtime is `resources/views/filament/collection-runs/realtime-listener.blade.php` + `ViewCollectionRun::content()`. Enums implement `HasLabel`, `HasColor`.                              |
| Verification commands      | `vendor/bin/pint --dirty --format agent`, `composer lint:check`, `composer types:check`, `composer test`, `yarn check`, `yarn types:check`, `composer ci:check`. Tests: only the Example tests exist.                                                                          |
| Normalization observations | With the single `<slug>.com` guess (B.3.2): `Linear` → `linear.com` (Linear's product domain is `linear.app`, so this may be a false positive), `Lemon.io` → `lemonio.com`, `A.Team` → `ateam.com`. The spec says to flag these, not fix them (B.10). Report them in Phase 11. |
| Prior feature              | `job-collection-mvp` has no `plan.md`. Its AC06–AC12 are quoted in Phase 11 for the AC12 spot-check.                                                                                                                                                                           |

## Owner decisions

### D1 — SMTP identity used for probes (HELO name and MAIL FROM)

**RESOLVED 2026-09-23: A.**
Blocks: Phase 5 · Options:
**A (recommended)** Null reverse-path `MAIL FROM:<>` plus `EHLO {helo}`, where `helo`
comes from the new config `talent.contacts.smtp_helo` (env `CONTACTS_SMTP_HELO`,
default `localhost`). Optionally an owner address via `CONTACTS_SMTP_MAIL_FROM`
(empty = `<>`). **B** Require a real owner address/domain in MAIL FROM, because some
servers reject `<>`. That ties the owner's domain to probe traffic from a residential
IP. **C** Hard-code a placeholder like `probe@localhost`.
Why: this is whose identity appears in third-party mail logs, and it can affect how
many servers answer RCPT at all. It's a product and reputation call, not code.

### D2 — What "Retry discovery" does to the company's existing contacts

**RESOLVED 2026-09-23: A.**
Blocks: Phase 8 · Options:
**A (recommended)** Delete that company's `contacts` rows as part of the reset. They
are derived data, and the retry re-creates them fresh, so aliases that stopped resolving
disappear.
**B** Keep the rows and upsert by `(company_id, local_part)`. Stale aliases keep their
old `checked_at` and confidence.
Why: B.7 lists only status fields to reset. Without A, a retry can show contacts that
no longer verify.

### D3 — Alias RCPT gets a 4xx or no answer on a server that rejected the catch-all probe

**RESOLVED 2026-09-23: A.**
Blocks: Phase 6 · Conflict: B.3.3 says to store "inconclusive-but-plausible" results.
AC05 says "only aliases that get a positive RCPT are stored, with
`confidence = smtp_verified`".
**A (recommended)** Follow AC05. On a server proven to reject unknown addresses (probe
got 5xx), store only 2xx aliases as `smtp_verified`, and skip 4xx/timeout aliases.
`mx_only` stays reserved for "we could not get a trustworthy verdict for the whole
domain" (see the mapping in Phase 6).
**B** Follow B.3.3 literally: store 4xx/timeout aliases as `mx_only` next to
`smtp_verified` ones.
Why: two spec statements disagree, and the choice changes what the admin sees.

## Global constraints (every phase)

- No git writes, ever (`CLAUDE.md`). No new composer/npm packages. No tests written or
  modified. English everywhere.
- DB: only forward `php artisan migrate`. Never fresh/refresh/reset/rollback/wipe/DROP/TRUNCATE.
- `.env`: never overwrite values, only append missing keys. `.env.example` gets new keys.
- Realtime only via the driver (`->socket()`, `<x-filament-realtime-driver::listener>`).
  Never `->poll()`, `wire:poll` or `$pollingInterval`. Public channels carry ids only.
  Never broadcast once per posting (job-collection invariant).
- **Never send email.** The SMTP client may issue only `EHLO`/`HELO`, `MAIL FROM`,
  `RCPT TO` and `QUIT`, and never `DATA`, `BDAT` or a message body (AC11). One
  connection per company, no parallel probes against one domain, ~5 s timeouts, one
  attempt.
- DNS or SMTP failures never escape as unhandled exceptions. They resolve to
  `not_found`/`no_domain`/`mx_only` (B.3.4).
- `job-collection-mvp` behavior is untouched. That covers `FetchJobsFromSource`
  counters, statuses, `MUTABLE_COLUMNS`, the insert-row shape, the `JobPostingsUpdated`
  emit, the batch/finalize flow, and the existing Job postings grouping, filters,
  actions and socket. `company_id` must never be added to `MUTABLE_COLUMNS` or the
  upsert rows.
- `/app` panel untouched. Contact emails appear only in `/admin`.
- Out of scope (B.10 / Part 0): sending, templates, CV, LinkedIn/named contacts,
  Hunter/Apollo/Clearbit, multiple TLD guesses, WHOIS/scraping, extra catch-all
  heuristics, scheduled retries, CSV export, merging duplicate companies, and extra
  normalization rules beyond B.3.1. Flag, don't build.
- Namespaces as the spec names them: `App\Contacts\Actions\…`, `App\Contacts\Jobs\…`.
  Enums go in `App\Enums` (existing convention).

## Acceptance-criteria coverage

| AC   | Phases   |
| ---- | -------- |
| AC01 | 1, 11    |
| AC02 | 3, 7, 11 |
| AC03 | 4, 6, 11 |
| AC04 | 6, 11    |
| AC05 | 5, 6, 11 |
| AC06 | 6, 9, 11 |
| AC07 | 3, 8, 11 |
| AC08 | 8, 9, 11 |
| AC09 | 10, 11   |
| AC10 | 2, 9, 11 |
| AC11 | 5, 11    |
| AC12 | 7, 11    |

## Phases

### Phase 1 — Port-25 connectivity check, recorded as evidence

Status: DONE
Role: laravel-backend (shell only, no code) · Depends on: none · Covers: AC01 · Size: S
Spec: Part 0.1, B.9.1

**Goal.** Find out whether this machine can open outbound TCP port 25 before any SMTP
code is built. Record the verdict here.

**Contract.**

- Verdict is one of: `usable` (TCP connect succeeds **and** a `220` banner is read) or
  `mx-only fallback` (connect refused, times out, or no banner).
- No code, no config, no files other than this plan's Phase 1 evidence.
- Never send anything beyond `QUIT` on the socket.

**Steps.**

1. Resolve MX hosts for two collected companies: `dig +short MX stripe.com` and
   `dig +short MX palantir.com`.
2. For the lowest-preference host of each, run `nc -vz -G 5 -w 5 <host> 25`, then read
   the banner: `(sleep 3; echo QUIT) | nc -w 6 <host> 25 | head -3`.
3. If the session's permission layer denies these commands (it denied them during the
   audit), stop and ask the owner to run them with the `!` prefix, then use their
   output.
4. Append an **Evidence** block to this phase with the commands, raw output, verdict,
   and date.

**Done when.**

- This phase has an Evidence block with the raw output and a `usable` or
  `mx-only fallback` verdict. The owner has been told the verdict.

**Evidence (2026-09-23, run by the owner in-session):**

- `dig +short MX stripe.com` → primary `aspmx.l.google.com` (10). `dig +short MX palantir.com` → `mx0a-00153501.pphosted.com` / `mx0b-00153501.pphosted.com` (20).
- `nc -vz -G 5 -w 5 aspmx.l.google.com 25` → `Connection … port 25 [tcp/smtp] succeeded!`
- `(sleep 3; echo QUIT) | nc -w 6 aspmx.l.google.com 25 | head -3` → `220 mx.google.com ESMTP … - gsmtp`
- `nc -vz -G 5 -w 5 mx0a-00153501.pphosted.com 25` → `succeeded!`
- **Verdict: `usable`.** Outbound port 25 works from this machine, so SMTP verification is on (AC01).

**Not in this phase.** Any PHP code (Phase 5 builds the client that handles both
outcomes).

---

### Phase 2 — Enums, companies/contacts tables, models, Company realtime

Status: DONE
Role: laravel-backend · Depends on: none · Covers: AC10 (emit side) · Size: M
Spec: B.2, B.5

**Goal.** The data model for companies and contacts exists and is migrated, and any
`Company` save/delete broadcasts `companies`/`CompanyUpdated`.

**Contract.**

- `App\Enums\DomainStatus: string implements HasLabel, HasColor`:
  `Pending='pending'` ("Pending", gray), `Found='found'` ("Found", success),
  `NotFound='not_found'` ("Not found", danger).
- `App\Enums\ContactStatus`: `Pending='pending'` ("Pending", gray),
  `NoDomain='no_domain'` ("No domain", gray), `Found='found'` ("Found", success),
  `NotFound='not_found'` ("Not found", danger).
- `App\Enums\ContactConfidence`: `SmtpVerified='smtp_verified'` ("SMTP verified",
  success), `CatchAll='catch_all'` ("Catch-all", warning), `MxOnly='mx_only'`
  ("MX only", info). All three badges must look different (AC06).
- Migration `create_companies_table`: `id`; `name` string; `normalized_name` string
  **unique**; `domain` string nullable; `domain_status` string default `pending`;
  `domain_checked_at` timestamp nullable; `contact_status` string default `pending`;
  `contact_checked_at` timestamp nullable; `is_catch_all` boolean nullable; timestamps.
- Migration `create_contacts_table`: `id`; `company_id` foreignId constrained
  `cascadeOnDelete`; `email` string; `local_part` string; `confidence` string;
  `checked_at` timestamp; timestamps; **unique** `(company_id, local_part)`.
- `App\Models\Company`: `#[Fillable([...all non-id columns])]`, `@property` docblock
  like `Source`, casts (`domain_status` → `DomainStatus`, `contact_status` →
  `ContactStatus`, `is_catch_all` → boolean, the two `*_checked_at` → datetime),
  `contacts(): HasMany<Contact>`, `jobPostings(): HasMany<JobPosting>` (FK
  `company_id`). Uses `BroadcastsRealtime`. `booted()` `saved`/`deleted` →
  `broadcastRealtime('companies', 'CompanyUpdated', ['id' => $company->id])`. Do not
  write a new trait.
- `App\Models\Contact`: fillable `company_id, email, local_part, confidence,
checked_at`; casts `confidence` → `ContactConfidence`, `checked_at` → datetime;
  `company(): BelongsTo<Company>`. No realtime of its own.

**Steps.**

1. Create the 3 enums (mirror `App\Enums\SourceRunStatus`).
2. Create the 2 migrations (style of `create_job_postings_table`) and run
   `php artisan migrate`.
3. Create the `Company` and `Contact` models.

**Done when.**

- `php artisan migrate:status` shows both new migrations as Ran, and the schema matches
  the contract (Boost `database-schema`).
- In tinker, creating a `Company` works and a duplicate `normalized_name` fails on
  the unique index. The tinker row is deleted afterwards. That's application data the
  phase created, not a table wipe.
- `vendor/bin/pint --dirty --format agent` and `composer types:check` pass.

**Evidence:** `php artisan migrate` ran both migrations (batch 2); `db:table` matches the contract (unique `normalized_name`, unique `(company_id, local_part)`, cascade FK); tinker duplicate `normalized_name` threw `UniqueConstraintViolationException` and the row was deleted; `composer lint:check` and `composer types:check` pass (0 errors); `code-reviewer` APPROVED, no findings.

**Not in this phase.** `job_postings.company_id` (Phase 3), any discovery logic,
admin UI.

---

### Phase 3 — `job_postings.company_id` + company resolution

Status: DONE
Role: laravel-backend · Depends on: 2 · Covers: AC02, AC07 (no duplicate companies) · Size: S
Spec: B.2 (`job_postings`), B.3.1

**Goal.** Postings can point at a company, and one action resolves a posting's company
idempotently.

**Contract.**

- Migration `add_company_id_to_job_postings_table`:
  `foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()` plus
  `index('company_id')`. Nothing else in `job_postings` changes.
- `JobPosting`: add `@property int|null $company_id`,
  `company(): BelongsTo<Company, $this>`. Do **not** add `company_id` to `#[Fillable]`
  unless needed. Never touch `FetchJobsFromSource::MUTABLE_COLUMNS`.
- `App\Contacts\Actions\ResolveCompanyForPosting`:
    - `public function handle(JobPosting $posting): Company`
    - `public function normalize(string $companyName): string`: `mb_strtolower`, trim,
      remove punctuation (`/[^\p{L}\p{N}\s]/u` → `''`), collapse whitespace, then
      repeatedly strip a **trailing** token in `inc, llc, ltd, gmbh, sa, co, corp,
corporation, company, plc`, then trim. Punctuation is removed first so
      `S.A.`/`, LLC` match. Examples: `"Credit Wellness, LLC"` → `credit wellness`,
      `"Sanctuary Computer Inc"` → `sanctuary computer`,
      `"hey contact heroes GmbH"` → `hey contact heroes`, `"A.Team"` → `ateam`.
    - `Company::firstOrCreate(['normalized_name' => $n], ['name' => $posting->company_name])`.
      It is race-safe with the unique index (Laravel falls back to `createOrFirst`).
    - Persist with a query-builder update that skips model events and doesn't touch
      `updated_at`:
      `JobPosting::query()->whereKey($posting->id)->toBase()->update(['company_id' => $company->id])`.
      Then set `$posting->company_id` in memory. No per-posting broadcast (company
      creation already emits `CompanyUpdated`).
    - If the normalized name is empty, fall back to the lowercase trimmed raw name.

**Steps.**

1. Migration → `php artisan migrate`.
2. Add the relation to `JobPosting`.
3. Write the action.

**Done when.**

- The column and index exist. Existing 1045 postings have `company_id` null.
- In tinker, running the action on one Stripe posting twice yields one `companies` row
  (`stripe`), and the posting's `company_id` is set. The tinker-created rows are left
  in place; the backfill uses them.
- Pint and `composer types:check` pass.

**Evidence:** `php artisan migrate` added nullable `job_postings.company_id` (FK set null + index); `normalize()` verified on the plan's examples; `handle()` run twice on one Stripe posting gave one `stripe` company and set its `company_id` without touching `updated_at` (rows left for the backfill); `FetchJobsFromSource` diff empty; `composer lint:check` and `composer types:check` pass (subagent hit an intermittent PHPStan 128M memory crash once; re-run passed); `code-reviewer` APPROVED, no findings.

**Not in this phase.** Dispatching anything, domain/contact discovery.

---

### Phase 4 — DNS lookup + domain resolution

Status: DONE
Role: laravel-backend · Depends on: 2 · Covers: AC03 (domain side) · Size: S
Spec: B.3.2

**Goal.** Given a pending company, decide `found`/`not_found` for its single
`<slug>.com` candidate via DNS, never throwing.

**Contract.**

- `App\Contacts\Support\DnsLookup`:
    - `mxHosts(string $domain): list<string>`: `dns_get_record($domain, DNS_MX)` with
      warnings suppressed. `false`/errors → `[]`. Sorted by `pri` ascending (lowest
      preference value = primary). Drops a null MX (`target` `''` or `'.'`, RFC 7505).
    - `hasAddress(string $domain): bool`: any `DNS_A` or `DNS_AAAA` record. Errors →
      `false`.
- `App\Contacts\Actions\ResolveCompanyDomain::handle(Company $company): void`:
    - Return immediately if `domain_status !== DomainStatus::Pending`.
    - Candidate: `Str::slug($company->normalized_name, '') . '.com'`
      (`"open ai"` → `openai.com`). An empty slug means `not_found`.
    - `found` when `mxHosts()` is non-empty **or** `hasAddress()` is true. Then set
      `domain = candidate`. Otherwise `not_found` with `domain = null`.
    - Always set `domain_checked_at = now()`. One Eloquent `save()` (emits
      `CompanyUpdated`).
    - A domain whose only MX is a null MX counts as `not_found` even if it has an A
      record.

**Steps.**

1. Write `DnsLookup`, then the action.

**Done when.**

- In tinker, a company `stripe` resolves `found`/`stripe.com`, and a company named
  `zzqxnonexistent987` resolves `not_found`, `domain` null. Delete the throwaway row
  afterwards. A second call is a no-op.
- Pint and `composer types:check` pass.

**Evidence:** tinker: `stripe` -> found/`stripe.com`, second call a no-op, throwaway `zzqxnonexistent987` -> not_found with null domain (row deleted); `composer lint:check` and `composer types:check` pass; `code-reviewer` APPROVED. Non-blocking: an extra public `DnsLookup::hasOnlyNullMx()` was added for the null-MX rule, and MX is queried twice when there is no usable MX (one extra DNS query, accepted).

**Not in this phase.** SMTP, contacts.

---

### Phase 5 — SMTP probe client (handshake only, never sends)

Status: DONE
Role: laravel-backend · Depends on: 1 · Covers: AC11, AC05/AC06 transport · Size: S
Spec: B.3.3 (transport rules), Part 0.1

**Goal.** A minimal, auditable SMTP client that opens one connection, checks a list of
recipients with `RCPT TO`, and reports reply codes. It can never issue `DATA`.

**Contract.**

- `config/talent.php` adds a `contacts` key (per D1-A):
  `smtp_helo => env('CONTACTS_SMTP_HELO', 'localhost')`,
  `smtp_mail_from => env('CONTACTS_SMTP_MAIL_FROM', '')` (empty → `<>`),
  `smtp_timeout => 5`. Append `CONTACTS_SMTP_HELO=` and `CONTACTS_SMTP_MAIL_FROM=` to
  `.env.example`. Append them to `.env` only if missing.
- `App\Contacts\Support\SmtpProbeResult` (final readonly): `bool $connected`,
  `bool $sessionOk` (greeting, EHLO/HELO and MAIL FROM all 2xx),
  `?string $failure` (short reason), and `array<string, int|null> $codes`
  (recipient → RCPT reply code, `null` = no reply/timeout).
- `App\Contacts\Support\SmtpProbe::probe(string $host, array $recipients): SmtpProbeResult`:
    - `stream_socket_client("tcp://{$host}:25", …, timeout)`. Failure →
      `connected=false`. No retry.
    - `stream_set_timeout` = `smtp_timeout` per read. Total session deadline 45 s, after
      which it stops and treats remaining recipients as `null`.
    - Reads multi-line replies (continuation `NNN-`).
    - Sequence: greeting `220` → `EHLO {helo}` (on 5xx, `HELO {helo}`) →
      `MAIL FROM:<{from}>` → `RCPT TO:<{addr}>` for each recipient in order → `QUIT` →
      close. No STARTTLS.
    - Every write goes through one private `command()` method guarded by an allow-list
      constant `ALLOWED_VERBS = ['EHLO', 'HELO', 'MAIL FROM', 'RCPT TO', 'QUIT']`. The
      strings `DATA`/`BDAT` must not appear in `app/Contacts`.
    - Catches all socket errors internally and never throws. Always closes the socket in
      `finally`.

**Steps.**

1. Config + env keys.
2. Result DTO + client.

**Done when.**

- `grep -rnE "DATA|BDAT" app/Contacts` returns nothing.
- In tinker, `probe('<Stripe primary MX from Phase 1>', ['careers@stripe.com'])` returns
  a result consistent with Phase 1's verdict (connected with a code, or
  `connected=false`) without throwing.
- Pint and `composer types:check` pass.

**Evidence:** `grep -rnE "DATA|BDAT" app/Contacts` empty; one live probe of `aspmx.l.google.com` for `careers@stripe.com` -> connected, sessionOk, RCPT 250; `composer lint:check` and `composer types:check` pass; `code-reviewer` APPROVED and confirmed by code read that the only write is `command()` behind the verb allow-list (AC11). Deviation accepted: a blank `CONTACTS_SMTP_HELO=` is treated as `localhost`. Non-blocking: a CR/LF recipient aborts the session (safe), an over-long reply line could desync parsing (fails closed), the 45 s deadline can overrun by one read timeout (5 s), and `$host` must always come from DNS, never user input.

**Not in this phase.** Confidence logic, persistence.

---

### Phase 6 — Contact discovery action

Status: DONE
Role: laravel-backend · Depends on: 1, 4, 5 · Covers: AC03, AC04, AC05, AC06 · Size: S
Spec: B.3.3

**Goal.** For a company with a found domain, probe the six aliases in one SMTP
session, store contacts with the correct confidence, and set the company's contact
fields.

**Contract.**

- `App\Contacts\Actions\DiscoverCompanyContacts::handle(Company $company): void`.
- Guards, in spec order:
    1. `domain_status !== Found` → `contact_status = NoDomain`,
       `contact_checked_at = now()`, save, return. No SMTP (AC03).
    2. `contact_status !== Pending` → return.
- `ALIASES = ['careers', 'jobs', 'hr', 'talent', 'recruiting', 'people']`. All of them
  are tried; the loop does not stop at the first success.
- Host: `DnsLookup::mxHosts($domain)[0]`, else the domain itself (A-record fallback).
- Probe address `nonexistent-{Str::lower(Str::random(8))}@{domain}` goes **first** in
  the same `SmtpProbe::probe()` call as the 6 aliases. That makes one connection per
  company, and the probe address is never stored.
- Mapping (D3-A):

    | Situation                                                             | `is_catch_all` | alias 2xx       | alias 4xx / null | alias 5xx                            |
    | --------------------------------------------------------------------- | -------------- | --------------- | ---------------- | ------------------------------------ |
    | not connected, or `sessionOk=false`, or probe code null/4xx (unknown) | `null`         | `mx_only`       | `mx_only`        | `mx_only` (every alias stored, AC06) |
    | probe 2xx (catch-all)                                                 | `true`         | `catch_all`     | skip             | skip                                 |
    | probe 5xx (rejects unknown addresses)                                 | `false`        | `smtp_verified` | skip             | skip                                 |

    In unknown mode, every alias is stored because accept and reject can't be told apart
    (AC06).

- Store with `$company->contacts()->updateOrCreate(['local_part' => $alias], ['email' => "{$alias}@{$domain}", 'confidence' => …, 'checked_at' => now()])`.
- Finally set `is_catch_all`, set `contact_status` to `Found` if at least 1 contact was
  stored this pass and `NotFound` otherwise, and set `contact_checked_at = now()`. One
  `save()` (single `CompanyUpdated`).
- Never throws for DNS/SMTP reasons (both helpers are non-throwing).

**Steps.**

1. Write the action.

**Done when.**

- In tinker on the `stripe` company from Phase 4: contacts and `contact_status` match
  the mapping for Phase 1's verdict (`usable` → `smtp_verified`/`catch_all` rows only
  for accepted aliases; `mx-only fallback` → 6 `mx_only` rows, `is_catch_all` null).
  Rerunning is a no-op.
- A `not_found` company ends `contact_status = no_domain` with no SMTP connection
  (code path read + tinker).
- Pint and `composer types:check` pass.

**Evidence:** one real SMTP session against Stripe's primary MX: `stripe` -> contact_status found, `is_catch_all` true, 6 contacts all `catch_all` (Stripe's server accepted the random probe address and all 6 aliases); second call a no-op; a `not_found` throwaway ended `no_domain` with DNS/SMTP swapped for throwing fakes (neither called), row deleted; `grep -rnE "DATA|BDAT" app/Contacts` empty; `composer lint:check` and `composer types:check` pass; `code-reviewer` APPROVED. Notes: a Found company with an empty domain also takes the `no_domain` path (small safe addition); 3xx probe codes count as unknown mode. Only the catch-all branch was exercised live, the `smtp_verified` and `mx_only` branches are verified by code read.

**Not in this phase.** Queued orchestration, locking, admin.

---

### Phase 7 — Orchestration job + wiring into the collection flow

Status: DONE
Role: laravel-backend · Depends on: 3, 6 · Covers: AC02, AC12 · Size: M
Spec: B.3.4, B.4

**Goal.** Every newly inserted posting queues discovery on `contacts`, the dev worker
consumes that queue, and the collection flow's own behavior is byte-for-byte the same.

**Contract.**

- `App\Contacts\Actions\RunCompanyDiscovery::handle(Company $company): void`, the shared
  sequence also used by Phase 8's retry:
  `Cache::lock("contacts:company:{$company->id}", 90)->get()`. If the lock isn't
  acquired, return, because another worker owns this company (no parallel probes). Then
  `$company->refresh()`, `ResolveCompanyDomain`, `DiscoverCompanyContacts`. Catch
  `Throwable` → `Log::warning('Contact discovery failed', ['company_id' => …, 'exception' => $e])`.
  Never rethrow. Release the lock in `finally`.
- `App\Contacts\Jobs\DiscoverContactsForPosting implements ShouldQueue` (`Queueable`):
  `__construct(public int $jobPostingId)` calls `$this->onQueue('contacts')`.
  `$tries = 1`, `$timeout = 75` (< `retry_after` 90).
  `handle()`: load the posting (missing → return), call `ResolveCompanyForPosting`, then
  call `RunCompanyDiscovery` only if `$company->wasRecentlyCreated` or `domain_status`
  or `contact_status` is `pending`. The whole body is wrapped in a `Throwable` catch
  that logs and never rethrows.
- `FetchJobsFromSource` (smallest change):
    - In the chunk loop, keep the new external ids already computed by the existing
      `array_diff` in a `$newExternalIds` array. Don't change the `$jobsNew` math.
    - After the existing `JobPostingsUpdated` dispatch (success path only), dispatch in a
      separate `try { … } catch (Throwable $e) { report($e); }`. Chunks of 200 of
      `JobPosting::where('source_id', …)->whereIn('external_id', $chunk)->pluck('id')`,
      then `DiscoverContactsForPosting::dispatch($id)` each.
    - No changes to counters, statuses, `MUTABLE_COLUMNS`, upsert rows, `failed()`, or
      realtime events.
- `AppServiceProvider::boot()`: the dev worker becomes
  `queue:work database --queue=collection,contacts,default` (same `'queue'` name).
  Collection keeps priority.

**Steps.**

1. Shared action → job → `FetchJobsFromSource` change → dev worker queues.

**Done when.**

- `git diff app/Jobs/FetchJobsFromSource.php` shows only the id capture and the
  post-success dispatch block.
- In tinker, `DiscoverContactsForPosting::dispatchSync(<an unresolved posting id>)` sets
  its `company_id` and brings its company out of `pending`.
- Pint, `composer types:check` and `composer test` pass.

**Evidence:** `FetchJobsFromSource` diff is only the `$newExternalIds` capture (the `$jobsNew` math is unchanged) plus a post-success dispatch block in its own try/catch that only `report()`s; dev worker now `queue:work database --queue=collection,contacts,default`; tinker `dispatchSync` on a Stripe posting set `company_id`, reused the one `stripe` company and skipped discovery (contacts' `checked_at` unchanged, no SMTP); job queue is `contacts`, `$timeout` 75 < `retry_after` 90; `composer lint:check` passes; `composer types:check` passes (PHPStan intermittently crashes at 128M; passes at `--memory-limit=1G`); `code-reviewer` APPROVED. No collection run was triggered (Phase 11 does that).
**Known issue, not caused by this feature:** `composer test` (and so `composer ci:check`) fails. The 2 example tests run on in-memory sqlite (`phpunit.xml`), and the committed migration `2026_09_23_003047_change_notifications_data_to_json.php` (commit `e06080c`) uses Postgres-only SQL (`ALTER COLUMN data TYPE json USING data::json`). Fixing it means editing an old migration or the test DB config, which is outside this plan. Needs an owner decision before Phase 11's gate can go green.

**Not in this phase.** Backfill command, retry action, admin UI.

---

### Phase 8 — Backfill command + retry backend

Status: DONE
Role: laravel-backend · Depends on: 7 · Covers: AC07, AC08 (backend) · Size: S
Spec: B.6, B.7 ("Retry discovery")

**Goal.** Existing postings can be backfilled idempotently, and a single company can be
re-discovered on demand.

**Contract.**

- `App\Console\Commands\BackfillContacts`, signature `contacts:backfill`, description
  `Queue contact discovery for job postings without a company`:
  `JobPosting::query()->whereNull('company_id')->select('id')->chunkById(200, …)` →
  `DiscoverContactsForPosting::dispatch($id)`. It prints
  `Queued N postings for contact discovery.` A second run with a drained queue queues 0.
  It never runs automatically (not in migrations, not scheduled).
- `App\Contacts\Jobs\DiscoverContactsForCompany implements ShouldQueue`:
  `__construct(public int $companyId)` → `onQueue('contacts')`, `$tries = 1`,
  `$timeout = 75`. `handle()` loads the company (missing → return) and calls
  `RunCompanyDiscovery`. It does not go through the posting-scoped job (B.7).
- `App\Contacts\Actions\RetryCompanyDiscovery::handle(Company $company): void`: in a DB
  transaction, delete `$company->contacts()` (D2-A), then set
  `domain_status = Pending`, `contact_status = Pending`, `is_catch_all = null`,
  `domain = null`, and `save()` (emits `CompanyUpdated`). After commit,
  `DiscoverContactsForCompany::dispatch($company->id)`.

**Steps.**

1. Company job → retry action → command.

**Done when.**

- `php artisan list contacts` shows `contacts:backfill`.
- In tinker, running `RetryCompanyDiscovery` on one company and then
  `queue:work --queue=contacts --stop-when-empty` shows the company going `pending` and
  then resolved again, with no duplicate contacts.
- Pint and `composer types:check` pass.

**Evidence:** `php artisan list contacts` shows `contacts:backfill`; the backfill was NOT run (Phase 11 does that); tinker retry on `stripe` reset it to pending / no domain / no contacts and queued exactly one `contacts` job, then re-discovery (one real SMTP session, that job run synchronously) restored found / catch-all / 6 contacts with no duplicates; queue left empty; `grep -rnE "DATA|BDAT" app/Contacts` empty; `composer lint:check` and `composer types:check` pass; `code-reviewer` APPROVED.
Non-blocking, for the owner: (1) running `contacts:backfill` twice before the queue drains re-queues still-unresolved postings, which is harmless (the jobs are idempotent) but inflates the backlog, so run it once and let the queue drain; (2) if "Retry discovery" is clicked while a probe already holds that company's lock, the queued retry skips silently and the company stays Pending with its contacts deleted until another trigger (a follow-up could re-dispatch with a delay).

**Not in this phase.** Running the full backfill (Phase 11), the Filament action
(Phase 9).

---

### Phase 9 — Companies admin resource

Status: DONE
Role: filament-admin · Depends on: 2, 8 · Covers: AC08, AC10, AC06 (visible badge) · Size: M
Spec: B.7 (Companies), B.5

**Goal.** Admins can list companies with status badges, retry one, and open a live
view page with its contacts.

**Contract.** Files: `app/Filament/Resources/Companies/CompanyResource.php`,
`Pages/ListCompanies.php`, `Pages/ViewCompany.php`, `Tables/CompaniesTable.php`,
`Schemas/CompanyInfolist.php`, `RelationManagers/ContactsRelationManager.php`,
`resources/views/filament/companies/realtime-listener.blade.php`.

- Resource: model `Company`, navigation group `Collection`, icon
  `Heroicon::OutlinedBuildingOffice2`, `recordTitleAttribute` `name`,
  `canCreate(): false`, pages `index` + `view` only (no edit or delete anywhere),
  relations `[ContactsRelationManager::class]`.
- Table: columns `name` (searchable), `domain` (searchable, placeholder `—`),
  `domain_status` badge, `contact_status` badge, `is_catch_all` IconColumn boolean
  labelled `Catch-all` (renders `—` when null), `contacts_count` via
  `->counts('contacts')` labelled `Contacts`, `domain_checked_at` dateTime
  (placeholder `—`), `contact_checked_at` dateTime sortable (placeholder `—`).
  Default sort `contact_checked_at desc nulls first` (explicit `orderByRaw`).
  Filters: `SelectFilter` `domain_status` (`DomainStatus` options), `contact_status`
  (`ContactStatus` options).
  Record actions: `ViewAction`, plus `Action::make('retryDiscovery')` with label
  `Retry discovery`, `requiresConfirmation()`, modal description
  `Resets this company's domain and contact results and runs discovery again. Existing contacts are removed.`,
  calling `RetryCompanyDiscovery`, then a success notification
  `Discovery queued for {name}`.
  `->socket(channel: 'companies', event: 'CompanyUpdated')`. No `->poll()`.
- Infolist: name, normalized_name, domain (`—`), domain_status badge,
  domain_checked_at, contact_status badge, contact_checked_at, is_catch_all
  (boolean icon, `—` when null), created_at.
- `ViewCompany::content()` follows the `ViewCollectionRun` pattern: listener view +
  infolist + relation managers. Listener: `channel="companies" event="CompanyUpdated"
callback="$wire.$refresh()"`.
- `ContactsRelationManager` (`contacts`, title `Contacts`): read-only, columns `email`,
  `local_part` (label `Alias`), `confidence` badge, `checked_at` dateTime. Header,
  record and toolbar actions are all empty.
  `->socket(channel: 'companies', event: 'CompanyUpdated')`.

**Steps.**

1. Resource + pages + table + infolist + RM + listener view.

**Done when.**

- `/admin/companies` lists companies with badges. The view page shows contacts. Retry
  queues and the row refreshes live (check with `php artisan route:list --path=admin/companies`
  and Boost `browser-logs` has no errors).
- `grep -rnE "->poll\(|wire:poll|pollingInterval" app resources` returns nothing.
- Pint, `composer types:check`, `composer test` pass.

**Evidence:** `php artisan route:list --path=admin/companies` shows index + view; poll grep (`->poll(`, `wire:poll`, `pollingInterval` over `app` and `resources`) empty; headless Livewire mount of `ListCompanies` and `ViewCompany` rendered without errors; `composer lint:check` and `composer types:check` pass; `code-reviewer` APPROVED (no edit/delete path in the UI, `defaultSort` closure with `NULLS FIRST` valid on Postgres, relation manager refreshes because both `RetryCompanyDiscovery` and `DiscoverCompanyContacts` end with a `Company` save).
**Not verified visually:** no browser check of the badges, the catch-all icon for null, or the live refresh; that is on Phase 11's owner checklist.
Non-blocking: the catch-all icon/colour closures are duplicated between `CompaniesTable` and `CompanyInfolist`; `RetryCompanyDiscovery` saves inside its transaction, so the realtime signal can fire just before commit (a refreshing client could briefly see the old state).

**Not in this phase.** Job postings changes (Phase 10).

---

### Phase 10 — Job postings: Contact column + Contact status filter

Status: DONE
Role: filament-admin · Depends on: 2, 3 · Covers: AC09 · Size: S
Spec: B.7 (Job postings)

**Goal.** Each posting row shows its company's contact status and can be filtered by
it, with everything else unchanged.

**Contract.** Only `app/Filament/Resources/JobPostings/Tables/JobPostingsTable.php`.

- Eager load becomes `->with(['collectionRun', 'source', 'company'])`.
- New column after `Company`: `TextColumn::make('contact_status')`, label `Contact`,
  `->state(fn (JobPosting $r) => $r->company?->contact_status ?? ContactStatus::Pending)`,
  `->badge()`. The enum supplies label and color: gray Pending, gray No domain, green
  Found, red Not found.
- New filter: `SelectFilter::make('contact_status')`, label `Contact status`, options
  `ContactStatus`, query
  `whereHas('company', fn ($q) => $q->where('contact_status', $value))`. For `pending`,
  it also matches `company_id IS NULL` so the filter agrees with the badge.
- Grouping by run, existing columns, filters (run, source, adapter, today's runs),
  View/Open posting actions, and the `job_postings` socket all stay exactly as they
  are.

**Steps.**

1. Edit the table class.

**Done when.**

- `/admin/job-postings` shows the Contact badge, and `Contact status = Found` narrows
  rows. Combined with "Today's runs only", it answers AC09.
- Pint and `composer types:check` pass.

**Evidence:** diff limited to the eager load, the `Contact` column, the `Contact status` filter and one import; headless Livewire mount of `ListJobPostings` OK: unfiltered 1045, `contact_status` = found -> 2, = pending -> 1043 (pending also matches `company_id IS NULL`, agreeing with the badge); poll grep empty; `composer lint:check` and `composer types:check` pass; `code-reviewer` APPROVED (the `orWhereHas` is grouped in its own closure so it can't leak past other filters; no N+1). Deviation accepted: the badge state is `company_id === null ? Pending : company->contact_status` because PHPStan rejected the nullsafe form (`nullsafe.neverNull`); equivalent at runtime.

**Not in this phase.** Live refresh of the Contact badge when a company resolves. That
isn't required by any AC; noted as an idea.

---

### Phase 11 — Verification and report

Status: BLOCKED (gate: `composer test` and `vp check` fail for reasons outside this feature; needs an owner decision)
Role: laravel-backend (+ qa-tester for the gate) · Depends on: 1–10 · Covers: AC01–AC12 · Size: M
Spec: B.8, B.9

**Goal.** Prove every AC against the running app and hand the owner a checklist.

**Steps.**

1. **Gate:** `composer ci:check` passes. `git diff --stat composer.json composer.lock package.json yarn.lock`
   is empty, so no dependency changed.
2. **AC01:** restate Phase 1's verdict and date.
3. **Backfill smoke (B.9.2, AC07):** `php artisan contacts:backfill`, then
   `php artisan queue:work database --queue=contacts --stop-when-empty`. Report counts:
   companies, domain found/not_found, contact found/not_found/no_domain, catch-all
   count, contacts by confidence, and postings with null `company_id` (must be 0).
   Run both again and confirm identical counts and `Queued 0 postings`.
4. **New run (B.9.3, AC02/AC12):** trigger "Collect jobs now" (owner click, or tinker
   `app(StartCollectionRun::class)->handle(<admin>)`), then
   `queue:work database --queue=collection,contacts,default --stop-when-empty`. Confirm:
   the run's status and counters are the same kind of result as before (jobs_fetched,
   jobs_new, sources_succeeded), new postings have `company_id`, and no company is left
   `pending`.
5. **Code reads:** `SmtpProbe` issues only allow-listed verbs, and
   `grep -rnE "DATA|BDAT" app/Contacts` is empty (AC11). The AC03 guard runs before any
   SMTP call. The mapping matches Phase 6 (AC04/AC05/AC06). If Phase 1 was
   `mx-only fallback`, AC04/AC05 can only be verified by reading code; say so plainly.
6. **Forbidden patterns:** `grep -rnE "->poll\(|wire:poll|pollingInterval" app resources`
   is empty. `/app` panel files are unchanged (`git diff --stat app/Filament/App`).
   `FetchJobsFromSource` diff is limited to Phase 7's contract.
7. **AC12 spot-check** (job-collection-mvp): AC06 (collect confirmation, one run,
   blocked while in progress), AC07 (no duplicate postings; `collection_run_id` kept),
   AC08 (failing source → partial), AC09 (counters + live notification), AC10 (live
   tables, no polling), AC11 (grouping + filters), AC12 ("Mark as failed" unblocks).
8. **Observations to report, not fix:** duplicate companies from name variants, and
   doubtful domain guesses (`linear.com`, `lemonio.com`, `ateam.com`).
9. `code-reviewer` over the full feature diff against AC01–AC12.
10. **Owner manual checklist (B.9.4):** with `composer dev` running, open Companies and
    see the badges. Open one company and see its contacts with confidence badges. Click
    "Retry discovery" on one and watch it go Pending, then resolve, without reloading.
    Open Job postings and filter `Contact status = Found`, with "Today's runs only"
    on.

**Done when.**

- The report lists each AC with its evidence, the backfill counts from both runs, the
  new-run result, the observations, and the checklist. Nothing is committed.

**Evidence (2026-09-23):**

- **Backfill smoke (B.9.2, AC07):** `contacts:backfill` queued 1043 postings; the `contacts` queue drained in about 40 s with 0 failed jobs. Result: 15 companies (domain: 14 found, 1 not_found), contact_status 10 found / 4 not_found / 1 no_domain, `is_catch_all` true 7 / false 4 / null 4, 60 contacts (42 `catch_all`, 18 `mx_only`, 0 `smtp_verified`), 0 postings without `company_id`, 0 duplicate companies, 0 duplicate contacts. A second `contacts:backfill` queued 0 and every count was identical.
- **New run (B.9.3, AC02/AC12):** run #2 via `StartCollectionRun` as the admin: completed 4/4 sources, 1050 fetched / 5 new (Stripe 688/5, the rest 0 new), exactly 5 discovery jobs dispatched (one per new posting), all 1050 postings linked, 0 companies left pending. Run #1's 1045 postings still belong to run #1, only the 5 new ones belong to run #2, all 1050 have `last_seen_run_id = 2`, no duplicate `(source_id, external_id)`.
- **Code reads:** `SmtpProbe::command()` is the only socket write, behind the verb allow-list, and rejects CR/LF (AC11); `grep -rnE "DATA|BDAT" app/Contacts` empty; AC03 guard runs before any DNS/SMTP call. Only the catch-all and no-domain branches were exercised live (Stripe, Palantir etc.). The `smtp_verified` branch (the probe gets a 5xx and an alias a 2xx) had no live example; it is verified by code read only.
- **Forbidden patterns:** poll grep empty; `git diff --stat` on `composer.json`, `composer.lock`, `package.json`, `yarn.lock` empty; `app/Filament/App` untouched; `FetchJobsFromSource` diff limited to Phase 7's contract.
- **Final review:** `code-reviewer` over the full diff APPROVED, AC02–AC12 PASS, no blocking findings.
- **Passing:** `composer lint:check`, `composer types:check` (0 errors; PHPStan intermittently crashes at the 128M default memory, passes at `--memory-limit=1G`), `yarn run types:check`.
- **Gate NOT green (all pre-existing, none caused by this feature):**
    1. `composer test` -> the 2 example tests error: committed migration `2026_09_23_003047_change_notifications_data_to_json.php` (commit `e06080c`) runs Postgres-only SQL, `phpunit.xml` uses in-memory sqlite.
    2. `yarn run check` (`vp check`) -> formatting issues in 22 files, all docs/config (`.claude/skills/**`, `AGENTS.md`, `.mcp.json`, `boost.json`, `docs/features/job-collection-mvp/spec.md`), no code files. This feature's `plan.md` was formatted so it no longer counts. Note `yarn check` alone is Yarn's built-in integrity check, not the script; use `yarn run check`.
    3. So `composer ci:check` fails. Options for the owner: (A) fix the migration to be driver-aware and run `vp check --fix` on those docs (touches the prior feature's spec and skills, so it should be the owner's call), (B) point the tests at a Postgres test database, (C) accept the gate as-is for this feature.
- **Observations, not fixed (B.10):** duplicate companies from name variants were not observed in this data (15 distinct names -> 15 companies). Doubtful domain guesses: `linear.com` resolved and reads as catch-all (Linear's product is on `linear.app`), `lemonio.com`, `ateam.com`, `imerittechnology.com`, `uniodigital.com` resolved but their servers rejected every alias, `credit wellness` has no `.com` guess. 3 companies (KoboToolbox, hey contact heroes, IAPWE) ended `mx_only` (probe inconclusive), so those 18 contacts are unverified. One row in `failed_jobs`: Filament's `DatabaseNotificationsSent` broadcast for run #2's "finished" notification failed with `cURL error 7` because Reverb was not running on `localhost:8080` in this shell; that is the existing job-collection-mvp notification, not this feature. The row was left in place.
- **Non-blocking review notes:** (1) a "Retry discovery" clicked while a probe holds the company lock can be silently lost (narrow, about 45 s window); (2) the Job postings Contact badge refreshes live only on `JobPostingsUpdated`/reload, not on `CompanyUpdated`, since AC10 only names Companies; (3) a tooltip on the catch-all icon (null vs false) would help.
- **Owner checklist (B.9.4)**, with `composer dev` running (so Reverb is up): open Companies and see the badges and the catch-all icon; open one company (for example Stripe) and see its 6 contacts with their confidence badges; click "Retry discovery" on one company you don't mind probing again and watch it go Pending and then resolve without a reload; open Job postings, filter `Contact status = Found`, combine with "Today's runs only".
