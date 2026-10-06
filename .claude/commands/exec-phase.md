---
description: Execute one or more phases of docs/features/<feature>/plan.md through the execute-feature orchestrator, running the checks and making one commit and push per phase
argument-hint: <feature-slug> <phases: N | N-M | N,M | N M | next>
model: claude-sonnet-5-5
---

# /exec-phase $ARGUMENTS

Takes `<feature> <phases>` in `$ARGUMENTS`, where `<feature>` is the folder in
`docs/features/`. `<phases>` may be one phase or several (e.g.
`/exec-phase saved-searches 2`, `/exec-phase saved-searches 1 2 3`,
`/exec-phase saved-searches 1-3`, `/exec-phase saved-searches 2,4`) or `next`
(the first `PENDING` phase whose dependencies are all `DONE`). Run nothing
outside that list, even if it's small or obviously next.

**Unattended by default.** The owner often leaves this running overnight.
Never pause to ask "should I continue?", for confirmation to commit, or for
approval between phases. Stop only on a real block (failed checks after the
correction rounds, or an open `BLOCKED (Dn)` decision) and say so in the
report.

## Before any edit

1. Confirm `docs/features/<feature>/spec.md` and `docs/features/<feature>/plan.md`
   exist. If not, tell the owner and stop.
2. Read the plan's header, `Owner decisions`, `Global constraints` and the
   requested phases in full. For each requested phase:

    - It exists, and its status is `PENDING` or an interrupted `IN_PROGRESS`
      (reset it to `PENDING`). Already `DONE` → skip it and say so.
    - Every `Depends on:` phase is `DONE` (a dependency that is also requested
      and comes earlier counts once it finishes).
    - It's not `BLOCKED (Dn)`. If Dn is still open, stop and restate the
      decision with its options. Continue only if the owner's message resolves
      it; then record the answer under that decision in the plan.

    If the gate fails, stop and report. Never run a phase partially.

3. Run `git status`. Note any uncommitted changes that already exist: they are
   not yours and never go into a phase commit.

## Execution

1. Load the `execute-feature` skill (plan mode) and follow it **in this
   session** (the orchestrator is you, not a subagent), with `spec.md` as the
   product truth and each phase's **Contract** as the executor's brief.
2. Run the requested phases **one at a time, in order**.
3. When **each** phase finishes, before starting the next:
    1. Set `Status: DONE` in the phase and on the status board, with a short
       `Evidence:` line under it (checks and tests run with results, the
       observable outcome).
    2. **If this is the last phase of `plan.md`** (every other phase already
       `DONE` and this one just marked): update `docs/features/<feature>/spec.md`.
       For each `## Pending changes` item cited as the `Origin` of some phase,
       fold it into the spec body (in the right section, in the document's
       style, as implemented behaviour, checked against the code) and remove it
       from the section. Pending items no phase covered stay where they are. If
       the section ends up empty, remove it. Only edit the spec in this
       situation. The spec goes into this phase's commit.
    3. Run the project's existing tests plus the deterministic checks from
       `project-core` for the changed files (running tests is fine; never
       write or modify them). If something fails, fix it through the
       correction loop. If you can't, set `Status: BLOCKED` with a one-line
       reason, options and a recommendation, **don't commit**, and stop
       without moving to the next phase.
    4. Make **one commit per phase**. Stage only this phase's files by explicit
       path (including `plan.md`, and `spec.md` on the last phase), never
       `git add -A` or `.`; never stage pre-existing unrelated changes or
       secrets (`.env`). Don't use `--no-verify`; if a hook fails, fix the
       cause and make a new commit, never amend.
    5. Commit message, in English, conventional style matching the repo log:
        - Subject: `<type>(<scope>): <phase outcome> (phase <N>)`, e.g.
          `feat(review): add Approve all to the review modal (phase 2)`.
        - Body: what was done and why (new or changed behaviour, main
          files/areas, relevant decisions), in short lines. Don't list the diff.
        - Trailer: `Spec: docs/features/<feature>`, then the attribution line
          from the session's system-reminder.
    6. `git push` (plain, current branch). If the push fails, tell the owner
       and stop, without moving to the next phase.
4. Keep the owner oriented with one line per boundary, e.g.
   "Phase 4 DONE — committed abc1234, pushed — 4/9 phases". If your remaining
   budget looks too small for the next phase, stop cleanly after the current
   one and say which phases are left.

## Report

After the last requested phase (or a block), stop. Report:

-   phases done and blocked, with evidence;
-   requirements now satisfied, as far as the executed phases go;
-   non-blocking review findings left open;
-   what the owner should check manually now;
-   the commits made (hash + subject per phase) and whether each was pushed;
-   the next dependency-ready phase and the exact command to run it;
-   if the last phase of the plan ran: that the pending changes covered by the
    plan were moved into the spec body, that `plan.md` can now be deleted by
    the owner, and that `/update-feature-spec <feature>` is only needed to
    reconcile the spec with the code.

## Commit rules

-   This command is the owner's explicit request to commit and push, and it
    covers only the phases listed in `$ARGUMENTS`.
-   Only the orchestrator commits. Subagents never run git writes.
-   Plain `git push` of the current branch only. Never `--force` /
    `--force-with-lease`, never branch, switch, amend, reset, stash or touch
    other refs.
-   Never commit `.env` files or secrets.
