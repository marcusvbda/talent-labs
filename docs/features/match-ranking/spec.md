# Match Ranking

> Implemented (plan phases 1–5, 2026-10-29). Origin: competitor analysis of
> 2026-10-29 (DevScout ranks jobs by compatibility; talent-labs only filters
> pass/fail).

## 1. What it is

A job posting either matches the client's preferences (the pass/fail gate) or it
does not. Match ranking adds a **match score with an explanation** to every
posting that matches, so the client sees which jobs fit best and why, instead of
a list ordered only by recency.

The score is **explained, not opaque**: rather than a bare percentage, the
client sees the reasons behind it, for example "matches 4 of 5 technologies,
seniority ok, remote ok, missing X".

## 2. Behaviour

- **R1.** Each posting in the client's pool gets a match score computed from the
  client's structured preferences and the posting's extracted profile (data the
  product already has). It is computed in SQL when the list is read
  (`JobPoolQuery` + `MatchScore`); nothing is stored.
- **R2.** The score comes with an explanation the client can read: which parts
  of the preferences the posting satisfies and which it does not (for example
  technologies matched out of those requested, seniority, remote/location fit,
  and what is missing). Each job card carries `match: {score, criteria[]}`
  (`null` when no criterion applies).
- **R3.** The client's job list can be ordered by match (best fit first), in
  addition to the default newest-first order (`sort=match` on `GET /jobs` and
  `GET /internal/jobs`).

Owner decisions (2026-10-29, `/plan-feature`), all implemented:

- **R4. Format.** The score is an integer percentage from 0 to 100. Each Jobs
  list row shows it as "N% match" ("N% compatível" in PT). The job detail
  sheet shows a "Why it matches" block with the breakdown per criterion
  (technologies matched out of requested plus the missing ones, seniority,
  location, title).
- **R5. Eligibility gate unchanged.** The current pass/fail match
  (`MatchingJobPostings`) remains the only eligibility rule. The score never
  adds or removes postings. It only orders and explains the postings already
  in the pool.
- **R6. Where the ranking applies.** Only the Jobs list. "Newest first" stays
  the default order. "Best match" is an opt-in sort kept in the URL, with ties
  broken newest first. A "Sort" select in the Jobs filter bar switches between
  "Newest first" and "Best match", and `sort=match` is kept in the URL (the
  default is omitted). Random sends stay random. Select and review are
  unchanged (they act on what the client picks from the list).
- **R7. Weights.** Technologies 50 (share of the requested technologies the
  posting lists). Seniority 25 (exact match = full, "not stated" = half, other
  = 0). Remote/location 15 (fits the remote mode or a listed location = full,
  otherwise 0). Title 10 (matches a preferred title = full). A criterion whose
  preference is empty is left out, and the remaining weights are re-normalised
  to 100. Exclude words are a gate only and are not scored. With no
  applicable criterion (no preferences, or "All jobs" with nothing else set),
  no score is shown.
- **R8. Settled by the current code.** Postings whose profile is not `done`
  never enter the pool, so they never need a score. The score is computed
  when the list is read, so it refreshes through the existing signals
  (`jobs.collected` and the jobs invalidation after saving preferences),
  without new events or polling. The Jobs list is already limited to plans
  that can choose jobs (Starter, Pro), so the ranking needs no plan change.

## 3. Attachments and references

- Competitor reference (not to be copied): DevScout landing page and pricing
  table, 2026-10-29. Relevant claims: "vagas ranqueadas por compatibilidade",
  "prioridade por fit real", "visualizar vagas com match", "96% compatibilidade
  média". Source file: `~/Downloads/screencapture-devscout-app-pt-2026-10-29-00_28_33.pdf`
  (not copied into `assets/`).

## 4. Open points

Format, gate, send modes, weights, refresh, profiles that are not `done` and
plans are settled in R4–R8. Still open:

- Surfaces beyond the Jobs list: should the dashboard "new matches" card or the
  preferences preview show the score? The plan assumes the Jobs list row and
  the job detail only (`plan.md` A1).
- EN/PT copy for the explanation texts. The plan proposes the strings
  (`plan.md` Phases 2–4), and the owner may adjust them.
