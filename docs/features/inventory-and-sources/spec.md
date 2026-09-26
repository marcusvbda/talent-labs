# inventory-and-sources — measure the pool and grow it beyond foreign remote jobs

> **Order:** 9 of 10. **Depends on:** `client-core-wiring` (job language)
> DONE; `targeted-sourcing` phase 10 measured. Part B.2 can run any time;
> Part B.3 **is blocked by owner decision D7**.
> **Kind:** backend (reporting command, adapters, admin).
>
> **How to run:** `/plan-spec docs/features/inventory-and-sources/spec.md`
> (the planner must mark B.3 phases BLOCKED until D7 is answered).

## Part 0 — Context and decisions

The product only sells if each client has enough companies to apply to.
Last audit (2026-09-26, before `targeted-sourcing` phase 10): 670 postings,
376 companies, only ~17% of postings reach a `verified` company (63
companies). Each client can apply to a company **once, ever**, so a Free
client (25/day) would exhaust that in days. Today all sources are global
remote aggregators in English. The owner wants Brazilian and Spanish
language jobs too.

Decided:

1. **Measure before adding.** A repeatable funnel report is the first step
   and the input for pricing and limits (limits are constants the owner
   raises as the pool grows).
2. **Launch gate (recommendation, owner may change):** do not charge a
   plan while the typical client pool (with realistic preferences) is below
   ~10 × its daily limit of **not-yet-applied, verified companies**.
3. Rules unchanged: no LinkedIn/Indeed/Glassdoor scraping (ToS), adapters
   are deterministic (no AI), aggregators keep attribution in the admin, one
   `sources` row per aggregator, max 10 requests per source per run.

## Part B — Product spec

### B.1 Files to read first

Skill `job-collection`, `app/Collection/**`, `app/Enums/SourceAdapter.php`,
`database/seeders/SourceSeeder.php`, `app/Contacts/**`,
`docs/features/targeted-sourcing/plan.md`, `docs/features/more-aggregators/spec.md`.

### B.2 Funnel report (no decision needed)

Command `reports:funnel {--days=30} {--user=}` printing a table (and
`--json`):

- Per source: postings fetched, new, target-family %, with company, company
  with domain, company `verified`, postings with profile `done`, language
  split (en/pt/es/other).
- Global: companies by `outreach_status`, contacts by confidence, share of
  `mx_only` (port 25 health indicator: if > 80% of contacts are `mx_only`,
  print a warning "outbound port 25 is probably blocked").
- With `--user=<id>`: that client's pool size (verified companies not yet
  applied, per language) and "days of runway" = pool / daily limit.
- Admin: a read-only Filament page "Inventory" showing the same numbers
  (computed on demand with a "Refresh" button — a click, not polling).

### B.3 New sources (BLOCKED by D7)

Research phase first (read-only, produces a short findings section in the
phase report, no code): for the chosen market, list candidate sources with
an official API or feed, terms of use about automated access, whether they
expose company websites (domain resolution quality), expected volume in
the target role families, and cost/keys.

Candidates to evaluate (nothing verified yet):

- **Adzuna API** — official API with country endpoints and a free key
  (`ADZUNA_APP_ID`, `ADZUNA_APP_KEY`); verify which countries are available
  (e.g. Brazil, Spain) and the attribution requirements.
- **ATS single-company adapters already in code** (Greenhouse, Lever,
  Ashby): add curated lists of companies (identifiers) hiring in Brazil /
  Spain / Portugal. They have high domain quality (company known).
- **Brazilian / Spanish job boards with public feeds or APIs** — to be
  identified in the research; Gupy has no stable public API (owner's
  earlier finding).
- More global remote aggregators only if the funnel shows good verified
  rates for them.

Then one phase per approved source: adapter implementing
`JobSourceAdapter::fetch(Source)`, `SourceAdapter` enum case, seeder row
(inactive by default), admin settings help text, targeting via `settings`,
and a funnel run to compare before/after.

### B.4 Company domain quality (optional, after the report)

If the report shows most losses at domain resolution for sources without a
company website, evaluate per source whether postings contain a company
site in the description (deterministic extraction of URLs that match the
company name, reusing the `RegistrableDomain` rules of `targeted-sourcing`
D3) — as a separate owner-approved phase.

## Acceptance criteria

- **AC01** `reports:funnel` prints the per-source and global funnel and the
  port 25 warning when applicable; `--user` prints pool size per language
  and runway days.
- **AC02** The admin Inventory page shows the same numbers on demand.
- **AC03** (after D7) Each approved source has an adapter, a seeded inactive
  source row, and a before/after funnel comparison in its phase report.
- **AC04** No scraping of sites whose terms forbid it; every new source
  documents its terms and attribution in its phase report.

## Verification

`composer lint:check`, `composer types:check`, existing tests; run the
report before and after a collection run.

## Out of scope

LinkedIn/Indeed/Glassdoor, AI in adapters, paid data providers unless the
owner approves one.

## Owner decisions

- **D7 (blocks B.3):** launch market — (a) Brazilians applying to jobs in
  Brazil, (b) Brazilians applying abroad (current sources), (c) both.
  Recommendation: start with (b), which the current sources already serve,
  and add (a) only after checking with a few real Brazilian companies that
  emailing `careers@`-style addresses gets read (many hire through portals).
- **D-GATE:** confirm or change the launch gate of Part 0 item 2.
