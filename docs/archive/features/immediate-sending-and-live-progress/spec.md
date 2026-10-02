> Status: ARCHIVED (implemented). Historical record only. The code is the source of truth. DO NOT use this document as a reference to implement or change code.

# immediate-sending-and-live-progress — queued items leave now; the live panel narrates every step

> **Kind:** product change (backend + dashboard). **Supersedes**, where they
> conflict, `plans-and-sending-modes` (Part 0, item 3 — sending window) and
> `client-app-screens` (the `LiveSending.current` definition and the live
> panel). Those specs are left untouched; this one wins.

## Part 0 — Owner decisions (final)

1. **No waiting for a sending window by default.** When a client clicks send
   and has a posting, it is sent right away, spaced only by
   `OUTREACH_SEND_INTERVAL_MIN_SECONDS`…`MAX_SECONDS`, the daily quota and
   pause/resume. The window (`OUTREACH_WINDOW_*`) stays available in config,
   but `OUTREACH_WINDOW_ENABLED` now defaults to **false**. When it is enabled
   the previous rules (start/end, weekdays only, `outside_window` state) apply
   unchanged.
2. **The live panel always shows the pipeline, step by step.** Every send
   shows the stepper advancing one step at a time (validating recipient →
   adapting template → attaching CV → sending → sent), driven by the real
   stages and sub-steps paced by `OUTREACH_STEP_DELAY_MS`. Steps are never
   skipped, batched or invented.

## Part 1 — Behaviour

### 1.1 Current application

- `LiveSending.current` is the application **in progress**: status `sending`,
  **or** status `queued` with a recorded `stage` (the recipient/template/CV
  pre-checks record their stage while the row is still queued).
- The in-progress application is excluded from `queue`, `queuedCount` and
  `nextSendAt`, so it is never listed twice and the countdown always targets
  the next waiting item.
- `waitStartedAt` (countdown start) is the last finished send, but never
  earlier than one maximum spacing before `nextSendAt`. Queued slots are never
  used as the start (the last one lies in the future).

### 1.2 Live panel

- The first stage event of an application puts it in the panel (no waiting
  for the end-of-send update); each following stage event advances the
  stepper.
- Between two sends of the same run the panel keeps the application the
  countdown started from, with all steps done (including "Sent"). A send from
  an earlier run is not shown.
- A failed send keeps its red step and reason for 6 s (unchanged).
- The card re-reads the live payload from the server when an application
  takes the panel and when one settles (sent, failed, ambiguous), so a lost
  realtime event cannot leave it stale.

### 1.3 Realtime reliability

- The throttled `account.updated` dispatch runs after the surrounding
  transaction commits. Taking the cache lock inside a PostgreSQL transaction
  can abort that transaction (SQLSTATE 25P02) and must never break sending,
  Resume, or saving preferences.

## Acceptance criteria

- **AC01** With the window disabled, a queued application leaves within the
  configured spacing at any hour, including nights and weekends.
- **AC02** Resume re-slots the queue without error.
- **AC03** During a send the stepper moves through each stage in order, one
  at a time, ending with every step checked on "Sent".
- **AC04** Between sends the panel shows the last sent application of the run
  with all steps checked and a countdown that fills up; at the start of a new
  run no earlier send is shown.
- **AC05** When the queue empties the card leaves the running state without a
  reload.

## Out of scope

- Changing spacing values, quota, pause rules or plan gating.
- Rescheduling items queued before the window was disabled (Pause → Resume
  re-slots them).
- Any test changes (not requested).
