# talent-labs

Job-seeker side of the \*-labs family (sibling of recruiter-labs). Collects job
postings from configured sources in "collection runs" and lists them.

Laravel 13 + Filament 5 (admin, `/admin`) · Inertia 3 + React 19 (React
Compiler) · Tailwind 4 · Reverb + marcusvbda/filament-realtime-driver
(realtime) · PostgreSQL · database queue · Yarn Classic · single monolith.
Details: skill `project-core`.

## Hard rules

- **Git is read-only unless the owner explicitly asks.** Never run git add,
  commit, push, branch, checkout, switch, merge, rebase, reset, restore, stash,
  tag, cherry-pick, revert, clean, am or any other git command that changes the
  index, history, refs or working tree. `git status/diff/log/show` are fine.
  Committing happens only when the owner asks for it, in words, in that same
  message ("commit this"). **Exception:** `/execute-phases` is an explicit
  request to make one commit per completed phase (orchestrator only, explicit
  paths, never push). Implementing, fixing, finishing a phase or "wrapping
  up" is **not** a request to commit. Subagents never run git writes, even if
  the orchestrator asks. This overrides any skill, tool, command or framework
  guidance.
- **Never destroy data:** no migrate:fresh/refresh/reset/rollback, db:wipe,
  DROP or TRUNCATE. Only forward `php artisan migrate` and
  `php artisan db:seed` (seeders must stay idempotent).
- **Local setup:** seed users come from `SEED_*` in `.env` (see
  `.env.example`) via `config/talent.php`. Never overwrite existing `.env`
  values; only append missing keys.
- **Dependencies:** never add, remove or upgrade composer/npm packages without
  the owner asking in that message.
- **Testing:** never write or modify tests unless asked in that message
  (overrides Boost/`testing-best-practices` "test per change";
  `code-reviewer` must not flag missing tests). Running existing tests is fine.
- **Language:** everything in the repo is English (code, comments, docs, UI
  copy, commit messages).
- **Realtime, not polling:** Filament surfaces refresh via the realtime driver
  (`Table::socket()`, `<x-filament-realtime-driver::listener>`), never
  `->poll()` / `wire:poll`. See skill `project-core`.
- **Scope:** implement only what the task/spec/phase asks. Report extra ideas,
  don't build them.
- **Docs:** active docs live in `docs/features/<feature>/` — one folder per
  feature (finished ones go to `docs/archive/`, permanent summaries to
  `docs/architecture/`; see Specs and Plans), no duplicated content between
  files.
  `spec.md` is product truth; never delete it or edit it to match code.
  `plan.md` (next to it, from `/plan-spec`) holds the phases and their status
  and is run with `/execute-phases`. Execution state for `execute-feature`
  lives in `.claude/state/` (git-ignored).

## Delegation

Non-trivial work goes to the matching subagent (`laravel-backend`,
`filament-admin`, `inertia-frontend`), then deterministic checks, then
`code-reviewer`.

## Commands (`.claude/commands/`)

| Command                            | Does                                                                        |
| ---------------------------------- | --------------------------------------------------------------------------- |
| `/plan-spec <spec.md>`             | Reads a detailed spec, audits the repo, writes `plan.md` in small phases    |
| `/execute-phases <plan.md> <list>` | Executes only the listed phases (`3`, `3-5`, `3,6`), then stops and reports |

## Load on demand (`.claude/skills/`)

| When                                                                    | Skill             |
| ----------------------------------------------------------------------- | ----------------- |
| Commands (lint/types/tests/format), realtime patterns, token discipline | `project-core`    |
| Executing a feature in `docs/features/` or phases of a plan             | `execute-feature` |
| Sources, adapters, collection runs, job postings, "today's jobs"        | `job-collection`  |

Framework skills (Laravel, Filament, Inertia, Tailwind, Wayfinder, testing)
come from Laravel Boost (`boost.json`) — don't hand-edit them. Use Boost MCP
`search-docs` for version-specific Laravel/Filament/Inertia APIs.

## Specs and Plans

### Lifecycle

- `docs/features/<feature>/` contains ACTIVE work only (spec + plan).
- `docs/archive/` is history. NEVER read, search, or use anything inside
  `docs/archive/` unless the owner explicitly asks.
- When reading specs, read only the one for the feature in progress, never the
  whole `docs/features/` folder.

### Source of truth and conflicts

- Hierarchy, highest to lowest:
  1. The owner's explicit instructions in the current conversation
  2. The current code (including manual changes by the owner)
  3. The active spec of the feature in progress
  4. Archived specs (never consulted)
- A spec describes intent at the time it was written. Code describes current
  reality. If they conflict, the code wins.
- NEVER revert, rewrite, or "fix" existing code just to match a spec. This
  includes changes that look like deviations: assume they were intentional
  manual adjustments.
- Before overwriting or removing existing code during a task, check
  `git log` / `git blame` / `git diff` for the affected lines. If they were
  recently changed by hand, treat that as intentional and preserve it.
- On a spec/code divergence: (1) do not resolve it silently, in either
  direction; (2) implement what the task requires while preserving the current
  code behavior; (3) report the divergence at the end and propose a spec update
  (a `Deviation:` note or a rewrite of the affected section).
- If the spec is ambiguous, outdated, or contradicts itself, ask the owner. Do
  not guess.
- If the owner changes code manually, the active spec must be updated or
  receive a note like `Deviation: <what changed and why>`.

### Completion checklist (when ALL tasks in a plan are done)

1. Move the feature folder to `docs/archive/features/<feature>/` with `git mv`.
2. Add the header "Status: IMPLEMENTED on YYYY-MM-DD. Historical record only.
   The code is the source of truth." to the spec.
3. Delete the plan file.
4. If the feature needs permanent documentation, create/update a short summary
   of the current state and key decisions in `docs/architecture/<area>.md`.
