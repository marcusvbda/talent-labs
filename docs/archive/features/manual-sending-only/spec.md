> Status: ARCHIVED (implemented). Historical record only. The code is the source of truth. DO NOT use this document as a reference to implement or change code.

# manual-sending-only — nothing is ever sent without a click; "Send randomly" for everyone

> **Kind:** product change (backend + dashboard/Jobs UI). **Supersedes** the
> `auto` mode of `plans-and-sending-modes` (Part 0, item 1). That spec is left
> untouched; where they conflict, this one wins.
>
> **How to run:** `/plan-spec docs/features/manual-sending-only/spec.md`, then
> `/execute-phases`. No new dependencies.

## Part 0 — Owner decisions (final)

1. **The system NEVER sends anything on its own.** Every application email is
   the result of an explicit click by the client. The scheduled auto-dispatch
   (`outreach:dispatch-auto`) is removed. Pacing, window, pause/resume, quota
   and the live panel are unchanged; they only act on what the client queued.
2. **"Send randomly" is available to every plan** (Free, Starter, Pro). One
   click queues randomly chosen matching postings until the client's daily
   limit (`remaining` quota) is used up. The queued items then leave one by one
   with the existing spacing and window rules.
3. **Select and send** (client picks specific jobs) is only for **Starter** and
   **Pro**, each with its own daily limit. Pro keeps editing each email
   (`review`) before it is queued.
4. **Free has no Jobs page.** Free clients never browse the list of postings;
   they only see jobs after they were sent, inside **Applications**.
   Starter and Pro keep the Jobs page.

5. **Plan restrictions are enforced by the server, never by the UI.** A lock
   overlay, blur or hidden element is cosmetic only. Data a plan may not see is
   not sent to that client at all, and actions a plan may not perform are
   rejected by the backend. Deleting the overlay in the browser inspector must
   reveal nothing and enable nothing.

## Part B — Product spec

### B.1 Plan modes

| Plan    | Mode     | Random send | Choose jobs | Edit emails | Jobs page |
| ------- | -------- | ----------- | ----------- | ----------- | --------- |
| Free    | `random` | yes         | no          | no          | no        |
| Starter | `select` | yes         | yes         | no          | yes       |
| Pro     | `review` | yes         | yes         | yes         | yes       |

- `auto` disappears from the plan catalog and from `SendMode`; Free becomes
  `random`. Limits are unchanged (env-driven).
- Existing `ApplicationOrigin::Auto` rows stay valid (history); nothing creates
  new ones. Random sends are recorded as `manual` (the client clicked).

### B.2 Random send

- New endpoint `POST /internal/applications/random`, allowed for all plans.
- Picks postings with `MatchingJobPostings::forUser` (client's preferences and
  active languages, freshness window), in random order, and queues them through
  `QueueApplication` until `remaining` reaches 0 or candidates run out. Skips
  postings that cannot be queued (already applied to the company, no contact…)
  and keeps going.
- Same eligibility as any send (`CanSendApplications`: Gmail connected,
  profile complete, quota, not paused). Same errors and messages.
- Returns the existing `QueueResult` shape (queued count, skipped, quota).
- Pro: random send queues directly, without the review step.
- Realtime: queues emit the same broadcast events as any other queue action.

### B.3 Select and send

- `POST /internal/applications` (select) and the drafts/reviewed review flow
  stay as they are and are denied for Free (no more `auto_only` wording; the
  message says selection is available on Starter and Pro).

### B.4 Jobs page and job data for Free

- `/jobs` and `/internal/jobs*` are forbidden for Free (page redirects to the
  dashboard; API returns 403). The sidebar/nav hides the Jobs entry for Free.
- Job detail reached from an application the client already sent stays
  available to Free (Applications page, `applications.show`).
- Starter and Pro: unchanged.

### B.5 Dashboard "New matches" card

- **Server-side:** the dashboard payload (`/internal/dashboard`, and the
  Inertia props of any page embedding it) returns `matches.items = []` for Free
  and only the `total` count. Starter/Pro get the items. No plan-gated list is
  ever rendered "underneath" a lock: when the plan lacks access, the component
  is not mounted with data (no `PlanGate` around real content).

- Every plan: the **Send randomly** button works and is enabled when there is
  quota left and at least one match; it shows a confirmation summary
  ("Send up to N applications at random") before queueing, like other sends.
- Free: the card shows only the count of matches and the button — no list of
  postings, no blurred rows, no lock. A short note points to Starter/Pro for
  choosing jobs.
- Starter/Pro: list, selection, clear and send as today, plus the working
  Send randomly button, a **See more** button (4 more rows per click, until
  `matches.total` is reached) and a header arrow ("View all jobs") to `/jobs`.
  Free sees neither See more nor the arrow.
- The dead `Send randomly` button (no handler) and the Free "auto" banner
  (`AutoBanner`, `mode === 'auto'` branches) are removed.

### B.5b Enforcement audit

- Every route/endpoint that returns or acts on postings by choice
  (`/jobs`, `/internal/jobs*`, `applications.store`, `applications.drafts`,
  `applications.reviewed`, dashboard matches) is checked against the plan on
  the server (policy/request `authorize`), reading the plan per request.
- Review the other `PlanGate` usages in the client app: for each, confirm the
  gated content is not present in the response for plans without access;
  report any that are, fix those in scope of this feature.
- Frontend plan checks stay only as UX (hide/disable), never as the guard.

### B.6 Fixtures

- Fixture engine/catalog (`VITE_USE_FIXTURES=true`) mirror the above: Free mode
  `random`, random send handler, no `auto` dispatch, no Jobs for Free, and the
  fixture dashboard also returns no items for Free. Fixtures are dev-only and
  client-side, so real enforcement can only be verified with the flag off.

### B.7 Out of scope

- Changing limits, pacing, window, pause rules or the live panel.
- Migrating old `auto` origin rows.
- Any test changes (not requested).
