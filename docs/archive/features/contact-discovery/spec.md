# talent-labs — Contact Discovery MVP (spec addition)

> **Prompt to give the agent:** "Read this file end to end. This is a new
> feature on top of the already-DONE `job-collection-mvp`. Copy Part B into
> `docs/features/contact-discovery-mvp/spec.md`, then execute it with the
> `execute-feature` skill. The harness (`CLAUDE.md`, agents, skills, no-git
> rule, no-destructive-DB rule) already exists in this repo — reuse it, don't
> recreate it. Don't touch `job-collection-mvp`'s spec or already-DONE work
> except where this file explicitly says to. Ask before any new dependency."

## Part 0 — What changed since job-collection-mvp, and why

The MVP proved collection + listing + realtime. It does **not** answer the
real product question yet: **can we actually reach this company by email?**
Greenhouse/Lever/Ashby postings point at the ATS's own apply flow, not a
company inbox — there's no email in the payload, never was, and never will
be, because these companies run a structured, form-based hiring process on
purpose. Email outreach is a mismatch for that kind of company.

This round exists to answer, per company, "do we have a working email
channel for this?" — cheaply, for every collected job, without yet asking
who the recipient is a person.

**Deliberate simplification vs. the original outreach vision:** no LinkedIn
scraping in this round, so there's no recruiter _name_ to build
`firstname.lastname@domain` from. The only pattern that works without a name
is a fixed set of **generic recruiting aliases** (`careers@`, `jobs@`,
`hr@`, `talent@`, `recruiting@`, `people@`) tried against the resolved
company domain. This is weaker than name-based guessing but is what's
achievable without adding a scraping/API dependency now. Named-contact
discovery (LinkedIn, Hunter/Apollo, etc.) is a later round, not this one.

**Consequence for the "hot/applicable" framing:** a company where none of
these aliases resolve is a signal, not just a gap — it likely means this
company isn't a good outreach target regardless of technical effort. This
round surfaces that signal in the admin (and, implicitly, argues for
reconsidering which _sources_ the collector points at — ATS-listed
companies vs. companies without a formal ATS — but that's a source-curation
decision for the owner to make after seeing the data, not something this
spec re-architects).

**Explicitly out of scope this round** (confirm/adjust nothing else):
client-facing filters, email templates, CV adaptation, actual sending
(manual or automatic), LinkedIn or any named-contact discovery, third-party
contact-finding APIs (Hunter/Apollo/Clearbit), user-app UI changes of any
kind. The `/app` panel is untouched.

## Part 0.1 — Known technical risk to validate early

**Outbound SMTP (port 25) is frequently blocked** by residential ISPs, home
routers and many cloud providers, independent of any code correctness. The
verification step (B.3) needs an actual `RCPT TO` handshake to the target
MX host. **First task of this feature is a standalone connectivity check**
(open a raw TCP connection to port 25 of a known-good MX, e.g. one of the
company domains discovered in testing) run directly by the agent and
reported to the owner _before_ building the full pipeline around it. If
port 25 is blocked from this machine, the SMTP verification step degrades to
"MX exists" confidence only (B.3.3 handles this) — don't discover this
after building everything on top of it.

## Part B — Product spec: contact discovery

### B.1 Goal

For every company behind a collected job posting: resolve a domain, then
try a small set of generic recruiting email aliases against it, verify what
can be verified, and record a confidence level. Surface this per company
and per posting in the admin. Backfill it for everything already collected,
and run it automatically for every future collection run.

No email is sent. No template exists. No client sees this data yet.

### B.2 Data model

#### `companies`

| column               | type                          | notes                                                                                                            |
| -------------------- | ----------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `id`                 | pk                            |                                                                                                                  |
| `name`               | string                        | display name, first `job_postings.company_name` seen                                                             |
| `normalized_name`    | string                        | lowercase, trimmed, legal-suffix-stripped (Inc, LLC, Ltd, GmbH, S.A., Co) — the actual matching key              |
| `domain`             | string, nullable              | resolved domain, no scheme (`stripe.com`)                                                                        |
| `domain_status`      | string (enum `DomainStatus`)  | `pending`, `found`, `not_found`                                                                                  |
| `domain_checked_at`  | timestamp, nullable           |                                                                                                                  |
| `contact_status`     | string (enum `ContactStatus`) | `pending`, `no_domain`, `found`, `not_found`                                                                     |
| `contact_checked_at` | timestamp, nullable           |                                                                                                                  |
| `is_catch_all`       | boolean, nullable             | null = unknown/not tested, true = the mail server accepts any address (lowers confidence on every contact found) |
| timestamps           |                               |                                                                                                                  |

Unique: `normalized_name`.

#### `contacts`

| column       | type                              | notes                                                                                                                                                                             |
| ------------ | --------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`         | pk                                |                                                                                                                                                                                   |
| `company_id` | fk, cascadeOnDelete               |                                                                                                                                                                                   |
| `email`      | string                            | e.g. `careers@stripe.com`                                                                                                                                                         |
| `local_part` | string                            | the alias tried (`careers`, `jobs`, …), for dedup/analysis                                                                                                                        |
| `confidence` | string (enum `ContactConfidence`) | `smtp_verified` (RCPT accepted, not catch-all), `mx_only` (domain has MX, RCPT unavailable/inconclusive — see B.1), `catch_all` (RCPT accepted but the domain accepts everything) |
| `checked_at` | timestamp                         |                                                                                                                                                                                   |
| timestamps   |                                   |                                                                                                                                                                                   |

Unique: `(company_id, local_part)`.

#### `job_postings` (add one column)

| column       | type                                 | notes                                                      |
| ------------ | ------------------------------------ | ---------------------------------------------------------- |
| `company_id` | fk companies, nullable, nullOnDelete | resolved async; null until discovery runs for that company |

Index: `company_id`.

### B.3 Discovery pipeline

#### B.3.1 Company resolution

`App\Contacts\Actions\ResolveCompanyForPosting::handle(JobPosting $posting): Company`

- Normalize `$posting->company_name`: lowercase, trim, strip a fixed list
  of legal suffixes (`inc`, `llc`, `ltd`, `gmbh`, `sa`, `co`, `corp`,
  `corporation`, `company`, `plc`), strip punctuation, collapse whitespace.
- `Company::firstOrCreate(['normalized_name' => $normalized], ['name' => $posting->company_name])`.
- Set `$posting->company_id` and save (via a query-builder update, not a
  full model `save()`, to avoid re-triggering unrelated model events — but
  it must still be reachable for the realtime dispatch in B.5).

Called once per posting, right after `FetchJobsFromSource` upserts it
(B.4/B.5 wire this in), and once per existing posting during the backfill
(B.6).

#### B.3.2 Domain resolution

`App\Contacts\Actions\ResolveCompanyDomain::handle(Company $company): void`

- Skip if `domain_status !== 'pending'` (idempotent — don't re-guess a
  company we already resolved or already know has no domain, unless the
  owner explicitly retries it, B.7).
- Candidate: `slug($normalized_name) . '.com'` (strip remaining spaces —
  `"open ai"` → `openai.com`). This is intentionally a single, simple
  candidate — not a list of TLD variants — because MVP scope is "does the
  obvious guess work", not exhaustive resolution.
- Check via DNS: `dns_get_mx($candidate, $mxhosts)` (or Laravel's DNS
  helper if one exists in this Laravel version — check via Boost
  `search-docs` first). If it returns MX records, or if it returns no MX
  but an `A`/`AAAA` record exists for the domain (some small companies
  route mail through their web host without a distinct MX — treat this as
  weaker but still "found"), set `domain = candidate`,
  `domain_status = found`. Otherwise `domain_status = not_found`.
- Always set `domain_checked_at = now()`.

#### B.3.3 Contact discovery

`App\Contacts\Actions\DiscoverCompanyContacts::handle(Company $company): void`

- Skip if `domain_status !== 'found'` → set `contact_status = no_domain`,
  `contact_checked_at = now()`, return.
- Skip if `contact_status !== 'pending'` (idempotent, same as B.3.2).
- Aliases to try, in order, stop-on-first-success is **not** used — try
  **all** of them and keep every one that resolves (a company might have
  both `careers@` and `jobs@` working): `careers`, `jobs`, `hr`,
  `talent`, `recruiting`, `people`.
- **Catch-all probe first**: try one random, almost-certainly-invalid local
  part (e.g. `nonexistent-{random 8 chars}`) via SMTP `RCPT TO` against the
  domain's primary MX. If that "invalid" address is accepted, set
  `company.is_catch_all = true` for this company and mark every subsequent
  alias check on this domain `catch_all` confidence instead of
  `smtp_verified`, since a positive RCPT means nothing on a catch-all
  server. If the probe itself fails to connect (port 25 blocked — B.1),
  set `is_catch_all = null` (unknown) and every alias gets `mx_only`
  confidence (we only know the domain accepts mail somehow, not that a
  specific address exists).
- For each alias: build `{alias}@{domain}`, do the SMTP `HELO`/`MAIL
FROM`/`RCPT TO` handshake against the domain's lowest-priority MX host,
  short timeout (~5s), one attempt (no retry storm against someone else's
  mail server). Record a `Contact` row with the appropriate confidence
  (per the catch-all/mx_only logic above) for every alias that gets a
  positive or inconclusive-but-plausible result; skip aliases that get an
  explicit rejection (`550`/`551`/`553` etc.).
- `contact_status`: `found` if at least one `Contact` row was created,
  `not_found` otherwise. `contact_checked_at = now()`.
- Never send an actual email (`MAIL FROM`/`RCPT TO` only — no `DATA`, no
  message body, ever).
- Be a polite client: one connection per company, don't parallelize probes
  against the same domain, respect the timeout.

#### B.3.4 Orchestration

`App\Contacts\Jobs\DiscoverContactsForPosting` (queued, on queue
`contacts`): calls B.3.1, then — only if the company was just created or is
still `pending` — B.3.2 then B.3.3 in sequence. `$tries = 1`; catch and log
any exception per company without rethrowing (one bad domain shouldn't
fail the batch); a DNS or SMTP failure resolves to `not_found`/`no_domain`,
never an unhandled exception.

### B.4 Wiring into the existing collection flow

In `FetchJobsFromSource` (job-collection-mvp), after the upsert of new/
updated postings for that source run, dispatch
`DiscoverContactsForPosting` on queue `contacts` for each **newly inserted**
posting only (not on every update — a re-seen posting's company was
already resolved on first sight). Read the existing `FetchJobsFromSource`
code before changing it; make the smallest change that adds this dispatch
without altering B.5/B.10's existing behavior, counters, or realtime
events from job-collection-mvp.

### B.5 Realtime

Reuse the existing driver (already configured on both panels).

| Channel     | Event            | Dispatched when         |
| ----------- | ---------------- | ----------------------- |
| `companies` | `CompanyUpdated` | `Company` saved/deleted |

`Company` model `booted()`: same pattern as `Source`/`CollectionRun` from
job-collection-mvp (reuse the shared `BroadcastsRealtime` trait already in
this codebase — don't write a new one).

### B.6 Backfill (existing ~1000+ postings)

A one-off, idempotent, re-runnable console command:
`php artisan contacts:backfill` — for every `job_postings` row with
`company_id IS NULL`, dispatch `DiscoverContactsForPosting` (chunked query,
`->chunkById(200, ...)` to avoid loading everything into memory). Safe to
run more than once (nothing left to backfill the second time). This is a
**console command run once by the owner after this feature ships**, not an
automatic migration step and not something that runs on every deploy.

### B.7 Admin panel additions

Navigation group **Collection** (existing).

**Companies** (`CompanyResource`, new, read-only except one action)

- Table: name, domain, domain_status (badge), contact_status (badge),
  `is_catch_all` (icon, `—` when null), contacts count, domain_checked_at,
  contact_checked_at. Filters: domain_status, contact_status. Default sort:
  `contact_checked_at` desc (nulls first, so unresolved companies surface
  first).
- Row action **"Retry discovery"**: resets `domain_status` and
  `contact_status` to `pending` (and `is_catch_all` to null) and
  re-dispatches `DiscoverContactsForPosting`-equivalent logic for that one
  company (call B.3.2/B.3.3 directly, not through the posting-scoped job).
  `requiresConfirmation()`.
- View page: infolist with the company fields plus a `contacts` relation
  manager (email, local_part, confidence badge, checked_at) — read-only.
- `->socket(channel: 'companies', event: 'CompanyUpdated')` on the table.

**Job postings** (`JobPostingResource`, existing — extend, don't rewrite)

- Add a column: **Contact** — derived from `posting.company.contact_status`
  (badge: gray "pending", gray "no domain", green "found", red "not
  found"). Eager-load `company` alongside the existing `collectionRun`/
  `source` eager loads.
- Add a filter: **Contact status** (select over `ContactStatus`, via
  `whereHas('company', ...)`).
- Everything else in this resource (grouping by run, existing filters,
  view/open actions, realtime) stays exactly as job-collection-mvp built
  it.

### B.8 Acceptance criteria

- **AC01** — The port-25 connectivity check (Part 0.1) ran and its result
  (usable / MX-only fallback) is recorded in the phase evidence before
  B.3.3 was built around it.
- **AC02** — Every newly-collected posting gets its company resolved
  (`company_id` set) without slowing down or breaking the existing
  collection flow's counters/realtime from job-collection-mvp.
- **AC03** — For a company whose guessed domain has no MX/A record,
  `domain_status = not_found` and `contact_status = no_domain`; no SMTP
  probe is attempted.
- **AC04** — For a company with a resolved domain and a catch-all mail
  server, every discovered alias is stored with `confidence = catch_all`,
  and `companies.is_catch_all = true`.
- **AC05** — For a company with a resolved domain and a non-catch-all
  server, only aliases that get a positive RCPT are stored, with
  `confidence = smtp_verified`.
- **AC06** — If port 25 is unreachable from this machine, contacts still
  get created (one per alias, since we can't distinguish accept/reject) at
  `confidence = mx_only`, and this degraded mode is visible in the admin
  (not silently indistinguishable from a verified result — the confidence
  badge must differ).
- **AC07** — `php artisan contacts:backfill` resolves companies/contacts
  for the pre-existing ~1000+ postings; running it twice creates no
  duplicate `Company` or `Contact` rows.
- **AC08** — The Companies resource lists every company with correct
  status badges, and "Retry discovery" re-resolves a single company
  on demand.
- **AC09** — Job Postings admin table shows the Contact status per row and
  can filter by it; a filament admin can visually pick out which of today's
  postings already have a usable contact.
- **AC10** — Companies table and its view page update live over the
  realtime driver (no `->poll()`).
- **AC11** — No email was ever sent by this feature (`DATA` is never
  called) — confirm by reading the SMTP client code, not just by absence
  of a bug report.
- **AC12** — job-collection-mvp's existing acceptance criteria still hold
  (spot-check AC06–AC12 from that spec) — this feature only adds a
  side-effect dispatch and a nullable column, it doesn't change the
  collection flow's own behavior.

### B.9 Verification

1. Part 0.1's connectivity check, reported first.
2. Backfill smoke test: run `contacts:backfill`, report counts (companies
   created, domains found vs not_found, contacts found vs not_found,
   catch-all count), run it again, confirm unchanged counts.
3. Trigger one new "Collect jobs now" run (reuse job-collection-mvp's
   existing flow) and confirm new postings get `company_id` set and, once
   the `contacts` queue drains, a `contact_status` other than `pending`.
4. Manual admin checklist for the owner: open Companies, see the resolved
   list with badges; open one company's view page and see its contacts;
   click "Retry discovery" on one and watch it live-refresh; open Job
   Postings and filter by "Contact status = found".

### B.10 Out of scope (do not build)

Everything listed in Part 0 plus: multiple domain-guess candidates/TLDs,
WHOIS or "About Us" page scraping for the domain, catch-all-domain
heuristics beyond the single probe in B.3.3, retry/backoff scheduling for
`not_found` companies (retry is manual, via B.7's action, in this round),
any UI in the `/app` panel, exposing contact emails anywhere outside
`/admin`, rate-limiting/politeness beyond "one connection per company,
don't parallelize", contacts.csv export, deleting/merging duplicate
companies caused by name variants (`"Stripe"` vs `"Stripe, Inc."` not
normalizing to the same row) — flag it if observed, don't silently fix it
with more normalization rules than B.3.1 lists.
