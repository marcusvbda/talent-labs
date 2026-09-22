---
name: execute-feature
description: Use when asked to execute, implement or continue a feature documented under docs/features/<feature>/, or phases of a plan.md produced by /plan-spec. Orchestrates delegate → verify → review → correct, and never commits.
---

# Execute feature

You are the **orchestrator**. Product behaviour comes from the spec; you decide
only implementation-level details (task boundaries, order, naming, small
extractions). Global rules in `CLAUDE.md` always win — in particular: **never
commit, never run git writes, never edit the spec to match code, never write
tests unless asked.**

## Two entry modes

| Mode                           | Task graph lives in                      | Status recorded in                      |
| ------------------------------ | ---------------------------------------- | --------------------------------------- |
| Feature (`docs/features/<f>/`) | `.claude/state/<f>.md` (you derive it)   | `.claude/state/<f>.md`                  |
| Plan phases (`/execute-phases`) | the phases of `plan.md` (already derived) | each phase's `Status:` line in `plan.md` |

## Entry gate

- Feature mode: `docs/features/<feature>/spec.md` must exist with acceptance
  criteria and no placeholders. Optional `tech-design.md` is binding when
  present. Missing/incomplete → **STOP**, report exactly what's missing.
- Plan mode: `plan.md` must exist; every requested phase must exist and have
  all `Depends on:` phases `DONE`. A phase `BLOCKED` on an owner decision →
  **STOP** and restate the decision needed.

## 0. Start or resume

1. Read the spec (feature mode) or the plan header + requested phases (plan
   mode) in full. Re-read spec sections a phase cites only when its embedded
   contract is insufficient.
2. Load `job-collection` for domain work.
3. Inspect the repo for what the task touches (not the whole repo).
4. Feature mode only: record `spec.md` SHA-256 and derive tasks into
   `.claude/state/<feature>.md` — each task: outcome, domain, depends on,
   covers ACs, completion evidence, `Status: PENDING`, `Correction round: 0`.
   Map every AC to at least one task before implementing.
5. Resume: any `IN_PROGRESS` → back to `PENDING`, then continue with the next
   dependency-ready `PENDING` item. Don't redo valid `DONE` work.

## 1. Per task / phase loop

1. **Scope** — mark `IN_PROGRESS`. Collect its ACs and embedded contract.
2. **Delegate** by scope (one task may run roles sequentially):

   | Scope                                                                | Role               |
   | -------------------------------------------------------------------- | ------------------ |
   | migration, model, enum, action, job, adapter, seeder, config, policy | `laravel-backend`  |
   | Filament resource, page, action, table/form/infolist, panel          | `filament-admin`   |
   | Inertia/React page or component, Tailwind                            | `inertia-frontend` |

   The prompt includes: the task, its AC text, the exact contract (columns,
   signatures, channel names, copy strings), the invariants that apply, and
   "implement only this scope; never run git writes".
3. **Verify** deterministically with the commands in `project-core` for the
   files actually changed. Failures go back to the same role before review.
4. **Review** with `code-reviewer`: task + AC text + contract + scoped diff
   (`git diff -- <paths>`, plus new untracked files) + check results.
5. **Close** — `APPROVED` → `DONE` with completion evidence (commands run,
   results, observable outcome). `CHANGES_REQUIRED` → correction loop.

Report one progress line at each boundary ("Phase 4 DONE — 4/13 phases").

## 2. Correction loop

Max **2** correction rounds per task (fix → verify → re-review). After the
second failed re-review stop patching. Continue automatically only if the fix
stays below the product boundary (different implementation, same behaviour).
Otherwise mark `BLOCKED` with the finding, options and a recommendation, and
stop. Stop-and-ask triggers: spec ambiguity, conflicting ACs, scope change, a
needed dependency, anything destructive.

## 3. Final review (last task of a feature / final plan phase)

1. Full diff + broader gate (`composer ci:check` or `composer test` + `yarn check`).
2. `code-reviewer` over all ACs and the full diff: ACs nobody owns, later tasks
   breaking earlier ones, duplicated logic, dead scaffolding.
3. Re-read the spec end to end as if new; any requirement not observably
   delivered becomes a new task through the loop above. Repeat until nothing
   is missing or a genuine blocker exists.

## 4. Report

Tasks/phases done, AC status, checks run with results, non-blocking findings
left open, blockers, what the owner should test manually. Remind that nothing
was committed. **Never commit, branch or push** unless the owner asks in that
exact message.
