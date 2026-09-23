# talent-labs — Non-verifiable contact status (spec addition)

> **Prompt to give the agent:** "Read this file end to end. Small addition
> on top of already-DONE `job-collection-mvp` and `contact-discovery-mvp`
> (and `more-aggregator-sources`/`aggregators-only` if built). Reuse the
> existing harness. Copy Part B into
> `docs/features/verification-status/spec.md`, then execute it. No data is
> deleted by this spec — every job posting and company already collected
> stays exactly where it is. Ask before any new dependency (none should be
> needed)."

## Part 0 — What changed and why

`contact-discovery-mvp` records a `Contact` row per resolved alias with a
`confidence` (`smtp_verified` / `catch_all` / `mx_only`) and rolls the
company up to `contact_status = found` the moment **any** `Contact` row
exists — regardless of confidence. That conflates two different things:
"we found _something_" and "we can actually trust this enough to send an
automated email to it." A company whose only resolved aliases are
`catch_all` or `mx_only` shows up identically to one with a real
`smtp_verified` hit, which is misleading for anyone deciding what's ready
for outreach.

**Product decision (owner, final):**

1. **"Sufficient contact for automated send" = at least one
   `smtp_verified` contact.** `catch_all` and `mx_only` are guesses, not
   confirmation, and don't count — even if the company has several of
   them, and even if `contact_status = found`.
2. A company that has been through discovery and doesn't clear that bar
   gets a **permanent** status, `not_verifiable`, not a deletion. It
   already cost an HTTP fetch (domain resolution) and an SMTP attempt
   (contact discovery) — throwing the row away throws that work away too,
   and if the same company resurfaces from a future collection run
   (matched by `normalized_name`, per `contact-discovery-mvp` B.3.1), the
   pipeline would otherwise redo it from zero for no reason.
3. `not_verifiable` companies (and their job postings) are **hidden from
   default admin views**, not deleted. A filter reveals them on demand so
   an admin can audit why a given company failed and decide whether to
   retry discovery for it.
4. A new posting that resolves to an already-`not_verifiable` company
   inherits that status immediately and is **not** reprocessed —
   `contact-discovery-mvp`'s existing idempotency (`DiscoverCompanyContacts`
   already skips a company whose `contact_status !== pending`) already
   gives this for free; this spec only has to make sure the _new_ status
   this file introduces follows the same skip, not just the old one.

**Explicitly not deleted by this spec, ever:** any `job_postings` row, any
`companies` row, any `contacts` row. Nothing here removes data — it only
adds a status and changes what's shown by default.

## Part B — Product spec

### B.1 New column and enum

Add to `companies`: `outreach_status` — string, backed by a new
`App\Enums\OutreachStatus` enum: `pending`, `no_domain`, `not_verifiable`,
`verified`. Nullable-free, default `pending` (matches the existing
`contact_status`/`domain_status` default convention from
`contact-discovery-mvp`). Migration only adds the column — no backfill
logic in the migration itself (B.4 handles backfill via a console
command, same pattern as `contacts:backfill`).

This is **additive** alongside the existing `contact_status` (`pending` /
`no_domain` / `found` / `not_found`) and `is_catch_all` columns — don't
remove or rename those; existing filters/badges that read them keep
working exactly as `contact-discovery-mvp` built them. `outreach_status`
is the new field that answers "is this company good for automated
outreach", which `contact_status = found` alone never actually answered.

### B.2 Where it's computed

In `App\Contacts\Actions\DiscoverCompanyContacts::handle()` (read the
existing method before changing it — this is the same method that already
sets `contact_status`/`is_catch_all`), set `outreach_status` at the same
points, from the same data, no new queries beyond what's already loaded:

- Early return for no domain (existing `contact_status = no_domain`
  branch): also set `outreach_status = no_domain`.
- After the alias loop finishes and `contact_status` is set to `found` or
  `not_found`: set `outreach_status = verified` if any `Contact` row just
  created for this company has `confidence = smtp_verified`; otherwise
  `outreach_status = not_verifiable`. This covers both sub-cases in one
  branch — `contact_status = not_found` (no contacts at all) and
  `contact_status = found` with only `catch_all`/`mx_only` rows — both are
  `not_verifiable`; only a real `smtp_verified` hit clears the bar.

Same transaction/save as the existing `contact_status` write — one model
save, not two.

### B.3 Skip logic (idempotency for the new status)

`DiscoverCompanyContacts`'s existing guard is `Skip if contact_status !==
'pending'`. That's still correct and doesn't need duplicating for
`outreach_status`, because both fields are written together in the same
method call (B.2) — a company that's already past `pending` on
`contact_status` never re-enters this method, so `outreach_status` is
never recomputed for it either. Read the method to confirm this is really
true before moving on (i.e. confirm there's no code path that sets
`contact_status` without going through this method) — if one exists,
either route it through this method or add the guard there too, rather
than leaving a gap where `outreach_status` could go stale relative to
`contact_status`.

`ResolveCompanyForPosting` (company resolution, B.3.1 in
`contact-discovery-mvp`) is unaffected — it only ever touches
`normalized_name`/`name`/`company_id`, never the status columns, so a new
posting landing on an existing `not_verifiable` company already just gets
that company's current row via `firstOrCreate` — nothing to change there.

### B.4 Backfill for existing companies

One-off, idempotent, re-runnable console command:
`php artisan companies:backfill-outreach-status`. For every `companies`
row where `outreach_status` is still `pending` (or wherever the migration
defaulted it) but `contact_status` is already past `pending` (i.e.
discovery already ran under the old logic, before this column existed):
compute `outreach_status` from that company's existing `contacts` rows
using the exact same rule as B.2 (any `smtp_verified` → `verified`,
`no_domain` if `contact_status = no_domain`, else `not_verifiable`) — no
new domain/SMTP lookups, this is a pure derivation from data already on
disk. Chunk with `chunkById(200, ...)`. Safe to run twice (second run
finds nothing left in `pending`).

### B.5 Admin panel changes

**Companies (`CompanyResource`)**

- Table query: default to `->where('outreach_status', '!=',
OutreachStatus::NotVerifiable)` — i.e. `not_verifiable` companies are
  excluded from the default list, everything else (`pending`, `no_domain`,
  `verified`) still shows exactly as before.
- Add a Filament table filter, **"Show not-verifiable"** (toggle filter,
  default off). Toggling it on removes the default exclusion for that
  session's view (standard Filament `Filter::make(...)->toggle()` pattern
  or equivalent — read how the existing `domain_status`/`contact_status`
  filters are built in this resource and match that style). This is the
  admin's audit path: turn it on to see why specific companies failed,
  then use the existing "Retry discovery" row action (from
  `contact-discovery-mvp` B.7) if they want to re-attempt one.
- Add `outreach_status` as a badge column (colors: gray `pending`, gray
  `no_domain`, red `not_verifiable`, green `verified`) — placed next to
  the existing `contact_status` badge, not replacing it.
- "Retry discovery" action: also reset `outreach_status` to `pending`
  alongside the existing `domain_status`/`contact_status`/`is_catch_all`
  resets, so a retried company re-enters B.2's computation cleanly.

**Job postings (`JobPostingResource`)**

- The existing "Contact" column (derived from `posting.company.
contact_status`) stays as-is.
- Default table query: exclude postings whose `company.outreach_status =
not_verifiable`, mirroring the Companies default (a job posting from a
  company that isn't send-ready shouldn't clutter the default view either)
  — implemented as a `whereHas('company', fn ($q) => $q->where
('outreach_status', '!=', OutreachStatus::NotVerifiable))` combined with
  `orWhereNull('company_id')` (postings whose company hasn't resolved yet
  must still show — they're `pending`, not excluded).
- Add the same **"Show not-verifiable"** toggle filter here, independent
  toggle state from the Companies resource's own filter (Filament filters
  are per-resource/per-table already — no shared state to build).

### B.6 Acceptance criteria

- **AC01** — Migration adds `outreach_status` with the 4 states; existing
  `contact_status`/`domain_status`/`is_catch_all` columns and their data
  are untouched.
- **AC02** — A fresh company that resolves a domain and gets at least one
  `smtp_verified` contact ends up `outreach_status = verified`.
- **AC03** — A fresh company that resolves a domain, gets discovery run,
  and ends up with only `catch_all` and/or `mx_only` contacts (or zero
  contacts) ends up `outreach_status = not_verifiable` — confirm both the
  zero-contacts case and the catch-all-only case explicitly, they're
  different code paths through B.2's branch.
- **AC04** — A company with no resolvable domain ends up `outreach_status
= no_domain`.
- **AC05** — `companies:backfill-outreach-status` correctly derives
  `outreach_status` for every pre-existing company from its already-stored
  `contacts`/`contact_status` data (no new network calls), run twice with
  no change on the second run. Report the before/after distribution across
  the 4 states.
- **AC06** — Companies admin table hides `not_verifiable` rows by default;
  toggling "Show not-verifiable" reveals them; the same holds for Job
  Postings with its own independent toggle.
- **AC07** — A `job_postings` row belonging to a `not_verifiable` company
  is never deleted by anything in this spec — confirm the row count before
  and after running the backfill command is unchanged.
- **AC08** — A new collection run that produces a posting for a company
  already marked `not_verifiable` (same `normalized_name`) does **not**
  re-run domain/SMTP discovery for that company (confirm via the existing
  skip guard, and confirm no new `contacts` rows or SMTP connections are
  made for it) — the new posting simply appears under that company,
  correctly hidden from the default Job Postings view by B.5's query.
- **AC09** — "Retry discovery" on a `not_verifiable` company resets
  `outreach_status` to `pending`, and after re-running discovery the
  company gets a fresh, independently-computed `outreach_status`.
- **AC10** — `contact-discovery-mvp`'s own acceptance criteria (AC01–AC12)
  still hold — this spec only adds a column and a derived value, it
  doesn't change domain resolution, SMTP probing, or the existing
  `contact_status`/`is_catch_all` semantics.

### B.7 Verification

1. Report the `outreach_status` distribution (`pending`/`no_domain`/
   `not_verifiable`/`verified` counts) immediately after
   `companies:backfill-outreach-status`'s first run, then confirm a second
   run changes nothing.
2. In tinker, hand-pick one company from each of the 4 resulting states and
   confirm its `contacts` rows actually match the rule (e.g. a
   `not_verifiable` one really has no `smtp_verified` row; a `verified` one
   really has at least one).
3. Trigger one real "Collect jobs now" run, confirm any brand-new company
   gets `outreach_status` computed correctly end to end, and confirm (via
   query log or a quick count of SMTP-adjacent log lines) that a posting
   landing on an _existing_ `not_verifiable` company triggers no new
   discovery work.
4. Admin checklist: open Companies with the toggle off (no
   `not_verifiable` rows visible), turn it on (they appear), same for Job
   Postings; click "Retry discovery" on one `not_verifiable` company and
   watch its badges update live.

### B.8 Out of scope

Deleting any company/posting/contact data (explicitly ruled out in Part
0); automatic re-retry/backoff scheduling for `not_verifiable` companies
(manual retry only, via the existing action); changing the meaning or
storage of `contact_status`/`is_catch_all` themselves; any change to how
aliases are tried or how SMTP verification works (that's
`contact-discovery-mvp`'s B.3.3, untouched here); exposing
`outreach_status` anywhere outside `/admin`; a bulk "retry all
not_verifiable" action (one-at-a-time via the existing row action is
enough for this round); anything already out of scope in the prior three
specs.
