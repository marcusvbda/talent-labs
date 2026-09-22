---
name: project-core
description: Stack detail, the exact lint/static-analysis/type/test/format commands that exist in this repo, realtime (no-polling) patterns, and token discipline. Load before verifying a change or choosing how to verify it.
---

# Project core

Supports `CLAUDE.md`, never replaces it. Global rules (git, data, deps, tests,
language) stay there.

## Stack detail

- Laravel 13, PHP 8.4, PostgreSQL, `QUEUE_CONNECTION=database`.
- Filament 5 panel `admin` at `/admin` (`app/Providers/Filament/AdminPanelProvider.php`).
- Inertia 3 + React 19, React Compiler via `@rolldown/plugin-babel` +
  `reactCompilerPreset()` — no speculative `useMemo`/`useCallback`/`React.memo`.
- Tailwind 4, Wayfinder (`@/routes`, `@/actions`, generated on `yarn dev`).
- Vite+ (`vp`) drives dev/build/check (`vite.config.ts` holds lint/fmt config).
- **Package manager: Yarn Classic (1.x).** Use `yarn`, not `npm install`.
  Yarn 1 has no `dlx`, so `app/Console/Commands/DevCommand.php` overrides
  `php artisan dev` to run the registered `DevCommands` through the local
  `concurrently` binary instead of `@laravel/multiplex`.
- `composer dev` → `php artisan dev` → every `DevCommands` registration
  (server, queue, logs, vite, reverb…). Extra processes are registered with
  `DevCommands::artisan('<command>', '<name>')` in
  `AppServiceProvider::boot()` under `runningInConsole()`; registering an
  existing name (`queue`, `reverb`) replaces the default.

## Deterministic verification commands

Only these exist (`composer.json` / `package.json`). Don't invent others. Run
the narrowest one that covers the change, **before** any AI review.

### PHP changed

| Purpose              | Command                                                   |
| -------------------- | --------------------------------------------------------- |
| Format changed files | `vendor/bin/pint --dirty --format agent`                  |
| Format check only    | `composer lint:check`                                     |
| Static analysis      | `composer types:check` (PHPStan/Larastan, level 7)        |
| Focused tests        | `php artisan test --compact --filter=<name>`              |
| Full PHP gate        | `composer test` (config:clear, lint:check, types, tests)  |

### Frontend changed (`resources/js/**`, CSS, `vite.config.ts`)

| Purpose                     | Command                                  |
| --------------------------- | ---------------------------------------- |
| Format + lint + types       | `yarn check` (`vp check`)                |
| Auto-fix format/lint        | `yarn check:fix`                         |
| Types only                  | `yarn types:check` (`tsc --noEmit`)      |
| Build (bundle at risk only) | `yarn build`                             |

### Final gate

`composer ci:check` (frontend check + types + PHP gate). Use once per
feature/final phase, not per task.

Notes: running existing tests is always fine; creating/modifying tests is not
(see `CLAUDE.md`). If a UI change doesn't show up, the owner may need
`composer dev` or `yarn build` — ask.

## Realtime (replaces polling)

- Panel: `FilamentRealtimeDriverPlugin::make()->socket()->databaseNotifications()`
  plus `$panel->databaseNotifications()` — already configured; don't duplicate.
- Tables: `->socket(channel: 'x', event: 'XUpdated')`. Never `->poll()`,
  `wire:poll` or `$pollingInterval`.
- Livewire pages/infolists: `<x-filament-realtime-driver::listener
  channel="..." event="..." callback="$wire.$refresh()" />`.
- Emit: `Marcusvbda\FilamentRealtimeDriver\RealtimeEvent::dispatch($channel,
  $event, ['id' => $id])` from model `booted()` `saved`/`deleted` hooks, and
  **explicitly** after query-builder writes (`upsert`, bulk `update`,
  `saveQuietly`), which skip model events.
- `RealtimeEvent` is `ShouldBroadcastNow` — no queue worker needed to emit.
- Notifications: `Notification::make()->...->sendToDatabase($user, isEventDispatched: true)`.
- Public channels carry ids / refresh signals only, never sensitive payloads.
- Reference: `vendor/marcusvbda/filament-realtime-driver/README.md`.
- Reverb server: `php artisan reverb:start` (port 8080); the driver reads
  `FILAMENT_REALTIME_SERVER` (default `localhost:8080`).

## Tooling

Prefer Boost MCP tools (`database-schema`, `database-query`, `search-docs`,
`browser-logs`, `last-error`) over shell equivalents, and `search-docs` over
guessing framework APIs.

## Token discipline

- Load a skill only when its domain is relevant.
- Review the task diff, not the repository.
- Deterministic checks first; AI review for judgement only.
- One integrated review per feature/plan end, not a full-repo review per task.
- A stalled subagent gets **one** resume max; then verify the concern directly
  with a targeted `Read`/`Grep`.
- Don't split a review preemptively; split only after one pass ran out of
  budget.
- **Embed contracts in delegation prompts** (signatures, column lists, AC text,
  invariants) instead of telling a subagent to go read `docs/` or skills —
  that is what burns its turn budget before it writes anything.
