---
description: Read the spec, compare it with what is already implemented and write plan.md in phases with what's missing to align the code with the spec
argument-hint: <feature-slug> [extra instructions]
model: claude-opus-5-5
---

# /plan-feature $ARGUMENTS

Takes the feature name (folder in `docs/features/`) as the first argument
(e.g. `/plan-feature saved-searches`). Anything after it is extra instructions
from the owner.

Don't implement anything at this stage. Only plan. Don't install anything,
don't run migrations or seeders, never run git writes (`CLAUDE.md`).

## Steps

1. Read `docs/features/<feature>/spec.md`. If it doesn't exist, tell the owner
   and stop: this command doesn't create the spec, only the plan from it. The
   plan is saved to `docs/features/<feature>/plan.md`, next to the spec.
2. Read the whole spec and extract the list of **verifiable requirements**:
   behaviour, screens (Filament and Inertia), routes, controllers and props,
   columns and enums, business rules, copy (EN/PT), states (loading, error,
   empty), permissions, realtime updates, expected tests and acceptance
   criteria. If there is a `## Pending changes` section, it is the **target**
   of the plan (what the owner wants to change); the rest of the spec
   describes the current state and serves as context and as invariants to
   preserve. Without that section, the whole spec is the target. Also read the
   files in `assets/` the spec links (images, PDFs), because they define
   requirements.
3. Read the relevant code (grep for key terms, routes, models, resources,
   pages, components, jobs). Load `job-collection` if the feature touches
   sources, adapters, collection runs, job postings or "today's jobs", and
   `project-core` for the verification commands that really exist. Audit what
   the plan depends on: package versions (`composer show`, `package.json`),
   config, `.env` key names (never values), table schemas (Boost
   `database-schema`), and existing models/resources/pages/routes.
4. Determine the state of each requirement by comparing spec x current code:

    - **Implemented and aligned:** the code already does what the spec
      defines. Not in the plan.
    - **Partial:** exists, but part of the defined behaviour is missing.
    - **Divergent:** the code does something different from what the spec
      defines.
    - **Missing:** nothing implemented yet.
    - **Unverifiable:** couldn't be confirmed (explain why).

    A new spec usually comes out almost all **Missing**; an existing one, a
    mix. Use `git log` / `git blame` on related files to understand recent
    changes; the conclusion always comes from reading the current code. For
    large work you may split the reading across read-only `Explore` agents in
    parallel (one per area).

5. Present to the owner, before writing the plan:

    - **Diagnosis**: table spec requirement -> state (implemented / partial /
      divergent / missing / unverifiable), with the evidence file when there is
      one.
    - **Ambiguities**: points of the spec that allow more than one reading.
    - **Contradictions**: passages that conflict with each other or with the
      existing code/conventions.
    - **Gaps**: behaviour not specified that the plan would need to assume.
    - **Divergences**: for each **Divergent** item that is not in
      `## Pending changes`, say whether its code was changed recently by hand
      (`git log` / `git blame`). Code wins over the spec body (`CLAUDE.md`), so
      never plan to revert it silently: ask the owner, per item, whether the
      code should follow the spec (it becomes a phase, and the decision is
      recorded in `## Pending changes`) or the spec should follow the code
      (recommend `/update-feature-spec`; not in the plan).
    - **Code not backed by the spec**: existing behaviour the spec doesn't
      mention. Don't remove it or plan to remove it; just flag it so the owner
      can decide whether to update the spec (via `/update-feature-spec`).

    If an ambiguity, contradiction, divergence or gap changes the plan in a
    relevant way, **ask the owner and wait for the answer** before writing the
    plan. Record each decision in `spec.md` ("Requirements" or
    `## Pending changes`, as appropriate, in objective wording), so the spec
    remains the source of truth; that is the only spec edit allowed here. Minor
    points you need to assume go into the plan marked as `Assumption`. Anything
    that needs a new dependency, a destructive operation or a product choice is
    an owner decision, never assumed. If the owner can't answer now, record it
    in "Open points" of the spec, add it to `Owner decisions` in the plan and
    mark the affected phase `BLOCKED (Dn)`.

6. Break **only what's missing** (partial, divergent approved by the owner,
   missing) into **small phases**. Every phase:

    - **Small and isolated.** One coherent deliverable, one primary role
      (`laravel-backend`, `filament-admin` or `inertia-frontend`), about 8 or
      fewer files created or changed. If it doesn't fit comfortably in one
      session, split it.
    - **Leaves the repo working.** Migrations run, the app boots, and the
      deterministic checks pass at the end of each phase. No half-built pieces
      that only a later phase makes valid, no TODO stubs.
    - **Dependencies point backwards only.** A phase never needs work from a
      later phase.
    - **Origin.** Which `## Pending changes` items it serves (cite the id and
      text) or "spec x code gap" when it doesn't come from that section.
      `/exec-phase` uses this to know what to move from pending to implemented
      in the spec.
    - **Gap -> desired state.** What exists today and what changes.
    - **Self-contained.** Whoever executes starts with a clean context (a
      smaller model, without this conversation), so embed the exact details:
      file paths, columns and types, enum values, method signatures,
      channel/event names, UI copy strings (EN/PT), validation rules, the
      decisions already taken, assumptions, and the relevant spec/attachment
      excerpts. Never rely on "as agreed". Cite spec requirement ids for
      traceability.
    - **Verifiable.** A `Done when:` list of observable results plus the
      deterministic commands to run (only commands that exist).
    - The last phase is always **verification and report**: the full gate, a
      grep for forbidden patterns (`->poll()`, `wire:poll`, git writes,
      Spanish copy), and a requirement-by-requirement walkthrough with the
      owner's manual checklist.
    - Every target requirement maps to at least one phase. Check this before
      writing.

    If everything is already aligned, say so, don't write a plan and stop.

7. Save the plan to `docs/features/<feature>/plan.md` with exactly the
   structure below. If the plan already exists, read it first and preserve the
   phases already `DONE`, adjusting only what changed.
8. Present the plan summary to the owner (phase count, status board, open
   decisions with your recommendation) and stop. Name the first
   dependency-ready phase and the exact command:
   `/exec-phase <feature> <phase>`.

## plan.md structure

```markdown
# Plan — <feature title>

Spec: `docs/features/<feature>/spec.md` · SHA-256 `<hash of the spec>`
Run phases with `/exec-phase <feature> <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — <date>

Implemented: N · Partial: N · Divergent: N · Missing: N · Unverifiable: N

| Requirement | State | Evidence |
| ----------- | ----- | -------- |

## Status board

| Phase | Title | Role | Depends on | Origin | Size | Status |
| ----- | ----- | ---- | ---------- | ------ | ---- | ------ |

## Owner decisions

### D1 — <question>

Blocks: Phase N… · Options: A (recommended) …, B … · Why: … · Answer: …

## Global constraints (every phase)

-   <rules from the spec and CLAUDE.md the executor must never break>

## Requirement coverage

| Requirement | Phases |
| ----------- | ------ |

## Phases

### Phase N — <outcome>

Status: PENDING | IN_PROGRESS | DONE | BLOCKED (Dn)
Role: <agent> · Depends on: <phases|none> · Covers: <R/P ids> · Size: S|M
Origin: <P ids + text | spec x code gap>

**Goal.** <one or two sentences>

**Gap -> desired.** <what exists today -> what changes>

**Contract.**

-   <exact details>

**Steps.**

1. …

**Done when.**

-   <observable result>
-   <command> passes

**Not in this phase.** <things that belong to later phases>
```

Sizes: S is a few files in one layer; M is up to about 8 files, or two closely
related layers. Anything bigger has to be split.

## Rules

-   Don't change code. Only create/update `docs/features/<feature>/plan.md`; in
    `spec.md`, only record the owner's decisions (step 5).
-   Never run git writes.
-   Don't read `.env` files (use key names from `.env.example` or config).
-   Read only this feature's folder, never the whole `docs/features/`.
-   If the code breaks an invariant of the spec, treat it as **Divergent** and
    point it out to the owner.
