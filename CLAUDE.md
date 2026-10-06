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
  message ("commit this"). **Exception:** `/exec-phase` is an explicit
  request to make one commit per completed phase, each followed by a plain
  `git push` (orchestrator only, explicit paths, never force-push).
  Implementing, fixing, finishing a phase or "wrapping up" is **not** a
  request to commit. Subagents never run git writes, even if
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
- **Docs:** feature docs live in `docs/features/<feature>/` — one folder per
  feature (see Specs and Plans and `docs/features/README.md`), no duplicated
  content between files: `spec.md` (living product doc, never deleted),
  `plan.md` (transient phases from `/plan-feature`, run with `/exec-phase`)
  and `assets/` (attachments). Execution state for `execute-feature` lives in
  `.claude/state/` (git-ignored).

## Delegation

Non-trivial work goes to the matching subagent (`laravel-backend`,
`filament-admin`, `inertia-frontend`), then deterministic checks, then
`code-reviewer`.

## Commands (`.claude/commands/`)

| Command                              | Does                                                                                         |
| ------------------------------------ | -------------------------------------------------------------------------------------------- |
| `/create-feature-spec <feature>`     | Creates/opens `spec.md` and collects the owner's items one by one (attachments in `assets/`) |
| `/update-feature-spec <feature>`     | Reconciles `spec.md` with the code (code wins); fills a skeleton spec from the code          |
| `/plan-feature <feature>`            | Diagnoses spec x code, asks what changes the plan, writes `plan.md` in small phases          |
| `/exec-phase <feature> <list\|next>` | Executes only the listed phases (`3`, `3-5`, `3,6`), one commit + push each, then reports    |

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

- `docs/features/<feature>/spec.md` is permanent and describes the feature.
  Its body is the current behaviour; `## Pending changes` holds what the
  owner wants changed and isn't implemented yet (for a new feature,
  "Requirements" is the target until built). The two never mix.
- `plan.md` is transient. After the last phase, `/exec-phase` folds the
  pending changes the plan covered into the spec body; the owner then deletes
  `plan.md` manually. Never move or delete spec/plan files yourself.
- When reading specs, read only the one for the feature in progress, never the
  whole `docs/features/` folder.

### Source of truth and conflicts

- Hierarchy, highest to lowest:
    1. The owner's explicit instructions in the current conversation
    2. The current code (including manual changes by the owner)
    3. The spec body of the feature in progress
- `## Pending changes` (and the "Requirements" of a feature not yet built) is
  the owner's recorded intent and the target of `/plan-feature`, not a claim
  about the current code.
- Where the spec body and the code conflict, the code wins. NEVER revert,
  rewrite, or "fix" existing code just to match the spec body; assume
  deviations were intentional manual adjustments. Only the owner can decide,
  in `/plan-feature`, that the code should follow the spec.
- Before overwriting or removing existing code during a task, check
  `git log` / `git blame` / `git diff` for the affected lines. If they were
  recently changed by hand, treat that as intentional and preserve it.
- The spec is edited only through the commands: `/create-feature-spec`
  (owner's items), `/update-feature-spec` (reconcile with code),
  `/plan-feature` (owner's decisions) and `/exec-phase` (fold implemented
  pending changes after the last phase). Outside them, on a spec/code
  divergence: don't resolve it silently, implement what the task requires
  while preserving current behaviour, and report it recommending
  `/update-feature-spec <feature>`.
- If the spec is ambiguous, outdated, or contradicts itself, ask the owner. Do
  not guess.
- If the owner changes code manually, the spec is brought up to date with
  `/update-feature-spec <feature>`.
