# Contact Quality

> Body: current behaviour, read from the code on 2026-10-30 (not produced by
> `/update-feature-spec`). Company domain/alias discovery itself is described
> in `docs/features/job-mining/spec.md` §2.8 and is not repeated here.
> `## Pending changes` holds what the owner decided on 2026-10-30. Items
> marked **O** are open decisions: `/plan-feature` asks the owner, never
> guesses.
>
> Depends on: `production-hardening` P7 (outbound guard) for Stage 2.
> Job-mining's "Out of scope" already says named-contact discovery would get
> its own spec: this is it.

## 1. What it is

Who an application is sent to. Goal (owner): stronger applications that
reach someone who reads them and, when possible, someone with hiring power —
cheaply or for free.

## 2. Current behaviour (2026-10-30)

- **Contacts:** only role aliases (`careers`, `jobs`, `hr`, `talent`,
  `recruiting`, `people`) probed over SMTP (job-mining §2.8).
  `ContactConfidence`: `smtp_verified`, `catch_all`, `mx_only` (no longer
  produced).
- **Recipient:** `SelectRecipientForCompany` picks the company's
  `smtp_verified` contact with the best position in
  `OutreachLimits::RECIPIENT_PRIORITY`; aliases outside that list are never
  used.
- **Gates that require a verified company** (`outreach_status = verified`):
  storing new postings (`StoreCompanyPostings`, `FetchJobsFromSource`), AI
  profile extraction (`ExtractJobPostingProfileJob::handle` returns early,
  plus the dispatchers in `DiscoverCompanyContacts` and
  `DiscoverContactsForPosting`), and sending (`SendApplicationEmail`
  sub-steps `CheckingCompany` and `ConfirmingRecipient`, which also requires
  an `smtp_verified` contact of that company).
- **Source contacts:** `job_postings.source_contacts` (JSON list of
  `{kind, name, title, email}`, kinds `email`, `founder`,
  `hiring_manager`). Filled only by `HackerNewsAdapter` (emails found in the
  post: `mailto:` links, `[at]`/`[dot]` de-obfuscation,
  `NON_CONTACT_EMAIL_DOMAINS`, `MAX_EMAILS`) and `YCombinatorAdapter`
  (founders: names and titles only, never emails). Shown in the admin job
  posting infolist; not used for sending.
- **Replies:** `ApplicationStatus` has no "replied" state, and the Gmail send
  scope cannot read replies, so reply rates cannot be measured today.

## 3. Open points

- Coverage of named contacts outside YC is unknown; Stage 2 starts with a
  measurement (P14) for that reason.

## Pending changes

### Stage 1 — Emails published in the posting, and contact tiers

- **P1.** Extract emails published in the posting for **every** source: one
  shared extractor built from the `HackerNewsAdapter` logic, applied to
  `description_html`/`description_text` and to `mailto:` in `apply_url`.
  Results go to `source_contacts` (kind `email`). Hacker News keeps working as
  today.
- **P2.** Discard: `noreply`/`no-reply`/`donotreply` local parts, domains in
  `NON_CONTACT_EMAIL_DOMAINS`, aggregator domains
  (`talent.outreach.aggregator_domains`) and ATS/job-board domains (new config
  list). **O1:** free-mail domains (gmail.com etc.).
- **P3.** Contact **tiers**, per contact (not per source):
    - **A** — email published in the posting;
    - **B** — named decision-maker, verified (Stage 2);
    - **C** — verified role alias (today's `smtp_verified`).
      Never sendable (unchanged): `catch_all`, `mx_only`, inconclusive,
      unverified guesses.
- **P4.** Data (forward migrations only): `contacts.source`
  (`alias_probe` | `posting` | `named`), nullable `contacts.job_posting_id`
  (posting that published it), nullable `person_name`, `person_title`
  (Stage 2). Whether `tier` is stored or derived is decided in the plan.
- **P5.** A published email is probed with the existing `SmtpProbe` when
  possible: unknown mailbox (5.1.x) → discarded; accepted, catch-all or
  inconclusive → sendable as tier A, because the company published it.
  **O2.**
- **P6.** Storage and AI gates accept a posting when its company is verified
  for sending **or** the posting has at least one sendable tier-A email. Same
  rule for the AI profile extraction and its dispatchers. After
  implementation, job-mining §2.4/§2.8 is updated with `/update-feature-spec
job-mining`.
- **P7.** Recipient per application: tier A of the same posting → tier B of
  the company → tier C by `RECIPIENT_PRIORITY`. **O3:** may a tier-A email of
  one posting be used for other postings of the same company?
- **P8.** `SendApplicationEmail` checks accept a tier-A recipient for its own
  posting even when the company is not `verified`; all other checks stay.
- **P9.** The application stores a snapshot of the recipient's tier and
  source at queue time.
- **P10.** Ordering: random sending (`QueueRandomApplications`, Free plan)
  takes tier A, then B, then C, then match score. In the selectable job list
  the tier is a secondary sort key or only a badge — **O4**.
- **P11.** Visibility: **O5** — tier badge ("direct contact" / "generic
  contact") shown to clients or used internally only. Admin (Filament)
  company/contact screens always show source and tier.
- **P12.** Measuring tiers: **O6** — a client action "I got a reply" on sent
  applications (e.g. `replied_at`), and a read-only report
  `reports:tier-performance` (sent and replies per tier). Without it, tier
  decisions stay guesses.
- **P13.** Backfill for stored postings: `contacts:extract-published
{--limit=} {--force}` — dry run by default, chunks of 200, never deletes
  (same style as `contacts:recheck`).

### Stage 2 — Named decision-makers (gated by a measurement)

- **P14.** Measurement first, read-only, no sending:
  `reports:named-contact-coverage {--sample=50}` over verified companies from
  several sources. Name sources: YC `source_contacts` founders, names in the
  posting text, and the company's `/about`, `/team`, `/company` pages
  (fetched through `PublicNetworkGuard`, robots.txt respected, 1 request/s
  per domain, short timeouts; names/titles extracted by a cheap AI agent).
  Then candidate generation + SMTP probe. Report: % companies with a plausible
  name, % with a verified named email, % catch-all, AI cost. **Stop and show
  the report to the owner.**
- **P15.** Only if the owner approves after P14: production version.
  Candidates from name + domain: `first@`, `first.last@`, `flast@`,
  `firstlast@` (lowercase, accents transliterated). Probe only on domains that
  are not catch-all and gave a conclusive answer; if more than one candidate
  is accepted, take the first in that order. Stored as tier B with name and
  title. Only roles with hiring power: founder, CEO, CTO, head of
  engineering/people/talent, the hiring manager named in the posting.
- **P16.** Never: LinkedIn scraping, search-engine scraping or paid search in
  volume, sending to an unverified guess.
- **P17.** Paid email-finder API (Hunter or similar) only for the top plan, on
  demand, cached per company — **O7**, not in scope unless the owner says so.

### Privacy

- **P18.** Named contacts are personal data: store only name, title, email,
  source and `checked_at`; retention rule decided with the privacy policy
  (`landing-and-legal`); the company opt-out/suppression list applies to every
  tier and stops probing too.

## Decisions

- **D1.** (2026-10-30) Keep weaker sources; flag confidence per contact
  instead of removing sources.
- **D2.** Tiers A (published in the posting), B (named, verified), C
  (verified role alias); unverified/catch-all/blocked stay out of sending.
- **D3.** Extract published emails for every source (Stage 1).

## Open decisions

- **O1.** Accept free-mail domains (e.g. a founder's gmail.com published in a
  post)?
- **O2.** Tier-A verification rule in P5.
- **O3.** Reuse a posting's published email for other postings of the same
  company.
- **O4.** Tier as a sort key or only a badge in the selectable list.
- **O5.** Show the tier to clients (creates expectations; Free gets weaker
  contacts) or internal only.
- **O6.** Reply tracking ("I got a reply").
- **O7.** Paid finder API on the top plan.
