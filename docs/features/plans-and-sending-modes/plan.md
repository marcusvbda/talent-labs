# Plan — plans-and-sending-modes: auto / select / review, spaced one-by-one sending, live stages, pause

Source spec: `docs/features/plans-and-sending-modes/spec.md` · SHA-256 `f6cc828bbcc50914fa2ecd14698bcce605259bd0d2cf3101ccadebef23e272c0`
Product truth: the spec above (Part 0 + Part B + acceptance criteria). Screen contracts:
`docs/features/client-app-screens/spec.md` (S1–S3, B.2) and `resources/js/types/contracts.ts`.
Run phases with `/execute-phases docs/features/plans-and-sending-modes/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                                                   | Role             | Depends on     | Size | Status       |
| ----- | ----------------------------------------------------------------------- | ---------------- | -------------- | ---- | ------------ |
| 1     | Outreach config, boot validation, sending enums                         | laravel-backend  | none           | M    | DONE         |
| 2     | Pause and stage columns, models, `AccountStatus.sending`, item stages   | laravel-backend  | 1              | M    | DONE         |
| 3     | `SendScheduler`: window and `nextSlot`                                  | laravel-backend  | 2              | S    | DONE         |
| 4     | `LiveSending` presenter, `SendingUpdated` event, `GET /internal/sending` | laravel-backend  | 3              | M    | PENDING      |
| 5     | Spaced queueing, job start guards, pause/resume core                    | laravel-backend  | 3, 4           | M    | PENDING      |
| 6     | Staged send pipeline (stages, sub-steps, pacing)                        | laravel-backend  | 5              | M    | PENDING      |
| 7     | Terminal effects: notifications, auto-pause, resume on reconnect        | laravel-backend  | 6              | M    | PENDING      |
| 8     | Pause/resume endpoints                                                  | laravel-backend  | 4, 5           | S    | PENDING      |
| 9     | `POST /internal/applications` (select only) + `QueueResult`             | laravel-backend  | 5              | M    | PENDING      |
| 10    | Review endpoints: drafts and reviewed queueing                          | laravel-backend  | 9              | M    | PENDING      |
| 11    | Automatic-mode dispatcher + scheduler in `composer dev`                 | laravel-backend  | 5              | S    | PENDING      |
| 12    | Admin Users: usage, sending badge, pause/resume actions                 | filament-admin   | 5              | S    | PENDING      |
| 13    | Admin Sending monitor page                                              | filament-admin   | 6              | M    | PENDING      |
| 14    | Frontend: live panel and pause/resume go real                           | inertia-frontend | 4, 8           | S    | PENDING      |
| 15    | Frontend: S1/S2/S3 send flows go real                                   | inertia-frontend | 9, 10, 14      | M    | PENDING      |
| 16    | Remove the legacy Filament `/app` panel                                 | laravel-backend  | 7, 11, 13, 15  | M    | PENDING      |
| 17    | Focused Pest tests (only with "write tests")                            | laravel-backend  | 6, 9, 10, 11   | M    | PENDING      |
| 18    | Verification and report                                                 | qa-tester        | 1–17           | S    | PENDING      |

Owner-only step (agents never run it): `php artisan migrate:fresh --seed` **after Phase 2** (the `users`
and `applications` create migrations gain columns). Phases 3–5 are statically checkable without it; any
runtime/manual check from Phase 3 on needs it.

## Audit — 2026-09-28

| Check                        | Result |
| ---------------------------- | ------ |
| Prerequisite specs           | `client-core-wiring`, `preferences-clarity`, `application-languages` are implemented (plans removed on completion; code present: `/internal/*` group, `PlanCatalog`, `ApplicationProfile`, `AccountStatusPresenter`, `ApplicationProgressed`/`AccountStatusUpdated`/`NotificationCreated`, `ClientNotification` base). |
| Config                       | `config/talent.php` `outreach` has only `intercept_to`. `plans.catalog.free.daily_limit` defaults to **10** (`.env.example` `PLAN_FREE_DAILY_LIMIT=10`) — spec Part 0.1 says **25**. `.env` has no `PLAN_*` and only `OUTREACH_INTERCEPT_TO` of the outreach keys. |
| Migrations                   | All 18 create migrations `Ran` (batch 1). `users` lacks `sending_paused_at`/`sending_pause_reason`; `applications` lacks `stage`/`sub_step`/`stage_log` and the `(user_id, status, scheduled_for)` index. Owner rule (memory): fold columns into create migrations; owner runs `migrate:fresh`. |
| Enums                        | `SendingPauseReason`, `SendStage`, `SendSubStep` do not exist. `ApplicationOrigin` has `Auto`/`Manual`. |
| Queueing                     | `QueueApplication::handle(User, JobPosting, ApplicationOrigin)` sets `scheduled_for = now()` and dispatches `SendApplicationEmail::dispatch($id)` immediately ("Sent right away" comment). Only caller besides future endpoints: `app/Filament/App/Pages/Jobs.php` (legacy panel) — the signature must stay compatible until Phase 16. |
| Send job                     | `SendApplicationEmail(int $applicationId)`, `tries 3`, `timeout 60`, `backoff [60,300,900]`, queue `outreach`; all checks + MIME build inside one locked transaction; no stages, no pause, no window. |
| Client contracts             | `LiveSending`, `ReviewDraft`, `QueueResult`, `SendStage`, `SubStep`, `AccountStatus.sending`, `NotificationType` all present in `resources/js/types/contracts.ts`; `sending.updated` in `resources/js/types/realtime.ts`, handler `sendingUpdated` in `data/realtime/handlers.ts`. |
| Backend gaps                 | No `/internal/sending*`, `/internal/applications` POST, `/drafts`, `/reviewed` routes. `AccountStatusPresenter` returns `sending: {paused: false, autoPausedReason: null}`. `ApplicationItemResource` returns `stage/subStep: null`. `ApplicationDetailResource.timeline` derived from `sent_at`/status only. No `SendingUpdated` event. No `application_failed`/`daily_limit_reached`/`sending_auto_paused` notification classes. |
| AC01 gap                     | `User` has no `saved` hook: a plan change in admin does **not** dispatch `account.updated` today. Phase 2 adds it. |
| Frontend                     | Send UI exists on fixtures: `features/dashboard/{live-sending-card,new-matches-card}.tsx`, `features/send/{send-bar,confirm-send-modal,auto-banner}.tsx`, `features/review/review-modal.tsx`, `pages/{dashboard,jobs}.tsx`. Hooks `use-live-sending`, `use-pause-sending`, `use-queue-applications`, `use-review-drafts`, `use-queue-reviewed` have **no `real`** source; `endpoints.ts` has hand-written placeholders for these 6 URLs. Dev emitter already only runs when `VITE_USE_FIXTURES=true` (`use-user-channel.ts`). `send.confirm.body` hardcodes "45–120 seconds". |
| Translations                 | `lang/en.json`/`pt.json` have `notifications.*`, `sending.stage.*`, `sending.sub.*`, `review.*`, `send.*`. Missing: rejection reasons for `QueueResult`, the mode 403 messages, `recipientLabel`, new error reasons (invalid content, invalid PDF). Never touch `lang/es.json` (memory). |
| Admin                        | `UsersTable` has `plan_key` badge, `sent_today_count` (via `countedToday`), no socket, no pause state. No `app/Filament/Pages` dir (admin discovers it); no `Outreach` navigation group yet. Realtime: `Application` broadcasts `applications`/`ApplicationsUpdated` on model save. |
| Legacy `/app` references     | Spec B.9 list **plus**: `ConnectedIntegrationTokenManager` sends a Filament database notification linking `Preferences::getUrl(panel: 'app')`; `bootstrap/app.php` excludes `'app', 'app/*'` from the branded Inertia error pages; Wayfinder output `resources/js/actions/App/Filament/App/*` (git-ignored, regenerated); `resources/views/filament/app/pages/{applications,jobs}.blade.php` + `partials/`. |
| Scheduler                    | `routes/console.php` has no schedule; no `scheduler` DevCommand. |
| Verification commands        | `composer lint:check`, `composer types:check`, `composer test`, `composer ci:check`, `yarn check`, `yarn types:check`, `yarn build`, `vendor/bin/pint --dirty --format agent`, `php artisan test --compact --filter=…`. Tests dir holds only `ExampleTest`s. |
| Dependencies                 | None needed. |

## Owner decisions

All three resolved by the owner on 2026-09-28: **option A** for each.

### D1 — Focused tests for the sending engine (spec "D-TESTS") — RESOLVED: A

Blocked: Phase 17 only · Options: A (recommended) write focused Pest tests (`SendScheduler`, quota, unique per
company, stale-dispatch and pause guards, mode enforcement) when you run Phase 17 with the words "write
tests"; B no tests, Phase 17 is dropped · Why: `CLAUDE.md` forbids tests unless asked in that message; the
spec recommends them because this code can burst or duplicate emails from real Gmail accounts.

### D2 — Send-time quota re-check fails (limit lowered after items were queued today) — RESOLVED: A

Blocked: Phase 6 · Options: A (recommended) keep the application `queued` and move it to the first window
slot of the next day (re-dispatch); B send it anyway — the queue-time reservation is final and the
re-check is dropped; C mark it `failed` ("Daily limit reached.") · Why: Part 0.7 says the new limit takes
effect immediately **and** "items already queued stay queued"; only A honors both. C loses that company
for the client forever (unique `(user, company)`). Under A the moved item does not consume the next day's
quota (the quota counts by `queued_at`, an unchanged guarantee). In all options the re-check counts only
**spent** rows: status `sending|sent|ambiguous` with `queued_at` today, compared to the current limit —
so the first (limit − spent) queued items still go out.

### D3 — Review drafts: links and addresses that come from the client's own template — RESOLVED: A

Blocked: Phase 10 · Options: A (recommended) keep `{{ job_url }}` as a token and run
`ClientSafeText::redact` only on the posting-derived values (`company`, `job_title`, `job_location`)
before rendering; the client's own template text (e.g. their portfolio link) is shown and sent as they
wrote it; B run `ClientSafeText::redact` on the whole rendered draft — the client's own links become
`[link]` and, if approved unchanged, are sent as `[link]` · Why: AC03 says drafts show no URL or email
address; `ClientSafeText` exists so the client cannot apply outside the platform, which only concerns
posting data. B would change the client's email.

## Global constraints (every phase)

- `CLAUDE.md`: no git writes; no destructive DB commands (`migrate:fresh` is owner-only, after Phase 2);
  no dependency changes; no tests outside Phase 17; English everywhere; realtime, never `->poll()` /
  `wire:poll`; implement only the phase.
- Schema changes go into the **create** migrations (`0001_01_01_000000_create_users_table.php`,
  `2026_09_24_100005_create_applications_table.php`); never add a new migration file.
- Translations: add keys to `lang/en.json` and `lang/pt.json` only; never touch `lang/es.json`.
- `.env` is never edited. `.env.example`: append missing keys (Phase 1 also sets the Free limit to 25).
- Unchanged guarantees (Part 0.6, AC09): one application per `(user, company)` forever (DB unique);
  `sent`/`ambiguous` are never resent; `ConnectionException` after the send started → `ambiguous`, any
  unexpected error after the send started → `ambiguous`; worker died mid-send (`sending` at job start) →
  `ambiguous`; one recipient by priority (`SelectRecipientForCompany`); `OUTREACH_INTERCEPT_TO` test mode
  (invalid value throws, never falls back to the real recipient); quota = `Application::countedToday()`
  (queued+sending+sent+ambiguous by `queued_at` today, app timezone).
- Never two applications in `sending` for the same user (AC02).
- Client payloads never carry the recipient address, contact data, provider ids, raw `last_error`, or
  posting URLs/emails; errors go through `ClientErrorMessage`.
- Client realtime is best-effort: dispatch through `DispatchesClientEvent` so a broadcast failure never
  fails a write. Query-builder writes skip model events → dispatch explicitly.
- `QueueApplication::handle(User, JobPosting, ApplicationOrigin)` stays callable with those three
  arguments until Phase 16 (the legacy `/app` Jobs page uses it); new parameters are optional.
- `SendApplicationEmail` must finish well inside `timeout = 60` (below the database queue's
  `retry_after` 90): ≤ 10 paced sub-steps × `step_delay_ms` (≤ 3000) + the Gmail call.

## Acceptance-criteria coverage

| AC   | Phases              |
| ---- | ------------------- |
| AC01 | 2, 12, 18           |
| AC02 | 3, 5, 6, 9, 15, 18  |
| AC03 | 10, 15, 18          |
| AC04 | 9, 11, 18           |
| AC05 | 2, 6, 14, 18        |
| AC06 | 3, 4, 5, 18         |
| AC07 | 5, 8, 14, 18        |
| AC08 | 7, 18               |
| AC09 | 5, 6, 18            |
| AC10 | 12, 13, 18          |
| AC11 | 16, 18              |

## Phases

### Phase 1 — Outreach config, boot validation, sending enums

Status: DONE
Evidence: `composer lint:check` and `composer types:check` pass. `php artisan about` boots; with
`OUTREACH_SEND_INTERVAL_MIN_SECONDS=2` it fails with "OUTREACH_SEND_INTERVAL_MIN_SECONDS must be at
least 5 (got 2)"; with `OUTREACH_STEP_DELAY_MS=5000` it fails similarly. `SendStage`/`SendSubStep`
backing values verified 1:1 against `resources/js/types/contracts.ts`. `code-reviewer`: APPROVED, no
blocking findings.
Role: laravel-backend · Depends on: none · Covers: groundwork for AC02, AC05, AC06, AC08 · Size: M
Spec: Part 0.1–0.5, B.2, B.3 (enums)

**Goal.** All sending knobs live in config with safe boot validation, and the three enums exist.

**Contract.**

- `config/talent.php` → `outreach` (keep the `intercept_to` comment):
  ```php
  'intercept_to' => env('OUTREACH_INTERCEPT_TO'),
  'interval_min_seconds' => (int) env('OUTREACH_SEND_INTERVAL_MIN_SECONDS', 45),
  'interval_max_seconds' => (int) env('OUTREACH_SEND_INTERVAL_MAX_SECONDS', 120),
  'step_delay_ms' => (int) env('OUTREACH_STEP_DELAY_MS', 1200),
  'window' => [
      'enabled' => (bool) env('OUTREACH_WINDOW_ENABLED', true),
      'start' => env('OUTREACH_WINDOW_START', '08:00'),
      'end' => env('OUTREACH_WINDOW_END', '19:00'),
      'weekdays_only' => (bool) env('OUTREACH_WINDOW_WEEKDAYS_ONLY', true),
  ],
  'auto_pause_after_failures' => (int) env('OUTREACH_AUTO_PAUSE_AFTER_FAILURES', 3),
  ```
- `plans.catalog.free.daily_limit` default `25` (Part 0.1); `.env.example` `PLAN_FREE_DAILY_LIMIT=25`.
- `.env.example`: append after `OUTREACH_INTERCEPT_TO=`: `OUTREACH_SEND_INTERVAL_MIN_SECONDS=45`,
  `OUTREACH_SEND_INTERVAL_MAX_SECONDS=120`, `OUTREACH_STEP_DELAY_MS=1200`, `OUTREACH_WINDOW_ENABLED=true`,
  `OUTREACH_WINDOW_START=08:00`, `OUTREACH_WINDOW_END=19:00`, `OUTREACH_WINDOW_WEEKDAYS_ONLY=true`,
  `OUTREACH_AUTO_PAUSE_AFTER_FAILURES=3`.
- `App\Outreach\Support\OutreachConfig` (final, static): typed readers `intervalMinSeconds(): int`,
  `intervalMaxSeconds(): int`, `averageIntervalSeconds(): int` (`intdiv(min + max, 2)`),
  `stepDelayMs(): int`, `windowEnabled(): bool`, `windowStart(): string`, `windowEnd(): string`,
  `weekdaysOnly(): bool`, `autoPauseAfterFailures(): int`; `validate(): void` throws
  `InvalidArgumentException` with a clear message when `min < 5`, `min > max`, or `step_delay_ms > 3000`.
- `AppServiceProvider::boot()` calls `OutreachConfig::validate()` in every environment.
- `App\Enums\SendingPauseReason: string` (`HasLabel`, `HasColor`): `Manual = 'manual'` ("Paused manually",
  gray), `ReauthorizationRequired = 'reauthorization_required'` ("Gmail reauthorization", warning),
  `RepeatedFailures = 'repeated_failures'` ("Repeated failures", danger).
- `App\Enums\SendStage: string` (`HasLabel`): `validating_recipient`, `adapting_template`,
  `attaching_cv`, `sending`, `sent`, `failed` (cases `ValidatingRecipient`, `AdaptingTemplate`,
  `AttachingCv`, `Sending`, `Sent`, `Failed`).
- `App\Enums\SendSubStep: string` (`HasLabel`) + `stage(): SendStage`: `checking_company`,
  `confirming_recipient`, `checking_gmail` → validating_recipient; `filling_variables`, `building_html` →
  adapting_template; `opening_cv`, `checking_pdf`, `attaching_file` → attaching_cv; `connecting_gmail`,
  `delivering` → sending. Values must equal the TS `SubStep`/`SendStage` unions.

**Steps.**

1. Config + `.env.example` edits (never `.env`).
2. `OutreachConfig` + boot call.
3. Three enums.
4. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- `php artisan about` boots; with `OUTREACH_SEND_INTERVAL_MIN_SECONDS=200` exported in the shell,
  `php artisan about` fails with the validation message.
- `composer lint:check` and `composer types:check` pass.

**Not in this phase.** Columns, scheduling, any use of the enums.

---

### Phase 2 — Pause and stage columns, models, `AccountStatus.sending`, item stages

Status: DONE
Evidence: `composer lint:check` and `composer types:check` pass. Only the two create migrations were
edited (`git status --porcelain database/migrations` confirms no new file). `code-reviewer`: APPROVED,
no blocking findings. Owner still needs to run `php artisan migrate:fresh --seed` before any runtime
check of these columns (not run by the agent).
Role: laravel-backend · Depends on: 1 · Covers: AC01, AC05 (data), AC07 (state) · Size: M
Spec: B.3, B.7 ("`AccountStatus.sending` becomes real"), Part 0.7

**Goal.** The data model carries pause state and live stages, and the client contracts read them.

**Contract.**

- `users` create migration, after `plan_key`: `timestamp('sending_paused_at')->nullable()`,
  `string('sending_pause_reason', 32)->nullable()`.
- `applications` create migration: `string('stage', 32)->nullable()`, `string('sub_step', 32)->nullable()`,
  `jsonb('stage_log')->default(new Expression("'[]'::jsonb"))` (list of `{stage, at}`), and
  `index(['user_id', 'status', 'scheduled_for'])`.
- `User`: casts `sending_paused_at` → `datetime`, `sending_pause_reason` → `SendingPauseReason`;
  `@property` docs; **not** added to `#[Fillable]` (written with `forceFill`);
  `isSendingPaused(): bool`. `booted()`: on `saved`, when `wasChanged(['plan_key', 'sending_paused_at',
  'sending_pause_reason'])` → `dispatchAccountStatusUpdated($user->id)` (use the `DispatchesClientEvent`
  trait). This is what makes AC01 live.
- `Application`: casts `stage` → `SendStage`, `sub_step` → `SendSubStep`, `stage_log` → `array`;
  `@property` docs; not fillable.
- `AccountStatusPresenter` `sending`: `paused` = `$user->isSendingPaused()`; `autoPausedReason` = the
  reason value when it is `reauthorization_required` or `repeated_failures`, else `null`.
- `ApplicationItemResource`: `'stage' => $application->stage?->value`,
  `'subStep' => $application->sub_step?->value`.
- `ApplicationDetailResource::timeline()`: when `stage_log` is non-empty, return it as
  `list<{stage: string, at: string}>` (ISO 8601); otherwise keep today's derivation.

**Steps.**

1. Edit both create migrations.
2. Models, presenter, resources.
3. Pint. Tell the owner to run `php artisan migrate:fresh --seed` (never run it).

**Done when.**

- `composer lint:check`, `composer types:check` pass.
- After the owner's fresh migrate: Boost `database-schema` shows the new columns/index;
  `GET /internal/account/status` returns `sending.paused=false`; changing a user's plan in `/admin` emits
  `account.updated` on `private-App.Models.User.{id}` (Reverb debug log).

**Not in this phase.** Writing stages (Phase 6), pausing (Phase 5).

---

### Phase 3 — `SendScheduler`: window and `nextSlot`

Status: DONE
Evidence: `composer lint:check` and `composer types:check` pass. `grep -rn "SendScheduler" app` shows
no callers yet (class only). Window math verified in a scratch tinker script (weekend skip, window
disabled, boundary cases). `code-reviewer`: APPROVED, no blocking findings; the one non-blocking note
(raw `max()` values parsed with `config('app.timezone')`) is moot since `config/app.php` hardcodes
`'timezone' => 'UTC'` with no env override. `anchor`/spacing math against real `Application` rows still
needs the owner's `php artisan migrate:fresh --seed`.
Role: laravel-backend · Depends on: 2 · Covers: AC02, AC06 · Size: S
Spec: Part 0.2–0.3, B.4 (`nextSlot`), B.2 (window timezone)

**Goal.** One class computes every send time: spaced, never a burst, inside the client's window.

**Contract.** `App\Outreach\Support\SendScheduler` (container-resolved, stateless):

- `timezone(User $user): string` — `users.timezone` when a valid IANA name, else `config('app.timezone')`.
- `anchor(User $user): ?CarbonImmutable` — latest of: max `scheduled_for` of the user's `queued`
  applications, `updated_at` of the user's `sending` application, max `sent_at`. `null` when none.
- `nextSlot(User $user, ?CarbonImmutable $after = null): CarbonImmutable` — `anchor` = latest of
  (`$after`, `anchor($user)`); `candidate = anchor === null ? now() : max(now(), anchor +
  random_int(min, max) seconds)`; then `intoWindow`; result truncated to whole seconds (the column has
  second precision; later equality checks compare `getTimestamp()`).
- `isInsideWindow(User $user, ?CarbonImmutable $at = null): bool` — always `true` when the window is
  disabled; else `$at` (default now) in the user's timezone is within `[start, end)` and, when
  `weekdays_only`, Monday–Friday.
- `nextWindowStart(User $user, CarbonImmutable $from): CarbonImmutable` — the next window start at or
  after `$from` in the user's timezone (today's start if `$from` is before it, else the next day's),
  skipping Saturday/Sunday when `weekdays_only`, plus a random `0–120 s` jitter; returned in the app
  timezone.
- `intoWindow(User $user, CarbonImmutable $at): CarbonImmutable` — `$at` if inside, else
  `nextWindowStart($user, $at)`.
- All config through `OutreachConfig`.

**Steps.**

1. Implement the class (no callers yet).
2. Pint.

**Done when.**

- In `php artisan tinker` (after the owner's fresh migrate): for a user with no applications
  `nextSlot` ≈ now inside the window; with a queued row scheduled at T, `nextSlot` ∈ [T+min, T+max];
  a Saturday 10:00 candidate with `weekdays_only` moves to Monday 08:00–08:02 in the user's timezone;
  with the window disabled nothing moves.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Pause/resume (Phase 5), any dispatching.

---

### Phase 4 — `LiveSending` presenter, `SendingUpdated` event, `GET /internal/sending`

Status: PENDING
Role: laravel-backend · Depends on: 3 · Covers: AC06, AC07 (read side) · Size: M
Spec: B.7 (`GET /internal/sending`, `SendingUpdated`), client-app-screens B.2 `LiveSending`

**Goal.** The live panel's single payload exists, is served, and can be broadcast.

**Contract.**

- `App\Client\LiveSendingPresenter::forUser(User $user): array` → the TS `LiveSending`:
  - `state` (first match): `paused` if `isSendingPaused()`; `limit_reached` if quota remaining = 0 and
    nothing `queued`/`sending`; `outside_window` if `queued` rows exist and `!isInsideWindow`; `sending`
    if a row is `sending`; `waiting` if `queued` rows exist; else `idle`.
  - `current`: the `sending` application as `ApplicationItemResource` (load `company`,
    `jobPosting.profile`, `user`), else `null`.
  - `progress`: `{index: min(sentToday + 1, limit), total: limit}` when a row is `queued` or `sending`,
    else `null`; `sentToday` = status `sent` with `sent_at` today (app timezone); `limit` from
    `PlanCatalog`.
  - `queue`: next 3 `queued` by `scheduled_for` asc (nulls last), as `ApplicationItemResource`.
  - `queuedCount`; `nextSendAt` = min non-null `scheduled_for` of `queued`; `waitStartedAt` =
    `SendScheduler::anchor()` when `state = waiting`, else `null`; `estimatedFinishAt` = `nextSendAt +
    (queuedCount − 1) × averageIntervalSeconds` (null without `nextSendAt`); `spacing` =
    `{minSeconds, maxSeconds}`; `window` = `null` when disabled, else `{start, end, weekdaysOnly,
    timezone}` (timezone from `SendScheduler::timezone`). Dates ISO 8601.
- `App\Events\Client\SendingUpdated` (`ShouldBroadcastNow`, uses `DispatchesClientEvent`):
  `__construct(public int $userId)`, `PrivateChannel('App.Models.User.'.$userId)`, `broadcastAs()` =
  `'sending.updated'`, `broadcastWith()` = `['sending' => LiveSendingPresenter->forUser($user)]`;
  `public static function broadcastFor(int $userId): void` (best-effort dispatch).
- `App\Http\Controllers\Client\Internal\SendingController::show` → JSON `LiveSending`; route
  `GET /internal/sending` name `internal.sending.show` in the existing `/internal` group.

**Steps.**

1. Presenter, event, controller, route.
2. Pint; `php artisan route:list --path=internal/sending`.

**Done when.**

- `GET /internal/sending` (logged in) returns every `LiveSending` key; `state = idle` for a user with no
  applications.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Dispatching `SendingUpdated` (Phases 5, 7, 8), pause/resume endpoints (Phase 8).

---

### Phase 5 — Spaced queueing, job start guards, pause/resume core

Status: PENDING
Role: laravel-backend · Depends on: 3, 4 · Covers: AC02, AC06, AC07, AC09 · Size: M
Spec: Part 0.2, 0.3, 0.5, B.4

**Goal.** Every queued application gets its own spaced slot and a delayed job; jobs that are stale,
paused, outside the window or would overlap another send step aside.

**Contract.**

- `QueueApplication::queue()`: after the existing user `lockForUpdate`, set
  `'scheduled_for' => $this->scheduler->nextSlot($user)` on create; dispatch
  `SendApplicationEmail::dispatch($application->id, $application->scheduled_for)->delay($application->scheduled_for)->afterCommit()`;
  delete the "Sent right away…" comment. After a successful commit in `handle()`:
  `SendingUpdated::broadcastFor($user->id)`. Signature unchanged.
- `SendApplicationEmail::__construct(public int $applicationId, public ?CarbonImmutable $expectedScheduledFor = null)`
  (`null` = job serialized before this change → no stale check).
- Start guards in `handle()`, before `beginAttempt`, applied only when the row exists and is `queued`
  (other statuses fall through to the existing `beginAttempt` handling — `sending` → `ambiguous`,
  others exit):
  1. Stale: `$expectedScheduledFor !== null` and `scheduled_for?->getTimestamp() !==
     $expectedScheduledFor->getTimestamp()` → return silently.
  2. Paused: user `isSendingPaused()` → `scheduled_for = null` (model save, stays `queued`) → return.
  3. Window: `!isInsideWindow($user)` → `$slot = nextSlot($user)`; save `scheduled_for = $slot`;
     `self::dispatch($id, $slot)->delay($slot)`; return.
- In `beginAttempt()` (locked): lock the **user** row first (`User::query()->whereKey(...)->lockForUpdate()`),
  then the application; if another application of the user is `sending` → re-slot this one
  (`nextSlot`), save, re-dispatch delayed, return `null`. Also re-check paused there (→ `scheduled_for =
  null`, return `null`).
- `SendScheduler` gains:
  - `pause(User $user, SendingPauseReason $reason): bool` — in a transaction with the user row locked;
    already paused → no change, return `false`; else `forceFill(['sending_paused_at' => now(),
    'sending_pause_reason' => $reason])->save()`, then `SendingUpdated::broadcastFor`, return `true`.
    (`account.updated` comes from the Phase 2 `User` hook.) The in-flight send finishes (AC07).
  - `resume(User $user): void` — transaction with the user row locked: clear both pause fields (model
    save); query-builder `scheduled_for = null` for the user's `queued` rows; then in `queued_at`, `id`
    order: `$slot = nextSlot($user, $previousSlot)`, query-builder update of `scheduled_for`, dispatch
    `SendApplicationEmail($id, $slot)->delay($slot)->afterCommit()`. After commit: one
    `SendingUpdated::broadcastFor` and one `RealtimeEvent::dispatch('applications',
    'ApplicationsUpdated', [...])` (query-builder writes skip model events). Old delayed jobs exit as
    stale.

**Steps.**

1. `SendScheduler::pause/resume`.
2. `QueueApplication` slotting + dispatch.
3. `SendApplicationEmail` constructor + guards (leave the rest of the pipeline as is).
4. Pint.

**Done when.**

- With `OUTREACH_SEND_INTERVAL_MIN_SECONDS=10`/`MAX=20`, `OUTREACH_WINDOW_ENABLED=false`,
  `OUTREACH_INTERCEPT_TO` set: queueing 3 postings for a user from tinker via `QueueApplication` gives 3
  `scheduled_for` values spaced 10–20 s apart in order; the `outreach` worker sends them one at a time.
- `SendScheduler::pause` then `resume` in tinker: pending rows get new increasing slots; the old jobs log
  nothing and change nothing.
- The legacy `/app` Jobs send modal still queues.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Stages/sub-steps (Phase 6), notifications and auto-pause (Phase 7), HTTP endpoints.

---

### Phase 6 — Staged send pipeline (stages, sub-steps, pacing)

Status: PENDING
Role: laravel-backend · Depends on: 5 · Covers: AC05, AC09, AC02 · Size: M
Spec: Part 0.4, B.5 (1–4)

**Goal.** `SendApplicationEmail::handle` runs real, persisted, broadcast stages with visible pacing,
keeping every safety rule.

**Contract.**

- `App\Outreach\Support\ApplicationStageRecorder` (injected):
  - `record(Application $application, SendStage $stage, ?SendSubStep $subStep): void` — query-builder
    update of `stage`, `sub_step` (not `updated_at`); when `$stage` differs from the row's current stage
    append `{"stage": ..., "at": ISO 8601}` with `stage_log = stage_log || ?::jsonb`; sync the same
    attributes on the in-memory model (`setRawAttributes`/`syncOriginalAttributes`) so a later `save()`
    does not overwrite them; then dispatch `ApplicationProgressed` (best-effort) and
    `RealtimeEvent::dispatch('applications', 'ApplicationsUpdated', ['id' => $id])`.
  - `reset(Application $application): void` — `stage`/`sub_step` = null, no log entry, same events.
  - `pace(): void` — `usleep(OutreachConfig::stepDelayMs() * 1000)` when > 0.
- Pipeline (after the Phase 5 guards):
  1. **Pre-checks, no locks** — each sub-step: `record` → check → `pace()`:
     - `validating_recipient`: `checking_company` (company `outreach_status = verified`, else
       "Company is no longer verified for outreach."); `confirming_recipient` (contact exists, same
       company, `smtp_verified`, else "Recipient is not a verified contact of this company.");
       `checking_gmail` (integration `connected`, `account_email` filled, and
       `ConnectedIntegrationTokenManager::accessToken($user, 'gmail')` succeeds, else "Gmail is not
       connected."; a `ConnectedIntegrationReauthorizationRequired` is this failure).
     - `adapting_template`: `filling_variables` (subject and body non-blank, subject ≤
       `CanSendApplications::MAX_SUBJECT_LENGTH`, body ≤ `MAX_BODY_LENGTH`, else "Email content is
       invalid."); `building_html` (`ApplicationTemplateRenderer::html($body)`).
     - `attaching_cv`: `opening_cv` (profile, `cv_path`, `ApplicationProfile::isOwnCvPath`, file exists,
       else "CV file is missing."); `checking_pdf` (size ≤ 5 MB and first bytes `%PDF-`, else "CV file
       is not a valid PDF."); `attaching_file` (build the `Email` exactly as today — intercept mode,
       `[TEST — would go to …]` subject, headers, sanitized attachment name — and `toString()`).
     - A failed check: model save `status = failed`, `last_error` = the reason; `record(failed,
       <failing sub-step>)`. No retry, return.
     - Any other `Throwable` during pre-checks (nothing was sent): `reset()`, rethrow → normal queue
       retry, row stays `queued` (same rule as today's "build failure stays queued").
  2. **Lock and mark sending** (transaction; user row locked, then application): status still `queued`
     (else return); another `sending` row → re-slot (Phase 5 rule); paused → `scheduled_for = null`,
     `reset()`, return; user not active → `failed` "Client account is not active.", `record(failed,
     connecting_gmail)`; quota re-check: `spent` = user's rows with status `sending|sent|ambiguous` and
     `queued_at` today (same bounds as `countedToday`), fails when `spent >= current limit` → (D2 = A)
     keep `queued`, `scheduled_for = nextWindowStart(user, next day start)`, re-dispatch delayed,
     `reset()`, return. Then model save `status = sending`, `attempts + 1`; `record(sending,
     connecting_gmail)`; `pace()`.
  3. `record(sending, delivering)`; `SendsGmailMessages::send`. Outcomes exactly as today: success →
     `sent` + `provider_message_id` + `sent_at`, `record(sent, null)`; `ConnectionException` / unexpected
     `Throwable` → `ambiguous` (stage left at `sending/delivering` — the outcome is unknown);
     reauthorization / 401-class / retries exhausted → `failed`, `record(failed, delivering)`; retryable
     `RequestException` → back to `queued`, `reset()`, rethrow. `failed()` hook → `failed` +
     `record(failed, <current sub-step>)`.
  4. Status changes keep going through the model (public admin broadcast + `ApplicationProgressed` +
     `AccountStatusUpdated`).
- `ClientErrorMessage::PREFIXES` += `'Email content is invalid.' => 'applications.error.content'`,
  `'CV file is not a valid PDF.' => 'applications.error.cv_invalid'` (en + pt strings).

**Steps.**

1. Recorder.
2. Restructure `handle()`/`beginAttempt()`; keep `interceptTo`, `attachmentName`,
   `isAuthorizationError`, `errorMessage`.
3. Error mapping + translations. Pint.

**Done when.**

- With `OUTREACH_STEP_DELAY_MS=1200`: one send takes ~12 s; `stage_log` holds
  `validating_recipient, adapting_template, attaching_cv, sending, sent` with timestamps.
- Deleting the CV file of a queued application → `failed`, `stage = failed`, `sub_step = opening_cv`,
  client `lastError` = the translated CV message (AC05).
- Two queued rows for one user never overlap in `sending`.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Notifications, auto-pause, `SendingUpdated` after terminal statuses (Phase 7).

---

### Phase 7 — Terminal effects: notifications, auto-pause, resume on reconnect

Status: PENDING
Role: laravel-backend · Depends on: 6 · Covers: AC08, AC07 (live), B.7 notifications · Size: M
Spec: Part 0.5, B.5 (5–6), B.7 (Notifications)

**Goal.** Terminal outcomes notify the client, keep the live panel current, and pause sending when
Gmail breaks or sends keep failing.

**Contract.**

- Notifications (extend `App\Notifications\Client\ClientNotification`, send with
  `ClientNotification::send($user, …)`):
  - `ApplicationFailed` — type `application_failed`, payload `['company' => <company name>]`.
  - `DailyLimitReached` — type `daily_limit_reached`, payload `['count' => <daily limit>]`.
  - `SendingAutoPaused` — type `sending_auto_paused`, payload `['reason' => <SendingPauseReason value>]`.
- `SendApplicationEmail`: one private `afterTerminal(Application $application)` called after every
  terminal status (`sent`, `failed`, `ambiguous`, including `failed()`):
  1. `failed` → `ApplicationFailed`.
  2. Reauthorization (the `checking_gmail` reauth failure, the `ConnectedIntegrationReauthorizationRequired`
     catch, the 401-class branch) → `SendScheduler::pause($user, ReauthorizationRequired)`; when it
     returns `true` → `SendingAutoPaused`.
  3. Else, when the user's last N (`OutreachConfig::autoPauseAfterFailures()`) terminal applications
     (`sent|failed|ambiguous`, by `updated_at` desc, `id` desc) number exactly N and are all
     `failed|ambiguous` → `pause($user, RepeatedFailures)`; when `true` → `SendingAutoPaused`.
  4. `SendingUpdated::broadcastFor($user->id)`.
- `QueueApplication::handle()`, after a successful commit: when remaining quota is now 0 and the user has
  no `DailyLimitReached` notification created today (app timezone) → send `DailyLimitReached`.
- `CompleteConnectedIntegration::run()`: after the integration is saved, when the user's
  `sending_pause_reason === ReauthorizationRequired` → `SendScheduler::resume($user)`.

**Steps.**

1. Three notification classes.
2. `afterTerminal` wiring; `QueueApplication` limit notification; reconnect resume.
3. Pint.

**Done when.**

- Revoking the Gmail token (or forcing a 401) during a send → `failed`, user paused with
  `reauthorization_required`, notifications `gmail_reauthorization_required` + `sending_auto_paused`,
  `sending.updated` with `state = paused`; reconnecting Gmail resumes and re-slots the queue.
- Three consecutive failures (e.g. three companies with no CV file) → paused `repeated_failures`; stays
  paused until resumed.
- Queueing up to the limit creates exactly one `daily_limit_reached` that day.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** HTTP pause/resume (Phase 8), admin UI.

---

### Phase 8 — Pause/resume endpoints

Status: PENDING
Role: laravel-backend · Depends on: 4, 5 · Covers: AC07 · Size: S
Spec: B.7 (`POST /internal/sending/pause` / `resume`)

**Goal.** The client can pause and resume from the app.

**Contract.**

- `SendingController::pause` → `SendScheduler::pause($user, SendingPauseReason::Manual)` (already
  paused → unchanged) → JSON `LiveSending`.
- `SendingController::resume` → when the Gmail integration is not `connected`, 422 with
  `__('Reconnect your Gmail account to resume sending.')` (resuming would fail every queued item at
  `checking_gmail` and lose those companies for good); else `SendScheduler::resume($user)` → JSON
  `LiveSending`.
- Routes in the `/internal` group: `POST /internal/sending/pause` (`internal.sending.pause`),
  `POST /internal/sending/resume` (`internal.sending.resume`).
- `lang/en.json` + `lang/pt.json`: the 422 message (pt: "Reconecte sua conta do Gmail para retomar o
  envio.").

**Steps.** Controller methods, routes, translations, pint.

**Done when.**

- `POST /internal/sending/pause` returns `state = paused`, broadcasts `sending.updated` and
  `account.updated`; a queued job that comes due stays `queued` with `scheduled_for = null`.
- `POST /internal/sending/resume` returns `waiting`/`idle` with fresh slots.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Frontend wiring (Phase 14).

---

### Phase 9 — `POST /internal/applications` (select only) + `QueueResult`

Status: PENDING
Role: laravel-backend · Depends on: 5 · Covers: AC02, AC04 (403) · Size: M
Spec: B.4 (bulk order), B.7 (first bullet), Part 0.7

**Goal.** Starter clients queue selected jobs in order; other modes are refused with a clear message.

**Contract.**

- `App\Http\Requests\Client\QueueApplicationsRequest`:
  - `authorize()` returns `Illuminate\Auth\Access\Response`: plan mode `review` →
    `Response::deny(__('Review each email before sending'))`; `auto` →
    `Response::deny(__('Your plan sends automatically.'))`; `select` → allow. Mode read from
    `PlanCatalog::for($user)` at request time (Part 0.7: plan changes apply immediately).
  - rules: `jobIds` `required|array|min:1|max:{remaining}` (remaining from
    `CanSendApplications::check`), `jobIds.*` `integer|distinct`.
- `ApplicationsController::store` → route `POST /internal/applications` (`internal.applications.store`):
  loop `jobIds` **in the given order**; missing posting → rejected with the no-match reason; else
  `QueueApplication::handle($user, $posting, ApplicationOrigin::Manual)` (each call commits, so each
  item's slot follows the previous one). A second job of the same company is rejected by the existing
  already-applied rule.
- Response `QueueResult`: `queued: [{jobId, applicationId, scheduledFor (ISO)}]`,
  `rejected: [{jobId, reason}]`, `quota: Quota` (after queueing).
- `AccountStatusPresenter::quota(User $user): array` extracted (public) and reused by `forUser()`.
- `App\Outreach\Support\QueueRejectionMessage::for(string $reason): string` — maps
  `QueueApplication::REJECT_NO_MATCH` → `queue.reject.no_match`, `REJECT_NO_RECIPIENT` →
  `queue.reject.no_recipient`, `REJECT_ALREADY_APPLIED` → `queue.reject.already_applied`,
  `REJECT_NO_PROFILE` → `queue.reject.no_profile`, prefix `REJECT_NOT_ELIGIBLE_PREFIX` →
  `queue.reject.not_eligible`; unknown → `queue.reject.generic`. en + pt strings for all, plus the two
  403 messages.

**Steps.** Request, presenter extraction, rejection mapper, controller method, route, translations, pint.

**Done when.**

- Starter user: posting 5 pool jobs returns 5 `queued` with increasing `scheduledFor` spaced within the
  configured interval; they send one at a time in that order (AC02).
- Free user → 403 "Your plan sends automatically."; Pro user → 403 "Review each email before sending".
- `jobIds` longer than remaining → 422.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Review endpoints (Phase 10), frontend (Phase 15).

---

### Phase 10 — Review endpoints: drafts and reviewed queueing

Status: PENDING
Role: laravel-backend · Depends on: 9 · Covers: AC03 · Size: M
Spec: B.7 (`/drafts`, `/reviewed`), client-app-screens B.2 `ReviewDraft`

**Goal.** Pro clients see each email in the job's language and queue it as edited.

**Contract.**

- Both endpoints: Pro only. `QueueReviewedRequest`/`ReviewDraftsRequest::authorize()` → `select` or
  `auto` plans: `Response::deny(__('Your plan sends without review.'))` (en + pt).
- `POST /internal/applications/drafts` (`internal.applications.drafts`): `jobIds`
  `required|array|min:1|max:20`, `jobIds.*` `integer|distinct` → `ReviewDraft[]` in request order;
  a job not in `MatchingJobPostings::forUser($user)` or without an active complete profile in its
  language is omitted.
  - `App\Client\ReviewDraftPresenter`: variables = `ApplicationTemplateRenderer::variablesFor($user,
    $posting, $profile)` with `job_url` → `ClientSafeText::JOB_URL_TOKEN` and, per D3 = A, the
    posting-derived values (`company`, `job_title`, `job_location`) passed through
    `ClientSafeText::redact`; `subject`/`body` = `render()` of the profile's templates;
    `job` = `JobCardResource`; `language`; `cvFileName` = profile `cv_original_name`;
    `recipientLabel` = `__('review.recipient_label', ['company' => $name])` (en ":company careers team",
    pt "Equipe de recrutamento da :company"). Never the recipient address.
- `POST /internal/applications/reviewed` (`internal.applications.reviewed`): `jobId`
  `required|integer`, `subject` `required|string|min:1|max:200`, `body` `required|string|min:1|max:5000`;
  the subject may contain no `{{ … }}` variable and the body only `{{ job_url }}` (422 on the field
  otherwise, using `review.subject.length` / `review.body.length` style messages plus a new
  `review.body.variables` key). Job not in the pool → `QueueResult` with it in `rejected`. Else
  `QueueApplication::handle($user, $posting, ApplicationOrigin::Manual, subject: $subject, body: $body)`
  → `QueueResult`.
- `QueueApplication::handle(..., ?string $subject = null, ?string $body = null)`: when given, the
  snapshot is `render($subject, ['job_url' => $posting->url])` / same for body instead of the profile
  templates; everything else (eligibility, recipient, profile, pool, slotting, dispatch) unchanged.

**Steps.** Requests, presenter, controller (`ReviewController` with `drafts`/`reviewed`), routes,
`QueueApplication` overrides, translations, pint.

**Done when.**

- Drafts for a PT job come back in Portuguese, with `{{ job_url }}` as a token and no URL or email
  address; `recipientLabel` shows the company name.
- Posting an edited subject/body queues it; with `OUTREACH_INTERCEPT_TO` the received email has the
  edited text and the real job URL where the token was (AC03).
- Starter/Free → 403.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Review modal wiring (Phase 15).

---

### Phase 11 — Automatic-mode dispatcher + scheduler in `composer dev`

Status: PENDING
Role: laravel-backend · Depends on: 5 · Covers: AC04 · Size: S
Spec: B.6

**Goal.** Free-plan clients get one application queued at a time, automatically, inside the window.

**Contract.**

- `App\Console\Commands\DispatchAutoApplications`, signature `outreach:dispatch-auto`. For each user
  with plan mode `auto` (plan keys whose `PlanCatalog` mode is `auto`), status active,
  `sending_paused_at` null, `CanSendApplications::check()->ok()`, `remaining > 0`,
  `SendScheduler::isInsideWindow`, and **no** application `queued` or `sending`: candidates =
  `MatchingJobPostings::forUser($user)->where('job_postings.first_seen_at', '>=',
  now()->subDays(OutreachLimits::MAX_POSTING_AGE_DAYS))->orderByDesc('job_postings.first_seen_at')->limit(20)`;
  call `QueueApplication::handle($user, $posting, ApplicationOrigin::Auto)` in order and stop at the
  first queued (at most one per user per tick). End with one summary line to the console and
  `Log::info('outreach:dispatch-auto', ['users' => …, 'queued' => …, 'skipped' => …])`.
- `routes/console.php`: `Schedule::command('outreach:dispatch-auto')->everyMinute()->withoutOverlapping()->onOneServer();`
- `AppServiceProvider` (inside `runningInConsole()`): `DevCommands::artisan('schedule:work', 'scheduler');`

**Steps.** Command, schedule, DevCommand, pint.

**Done when.**

- `php artisan schedule:list` shows the command every minute.
- Free user with onboarding complete and matching jobs: `php artisan outreach:dispatch-auto` queues one
  `auto` application; a second run while it is queued queues nothing; after it is sent, the next run
  queues the next one; nothing is queued outside the window or at the limit.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Frontend auto banner (already on fixtures; goes real with Phase 14).

---

### Phase 12 — Admin Users: usage, sending badge, pause/resume actions

Status: PENDING
Role: filament-admin · Depends on: 5 · Covers: AC10, AC01 · Size: S
Spec: B.8 (UserResource)

**Goal.** Admins see each user's daily usage and pause state and can pause/resume them.

**Contract.** `app/Filament/Resources/Users/Tables/UsersTable.php`:

- Replace the "Sent today" column with "Sent today / limit": `sent_today_count` formatted
  `"{count} / {limit}"`, limit from `PlanCatalog::for($record)->dailyLimit`.
- "Sending" badge column: `Active` (success) when not paused, else the `SendingPauseReason` label/color.
- Record actions: "Pause sending" (visible when not paused, `requiresConfirmation`) →
  `SendScheduler::pause($record, SendingPauseReason::Manual)`; "Resume sending" (visible when paused,
  `requiresConfirmation`) → `SendScheduler::resume($record)`; success `Notification`s.
- The plan select (`UserForm`) stays; saving it triggers `account.updated` via the Phase 2 hook.
- No `->poll()`.

**Steps.** Table edits, pint.

**Done when.**

- `/admin/users` shows "3 / 50" style usage and the badge; Pause/Resume change the client's live panel
  without reload (two browser windows).
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Sending monitor (Phase 13).

---

### Phase 13 — Admin Sending monitor page

Status: PENDING
Role: filament-admin · Depends on: 6 · Covers: AC10 · Size: M
Spec: B.8 (Sending monitor)

**Goal.** A read-only, realtime view of what is sending right now and what just finished.

**Contract.**

- `App\Filament\Pages\SendingMonitor` (admin panel, discovered from `app/Filament/Pages`), navigation
  group `Outreach`, label "Sending monitor", slug `sending-monitor`.
- Two read-only table widgets shown on the page (e.g. `App\Filament\Widgets\InFlightApplications`,
  `RecentTerminalApplications`, not registered on the dashboard):
  - In flight: applications with status `queued|sending` across users, ordered by `scheduled_for`
    (nulls last); columns user name, company name, status badge, stage badge, sub-step, scheduled for,
    attempts.
  - Recent: the last 50 `sent|failed|ambiguous` by `updated_at` desc (`whereIn('id', <latest 50 ids>)`,
    `paginated(false)`); same columns plus sent at and `last_error` (limited).
  - Both `->socket(channel: 'applications', event: 'ApplicationsUpdated')`; no actions; never poll.
- Stage/sub-step updates already emit `ApplicationsUpdated` (Phase 6 recorder).

**Steps.** Page + two widgets, pint.

**Done when.**

- `/admin/sending-monitor` lists in-flight and recent applications; while a send runs, its stage and
  sub-step change live without reload.
- `grep -rn "poll" app/Filament/Pages app/Filament/Widgets` finds nothing.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** Any write action.

---

### Phase 14 — Frontend: live panel and pause/resume go real

Status: PENDING
Role: inertia-frontend · Depends on: 4, 8 · Covers: AC05, AC06, AC07 · Size: S
Spec: B.7 (frontend line), client-app-screens S1 (live panel)

**Goal.** The dashboard live panel and pause/resume run on the real endpoints and `sending.updated`.

**Contract.**

- `resources/js/data/endpoints.ts`: `sending`, `pauseSending`, `resumeSending` use the generated
  Wayfinder `SendingController` actions (`show`, `pause`, `resume`); regenerate with
  `php artisan wayfinder:generate` if the files are missing.
- `use-live-sending.ts`: `real = () => apiFetch<LiveSending>(endpoints.sending().url)`.
- `use-pause-sending.ts`: `real` for pause and resume (`apiFetch<LiveSending>(url, { method: 'post' })`);
  on error the optimistic value rolls back (existing) and the component shows the `ApiError.message`
  (the 422 "Reconnect your Gmail account to resume sending.").
- The `sending.updated`, `application.progressed`, `account.updated` handlers already exist; confirm the
  dashboard subscribes to them and that `dev-emitter` is only used when `VITE_USE_FIXTURES=true`.
- The stepper renders `current.stage`/`current.subStep` from real payloads; `state` values
  `paused`, `limit_reached`, `outside_window` each render their existing copy.

**Steps.** Endpoints, two hooks, error surface in `live-sending-card.tsx`/`auto-banner.tsx` if missing.

**Done when.**

- With fixtures off, the dashboard panel shows real state; a send moves through the stages live with the
  configured pacing; Pause/Resume update without reload in both windows.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Queue/review flows (Phase 15).

---

### Phase 15 — Frontend: S1/S2/S3 send flows go real

Status: PENDING
Role: inertia-frontend · Depends on: 9, 10, 14 · Covers: AC02, AC03, AC04 · Size: M
Spec: B.7 (frontend line), client-app-screens S1–S3

**Goal.** Matches actions, Jobs selection + sticky bar, the confirm modal and the review modal call the
real endpoints.

**Contract.**

- `endpoints.ts`: `queueApplications` → `ApplicationsController.store`, `reviewDrafts` /
  `queueReviewed` → the Phase 10 controller actions (Wayfinder).
- Hooks `real`: `useQueueApplications` (`POST {jobIds}` → `QueueResult`), `useReviewDrafts`
  (`POST {jobIds}` → `ReviewDraft[]`), `useQueueReviewed` (`POST {jobId, subject, body}` →
  `QueueResult`); `invalidateAfterQueue` stays.
- UI: 403 → show `ApiError.message` (already translated); 422 → field errors under subject/body in
  the review modal and the quota message in the confirm modal; `rejected[].reason` listed as today.
- `send.confirm.body` uses the real spacing: en "They will go out one by one from :gmail, about every
  :min–:max seconds. Estimated finish :time." (pt equivalent), `:min`/`:max` from
  `useLiveSending().data.spacing`, `:time` from `spacing` average × count.
- Selecting jobs keeps the client's selection order in `jobIds`.

**Steps.** Endpoints, three hooks, confirm/review modal error handling and copy, translations.

**Done when.**

- Starter: select 5 jobs on `/jobs` → confirm → 5 queued, live panel counts down between sends.
- Pro: "Review n" opens drafts in the job's language; edits are queued and sent as edited.
- Free: matches/jobs show the auto banner; no send action reaches the API.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Removing `/app` (Phase 16).

---

### Phase 16 — Remove the legacy Filament `/app` panel

Status: PENDING
Role: laravel-backend · Depends on: 7, 11, 13, 15 · Covers: AC11 · Size: M
Spec: B.9

**Goal.** `/app` is gone; `/admin` untouched.

**Contract.** Delete / edit:

- Delete `app/Providers/Filament/AppPanelProvider.php` and its line in `bootstrap/providers.php`.
- Delete `app/Filament/App/**` (`Pages/Applications.php`, `Pages/Jobs.php`, `Pages/Preferences.php`) and
  `resources/views/filament/app/**` (`pages/applications.blade.php`, `pages/jobs.blade.php`,
  `pages/partials/`).
- `User::canAccessPanel`: remove the `'app'` branch.
- `ConnectedIntegrationTokenManager::markReauthorizationRequired`: remove the Filament
  `Notification::make()…->url(Preferences::getUrl(panel: 'app'))…->sendToDatabase(...)` block and its
  imports; keep `GmailReauthorizationRequired::send(...)` and the account-status dispatch.
- `bootstrap/app.php`: drop `'app', 'app/*'` from the Filament exclusion so `/app` 404s render the
  branded error page.
- `php artisan wayfinder:generate` so `resources/js/actions/App/Filament/App/*` disappears; fix any
  import of it.
- Then `grep -rn "panel: 'app'\|Filament\\\\App\|filament.app\|filament/app" app bootstrap config routes resources`
  returns nothing. Report (don't edit) mentions of the `/app` panel in `.claude/` or `docs/`.
- Check `NotificationsController` ignores legacy Filament-format rows (no `data.type`) already in the
  `notifications` table; report if it does not.

**Steps.** Deletions, edits, regenerate, grep, pint, `php artisan route:list | grep -c "^.* app/"`.

**Done when.**

- `GET /app` and `GET /app/jobs` → 404; `/admin` loads and works.
- `composer lint:check`, `composer types:check`, `yarn types:check` pass.

**Not in this phase.** Anything else in `/admin`.

---

### Phase 17 — Focused Pest tests (only with "write tests")

D1 = A. `CLAUDE.md` still requires the words "write tests" in the message that runs this phase, e.g.
`/execute-phases docs/features/plans-and-sending-modes/plan.md 17 — write tests`.

Status: PENDING
Role: laravel-backend · Depends on: 6, 9, 10, 11 · Covers: AC02, AC04, AC06, AC07, AC09 (regression net) · Size: M
Spec: Owner decisions (D-TESTS)

**Goal.** A small regression net for the parts that can harm real Gmail accounts.

**Contract.** Pest feature/unit tests (`Queue::fake()`, `Http::fake()`, fake `SendsGmailMessages`, frozen
time):

- `SendScheduler`: spacing within `[min, max]`, ordering of consecutive slots, window move, weekend skip,
  window disabled.
- Quota: `countedToday` statuses; `jobIds` over remaining → 422.
- Unique `(user, company)`: second queue for the same company rejected; `sent`/`ambiguous` never re-sent.
- Guards: stale dispatch exits without side effects; paused → `scheduled_for = null`; no two `sending`.
- Mode enforcement: 403 for `auto`/`review` on `POST /internal/applications`; 403 for non-review on
  `/drafts` and `/reviewed`.

**Done when.** `php artisan test --compact` passes.

**Not in this phase.** Browser tests.

---

### Phase 18 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–17 · Covers: AC01–AC11 · Size: S
Spec: Acceptance criteria, Verification

**Goal.** Prove the feature end to end and hand the owner a checklist.

**Steps.**

1. Gate: `composer ci:check` (runs `yarn check`, `yarn types:check`, `composer test` → lint, PHPStan,
   tests). Report any failure with output.
2. Grep for forbidden patterns: `->poll(`, `wire:poll`, `pollingInterval`, `panel: 'app'`,
   `Filament\\App`, `Sent right away`; `git diff --stat lang/es.json` is empty; no new file under
   `database/migrations`.
3. Smoke (with `OUTREACH_INTERCEPT_TO`, `OUTREACH_SEND_INTERVAL_MIN_SECONDS=10`, `…_MAX_SECONDS=20`,
   `OUTREACH_WINDOW_ENABLED=false`, `composer dev` running): `GET /internal/sending`, queue as Starter,
   drafts + reviewed as Pro, `outreach:dispatch-auto` as Free, pause/resume, `/app` → 404.
4. AC walkthrough AC01–AC11, each with the observed evidence.
5. Owner's manual checklist (two browser windows): AC01 plan change live; AC02 five spaced sends one at a
   time; AC03 edited email received as edited; AC04 auto queueing to the limit + 403; AC05 stepper with
   pacing + missing CV at `attaching_cv/opening_cv`; AC06 window on (outside hours → `outside_window`,
   sends at window open) and off; AC07 pause/resume live; AC08 revoke Gmail → paused + notified →
   reconnect resumes; three failures → `repeated_failures`; AC10 Sending monitor live + Users usage/pause
   actions; AC11 `/app` 404.

**Done when.** Report delivered with gate output, grep results, AC table (pass/fail/evidence) and the
open manual items.
