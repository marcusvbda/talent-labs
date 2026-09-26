---
description: Read a detailed spec, audit the repo read-only, and write plan.md (next to the spec) split into small, isolated, independently executable phases.
argument-hint: <path/to/spec.md> [extra instructions]
---

# Plan a spec into phases

Spec: `$1`
Extra instructions from the owner: $ARGUMENTS

You are **planning only**. Do not implement anything, do not install anything,
do not run migrations or seeders, and never run git writes (`CLAUDE.md`). The
only file you write is `plan.md` in the same directory as the spec — unless
the owner's extra instructions explicitly ask you to execute a named part of
the spec now; do exactly that part and nothing more, and record it as a `DONE`
phase in the plan.

## 1. Read

0. Docs live only in `docs/features/<feature>/`. If the spec is elsewhere,
   ask the owner to move it there, or propose the target folder. Never copy
   spec content into a second file; the plan references spec sections
   instead.
1. Read the whole spec end to end before anything else. Identify: goal,
   ground rules, data model, flows, UI surfaces, acceptance criteria,
   verification steps, out of scope.
2. If the spec has no acceptance criteria, or has placeholders or unfinished
   sections, stop and list what's missing. Do not write a plan around the gaps.

## 2. Audit (read-only)

Check what the plan depends on, and whether it is actually there:
framework and package versions (`composer show`, `package.json`), config,
`.env` keys (names only, never print secrets), `migrate:status` and table
schemas (Boost `database-schema`), existing models/resources/pages/routes,
and the verification commands that really exist (`composer.json` and
`package.json` scripts). Note what the spec assumes but the repo lacks.

Anything missing that would need a new dependency, a destructive operation, or
a product choice becomes an **owner decision**. Do not make those choices
silently.

## 3. Design the phases

Rules for every phase:

- **Small and isolated.** One coherent deliverable, one primary role
  (`laravel-backend`, `filament-admin` or `inertia-frontend`), and about 8 or
  fewer files created or changed. It has to fit comfortably in one session. If
  it doesn't, split it.
- **Leaves the repo working.** Migrations run, the app boots, and the
  deterministic checks pass at the end of each phase. No half-built pieces
  that only a later phase makes valid, and no TODO stubs.
- **Dependencies point backwards only.** A phase never needs work from a
  later phase. Order it so each phase's prerequisites already exist, e.g. the
  resource a notification links to comes before the notification.
- **Self-contained contract.** Embed the exact details the executor needs:
  columns and types, enum values, method signatures, channel/event names,
  UI copy strings, validation rules, and the AC text. That way the executor
  doesn't have to re-read the spec. Cite spec sections (`B.5`) for traceability.
- **Verifiable.** A `Done when:` list of observable results plus the
  deterministic commands to run (only commands that exist).
- Phases blocked by an owner decision are marked `BLOCKED (Dn)` and kept
  as small as the rest.
- The last phase is always **verification and report**: the full gate, the
  spec's smoke tests, a grep for forbidden patterns, the AC walkthrough, and
  the owner's manual checklist.
- Every acceptance criterion maps to at least one phase. Check this before
  writing.

## 4. Write `plan.md` next to the spec

Use exactly this structure:

```markdown
# Plan — <spec title>

Source spec: `<path>` · SHA-256 `<hash of the spec>`
Product truth: `<path>` (if the spec says where the product part lives)
Run phases with `/execute-phases <this plan path> <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title | Role | Depends on | Size | Status |
| ----- | ----- | ---- | ---------- | ---- | ------ |

## Audit — <date>

| Check | Result |

## Owner decisions

### D1 — <question>

Blocks: Phase N… · Options: A (recommended) …, B …, C … · Why: …

## Global constraints (every phase)

- <rules from the spec and CLAUDE.md that the executor must never break>

## Acceptance-criteria coverage

| AC | Phases |

## Phases

### Phase N — <outcome>

Status: PENDING | DONE | BLOCKED (Dn)
Role: <agent> · Depends on: <phases|none> · Covers: <ACs> · Size: S|M
Spec: <sections>

**Goal.** <one or two sentences>

**Contract.**

- <exact details>

**Steps.**

1. …

**Done when.**

- <observable result>
- <command> passes

**Not in this phase.** <things that belong to later phases>
```

Sizes: S is a few files in one layer; M is up to about 8 files, or two closely
related layers. Anything bigger has to be split.

## 5. Report back

Give the plan path, the number of phases, the status board, and the owner
decisions still open (with your recommendation). Name the first
dependency-ready phase and the exact command to run it. Do not start executing
any phase.
