# Contact Quality

> Body: current behaviour, read from the plan's implementation on 2026-10-31
> (folded from `## Pending changes` after the last phase; reconcile with the
> code via `/update-feature-spec contact-quality`). Company domain/alias
> discovery itself is described in `docs/features/job-mining/spec.md` §2.8 and
> is not repeated here.
> `## Pending changes` holds what is still open. Former open decisions O1-O7 are
> resolved under `## Decisions`.
>
> Depends on: `production-hardening` P7 (outbound guard) for Stage 2.
> Job-mining's "Out of scope" already says named-contact discovery would get
> its own spec: this is it.

## 1. What it is

Who an application is sent to. Goal (owner): stronger applications that
reach someone who reads them and, when possible, someone with hiring power —
cheaply or for free.

## 2. Current behaviour (2026-10-31)

### 2.1 Contacts and tiers

- A `Contact` has a `source` (`ContactSource`: `alias_probe`, `posting`,
  `named`), a nullable `job_posting_id` (the posting that published it) and
  nullable `person_name`/`person_title` (named contacts only). The **tier** is
  derived by `Contact::tier()` and never stored:
    - **A** — `source = posting` with a posting, confidence not `rejected`
      (`catch_all` and inconclusive allowed, because the company published it);
    - **B** — `source = named` and `smtp_verified`;
    - **C** — `source = alias_probe` and `smtp_verified` (the role aliases
      `careers`, `jobs`, `hr`, `talent`, `recruiting`, `people`, job-mining §2.8).
- Tiers B and C also need a company with `outreach_status = verified`; only tier A
  may be `catch_all` or inconclusive, and only `rejected` excludes it. Unverified
  guesses are never sendable.
- `ContactConfidence`: `smtp_verified`, `catch_all`, `mx_only` (no longer
  produced), `rejected`.

### 2.2 Emails published in the posting (tier A)

- One shared extractor, `PublishedEmailExtractor`, runs for every source over
  `description_html`/`description_text` and `mailto:` in `apply_url`. Results go to
  `job_postings.source_contacts` (kind `email`); Hacker News keeps its behaviour.
  `source_contacts` is a JSON list of `{kind, name, title, email}` with kinds
  `email`, `founder`, `hiring_manager` (YC founders and hiring managers carry names
  and titles, never emails).
- Discarded: `noreply`/`no-reply`/`donotreply` local parts, `NON_CONTACT_EMAIL_DOMAINS`,
  `talent.outreach.aggregator_domains`, `talent.outreach.ats_domains` and
  `talent.outreach.non_application_local_parts` (`privacy`, `legal`, `support`, `sales`…;
  `info` and `contact` stay allowed). Free-mail domains
  (`talent.outreach.free_mail_domains`) are accepted.
- Domain match: when the company has a found domain (`DomainStatus::Found`), the address must be on that
  registrable domain or on a free-mail domain; with no found domain any address
  passing the discard rules counts.
- `SyncPublishedContacts` probes the published addresses (`VerifyPublishedEmails`,
  through `SmtpProbe`) and records them (`RecordPublishedContacts`): an unknown
  mailbox (5.1.x) is `rejected`, kept only as a company-level verdict and never a
  posting contact; accepted, catch-all or inconclusive stay sendable as tier A; 5.7.x
  policy blocks count as inconclusive.
- `php artisan contacts:extract-published {--limit=} {--force}` backfills stored
  postings: dry run by default, chunks of 200, never deletes.

### 2.3 Gates, recipient and sending

- **Gates:** storing a posting, AI profile extraction (and its dispatchers) and the
  client pool (`MatchingJobPostings`) accept a posting when its company is verified
  **or** `JobPosting::hasSendableTierA()`.
- **Recipient:** `SelectRecipientForPosting` picks tier A of the same posting, then
  tier B of the company, then tier C by `OutreachLimits::RECIPIENT_PRIORITY`. A tier-A
  address is used only for the posting that published it.
- **Daily cap:** at most `talent.outreach.tier_a_daily_cap` (`OUTREACH_TIER_A_DAILY_CAP`,
  default 5) applications per tier-A address per day across all clients. Past the cap
  the posting falls back to tier B or C when its company is verified, otherwise it is
  not sendable that day.
- **Sending:** `SendApplicationEmail::preCheck` accepts a tier-A recipient for its own
  posting even when the company is not `verified`; every other check stays.
- **Snapshot:** the application stores `recipient_tier` and `recipient_source` at
  queue time (with an index on `recipient_email, queued_at`).
- **Ordering:** random sending (`QueueRandomApplications`, every plan that uses it)
  takes tier A, B, C, then match score, then random. In the selectable job list with
  `sort=match` the order is score, then tier, then newest; the default newest-first
  order is unchanged.
- **Visibility:** the tier is internal. Clients see no tier, source or recipient. The
  admin company contacts, job posting infolist and application views always show
  source and tier (`—` when there is no snapshot).

### 2.4 Replies and tier report

- A client marks a sent application as replied ("I got a reply") from the
  application detail sheet and can undo it; `applications.replied_at` stores it
  (`applications.reply` endpoint taking a `replied` boolean; re-marking keeps the first
  timestamp, `422` with `reply.not_sent` for an application that was not sent).
  The application resources expose `repliedAt` only.
- `php artisan reports:tier-performance` is read-only: sent applications and replies
  per tier (`--days`, `--json`).

### 2.5 Suppression and privacy

- `outreach_suppressions` holds a domain **xor** an email; only rows with `confirmed_at`
  count (`OutreachSuppressionList`). It stops probing (`DiscoverCompanyContacts`,
  `VerifyPublishedEmails`, the named flow), recipient selection, queueing
  (`QueueApplication::REJECT_SUPPRESSED`) and sending (a suppressed recipient is
  cancelled first in `preCheck`). A domain row must hold the registrable domain; a
  subdomain does not match. The opt-out form and the Filament resource stay in
  `landing-and-legal`; rows are added by hand today.
- Named contacts are personal data: only name, title, email, source and `checked_at`
  are stored.

### 2.6 Named decision-makers (Stage 2, tier B)

- `CompanyPageFetcher` fetches `/about`, `/team` and `/company` of a company over
  HTTPS only, through `PublicNetworkGuard` with the resolved address pinned, no
  automatic redirects (at most 2 manual ones, same registrable domain), 512 KB body cap,
  `text/html` only, 8000 characters of text, `robots.txt` honoured (`RobotsRules`:
  a 404 allows; any other non-200 status or a failed request disallows all) and at least 1 second between requests
  to a domain (per fetcher instance). It returns `[]` for a suppressed domain.
- `ExtractCompanyPeople` (`gpt-5-nano`) returns `{name, title}` evidence from page and
  posting text; `HiringPowerTitle` keeps founder, CEO, CTO and head of
  engineering/people/talent; hiring managers named in `source_contacts` always count;
  `NamedEmailCandidates` builds `first@`, `first.last@`, `flast@`, `firstlast@`
  (lowercase, ASCII).
- `php artisan reports:named-contact-coverage {--sample=50} {--input-usd-per-mtok=}
{--output-usd-per-mtok=} {--json}` is read-only (it writes only `ai_usage_records`
  for the agent calls): per source, % with a plausible name, % with a verified named
  email, % catch-all, % inconclusive, tokens, and a port 25 warning when every session
  is inconclusive.
- `DiscoverNamedContactsForCompany` (queue `contacts`, unique per company, shared
  `contacts:company:{id}` lock) stores one `named`/`smtp_verified` contact for a verified,
  non-catch-all, unsuppressed company not checked within 30 days
  (`companies.named_contacts_checked_at`), only when the random address gets a
  conclusive rejection and a candidate is accepted (first in candidate order). It is
  queued only by `php artisan contacts:discover-named {--limit=} {--force}` (dry run by
  default, not scheduled).
- Retention: `contacts:prune-named`, scheduled daily, deletes `named` contacts older
  than 90 days from `checked_at` and those now covered by the suppression list.
  Applications keep their own recipient snapshot.
- Never: LinkedIn scraping, search-engine scraping or paid search, sending to an
  unverified guess. Every SMTP connection goes through `SmtpProbe` and every company
  website fetch through `PublicNetworkGuard`.

## 3. Open points

- Coverage of named contacts outside YC is still unmeasured: the Stage 2 report has
  not been run against real data and port 25.
- The retention of named contacts (90 days, D16) is to be reconciled with the privacy
  policy (`landing-and-legal`).
- A bounce at send time does not mark a tier-A contact as bad for other clients, and
  `reports:funnel` counts verified companies only, so it does not show tier-A postings
  of unverified companies.

## Pending changes

- **P17.** Paid email-finder API (Hunter or similar): out of scope (D12).
  Reconsider after the P14 report.

## Decisions

- **D1.** (2026-10-30) Keep weaker sources; flag confidence per contact
  instead of removing sources.
- **D2.** Tiers A (published in the posting), B (named, verified), C
  (verified role alias); unverified/catch-all/blocked stay out of sending.
- **D3.** Extract published emails for every source (Stage 1).
- **D4.** (2026-10-31) Free-mail domains are accepted for tier A (was O1).
- **D5.** (2026-10-31) A published email must match the company's known domain,
  free-mail excepted; with no known domain, any address passing P2 counts.
- **D6.** (2026-10-31) Tier-A verification follows P5 as written; 5.7.x policy
  blocks count as inconclusive (was O2).
- **D7.** (2026-10-31) A tier-A email is not reused for other postings of the
  company (was O3).
- **D8.** (2026-10-31) Tier-A addresses have a daily cap across clients (P7a).
- **D9.** (2026-10-31) In the selectable list the tier only breaks ties under
  `sort=match` (was O4).
- **D10.** (2026-10-31) The tier is internal only (was O5).
- **D11.** (2026-10-31) Reply tracking and `reports:tier-performance` are in
  scope (was O6).
- **D12.** (2026-10-31) The paid finder API is out of scope (was O7).
- **D13.** (2026-10-31) The suppression table and checks are built here (P18a).
- **D14.** (2026-10-31) This feature's schema changes use new forward
  migrations (P4), not edits to `create_*` migrations.
- **D15.** (2026-10-31) Stage 2 goes to production (P15), delegated by the owner
  without the real-data numbers yet: it is built but nothing schedules it, so
  named contacts are created only when the owner runs
  `contacts:discover-named --force`.
- **D16.** (2026-10-31) Named contacts live 90 days from `checked_at`
  (delegated by the owner); to be reconciled with the privacy policy
  (`landing-and-legal`) when it is written.
