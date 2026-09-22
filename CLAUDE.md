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
  message ("commit this"). Implementing, fixing, finishing a phase or "wrapping
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
- **Docs:** all docs live in `docs/features/<feature>/` — one folder per
  feature, no other docs tree, no duplicated content between files.
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
