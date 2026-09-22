---
description: Execute only the requested phases of a plan.md (e.g. "3", "3-5", "2,4", "next"), updating their status in the plan, then stop and report. Never commits.
argument-hint: <path/to/plan.md> <phases: N | N-M | N,M | next>
---

# Execute plan phases

Plan: `$1`
Phases requested: `$2`
Full arguments: $ARGUMENTS

Load and follow the `execute-feature` skill in **plan mode**, together with
`CLAUDE.md`. Git stays read-only: **never commit, stage or push.** Finishing a
phase is not a request to commit.

## 1. Resolve the phase list

- `N` → that phase only. `N-M` → N through M in order. `N,M` → exactly those,
  in order. `next` → the first `PENDING` phase whose dependencies are all
  `DONE`.
- Run nothing outside that list, even if it's small or obviously next.

## 2. Gate (before touching code)

Read the plan's header, `Owner decisions`, `Global constraints`, and the
requested phases in full. For each requested phase:

- It exists, and its status is `PENDING` or an interrupted `IN_PROGRESS`
  (which you reset to `PENDING`). If it's already `DONE`, skip it and say so.
- Every `Depends on:` phase is `DONE`. A dependency that is also in the
  requested list and comes earlier counts once it finishes.
- It's not `BLOCKED (Dn)`. If a decision Dn is still open, stop and restate
  the decision with the options. Continue only if the owner's message resolves
  it; then record the answer under that decision in the plan.

If the gate fails, stop and report. Don't partially run a phase.

## 3. Run each phase, in order

1. Set `Status: IN_PROGRESS` in the phase and on the status board.
2. Delegate its steps to the role named in the phase, following the
   execute-feature per-task loop: embed the phase's **Contract** and AC text
   in the prompt, verify deterministically, review with `code-reviewer`, and
   allow at most 2 correction rounds.
3. Stay inside the phase. Anything outside its contract gets reported, not
   built. Never write tests, never add dependencies, never run destructive DB
   commands, never use `->poll()`.
4. When it passes: set `Status: DONE` and add a short `Evidence:` line under
   it (checks run and their results, the observable outcome), then update
   the status board.
5. If it fails or needs a decision: set `Status: BLOCKED` with a one-line
   reason plus options and a recommendation, then stop. Don't move on to
   later requested phases that depend on it.

Keep the owner oriented with one line per boundary, e.g.
"Phase 4 DONE — 4/13 phases".

If your remaining budget looks too small for the next requested phase, stop
cleanly after the current one and say which phases are left. Never leave a
phase half done.

## 4. Stop and report

After the last requested phase (or a block), stop. Don't start other phases.
Report:

- the phases done and blocked, with evidence;
- the ACs now satisfied, as far as the executed phases go;
- non-blocking review findings left open;
- anything the owner should check manually now;
- the next dependency-ready phase and the exact command to run it;
- a reminder that nothing was committed.
