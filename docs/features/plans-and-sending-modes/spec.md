# plans-and-sending-modes — auto / select / review, spaced one-by-one sending, live stages, pause

> **Order:** 6 of 10. **Depends on:** `client-core-wiring`,
> `preferences-clarity`, `application-languages` DONE.
> **Kind:** backend (sending engine) + wiring of the live panel, the send
> flows (S1 matches, S2 Jobs, S3 confirm/review) and admin tools. Ends by
> **removing the legacy Filament `/app` panel**.
>
> **How to run:** `/plan-spec docs/features/plans-and-sending-modes/spec.md`,
> then `/execute-phases`. No new dependencies.

## Part 0 — Context and decisions (owner, final)

1. **Plans and modes** (catalog from `client-core-wiring` B.4, values in
   config/env, editable before deploy):
    - **Free** — 25/day — `auto`: the system chooses and sends by itself,
      within the client's preferences and languages. No choosing, no review.
    - **Starter** — 50/day — `select`: the client chooses the jobs.
    - **Pro** — 150/day — `review`: chooses **and** edits each email before
      it is queued.
      The owner explicitly **reintroduces automatic sending** (it was removed
      earlier only to validate the flow).
2. **One by one, spaced.** Every client's emails leave one at a time with a
   random gap between `OUTREACH_SEND_INTERVAL_MIN_SECONDS` and
   `…_MAX_SECONDS` (defaults 45 and 120; the owner will test values like
   20 s). Never bursts.
3. **Sending window:** sends happen only inside a window in the client's
   timezone (default 08:00–19:00, weekdays only), configurable and
   switchable off (for testing). Queued items outside the window wait for
   the next window.
4. **Honest, narrated progress.** The pipeline runs in real stages and
   sub-steps (contracts `SendStage`, `SubStep`), persisted and broadcast
   live. A configurable pacing delay between sub-steps
   (`OUTREACH_STEP_DELAY_MS`, default 1200) makes the work visible; the
   steps are real work, never invented. A failure shows the step that
   actually failed.
5. **Pause/resume** by the client; **automatic pause** when Gmail needs
   reauthorization (auto-resumes after reconnect) or after 3 consecutive
   failed/ambiguous sends (client must resume). Bounces cannot be detected
   (we do not read the inbox; `gmail.send` scope only) — not in scope.
6. **Unchanged guarantees:** one email per (client, company) forever (DB
   unique); `sent`/`ambiguous` never resent; one recipient by priority;
   `OUTREACH_INTERCEPT_TO` test mode; quota counts queued+sending+sent+ambiguous.
7. Plan changes by the admin take effect **immediately** (limit and mode);
   items already queued stay queued.
8. Migrations: edit create migrations directly. `CLAUDE.md` rules apply.

## Part B — Product spec

### B.1 Files to read first

`app/Outreach/**` (all), `app/Models/{Application,User,ConnectedIntegration}.php`,
`app/Enums/{ApplicationStatus,ApplicationOrigin}.php`,
`app/Services/ConnectedIntegrationTokenManager.php`,
`app/Actions/CompleteConnectedIntegration.php`,
`app/Providers/AppServiceProvider.php` (DevCommands), `routes/console.php`,
`app/Filament/App/**`, `app/Providers/Filament/AppPanelProvider.php`,
`bootstrap/providers.php`, `app/Filament/Resources/{Users,Applications}/**`,
`client-app-screens/spec.md` S1–S3, B.2 (`LiveSending`, `ReviewDraft`,
`QueueResult`, `SendStage`, `SubStep`), B.7.

### B.2 Config (`config/talent.php` → `outreach`)

```php
'outreach' => [
    'intercept_to' => env('OUTREACH_INTERCEPT_TO'),               // existing
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
],
```

Validate at boot (in a service provider, local and production): min ≤ max,
min ≥ 5, `step_delay_ms` ≤ 3000 (10 sub-steps must fit well inside the job
`timeout` of 60 s together with the Gmail call). `.env.example`: append all
keys. Window timezone = `users.timezone` ?? `config('app.timezone')`.

### B.3 Data model (edit create migrations)

**users:** `sending_paused_at` timestamp nullable; `sending_pause_reason`
string(32) nullable (`manual`, `reauthorization_required`,
`repeated_failures`; enum `App\Enums\SendingPauseReason`).

**applications:** `stage` string(32) nullable (enum `App\Enums\SendStage`),
`sub_step` string(32) nullable (enum `App\Enums\SendSubStep`),
`stage_log` jsonb default `[]` (list of `{stage, at}`), index
`(user_id, status, scheduled_for)`.

### B.4 Scheduling (one by one, spaced, windowed)

`App\Outreach\Support\SendScheduler`:

- `nextSlot(User $user, ?CarbonImmutable $after = null): CarbonImmutable` =
  `max(now, anchor + random_int(min, max) seconds)`, where `anchor` = the
  latest of (max `scheduled_for` of the user's `queued` applications, the
  `updated_at` of a `sending` one, the last `sent_at`), then moved into the
  window: if outside, to the next window start (+ a random 0–120 s jitter),
  skipping weekends when `weekdays_only`.
- `QueueApplication` (all modes) sets `scheduled_for = nextSlot()` inside
  its transaction (the existing per-user `lockForUpdate` serializes this)
  and dispatches `SendApplicationEmail::dispatch($id, $scheduledFor)
->delay($scheduledFor)->afterCommit()`. Remove the comment "Sent right away".
- Bulk queueing (select) loops in the client's selection order; each item
  gets the next slot after the previous one.
- `SendApplicationEmail` receives `expectedScheduledFor`; at start, if the
  application's `scheduled_for` differs (rescheduled by resume) the job
  exits silently (stale dispatch). If the user is paused: set
  `scheduled_for = null`, keep `queued`, exit. If outside the window: move
  to the next window slot, re-dispatch with delay, exit.
- `resume(User)`: clear pause fields, then re-slot every `queued`
  application of the user in `queued_at` order and dispatch each.

### B.5 Pipeline with stages (`SendApplicationEmail::handle` restructure)

Keep every existing safety rule; change the structure so stages are real:

1. **Pre-checks without locks**, each sub-step persisted then paced
   (`usleep(step_delay_ms)` after each):
    - `validating_recipient`: `checking_company` (company still
      `verified`), `confirming_recipient` (contact exists, belongs to the
      company, `smtp_verified`), `checking_gmail` (integration connected
      and `accessToken()` obtainable — this refreshes the token if needed).
    - `adapting_template`: `filling_variables` (snapshot subject/body present
      and within limits), `building_html` (`ApplicationTemplateRenderer::html`).
    - `attaching_cv`: `opening_cv` (profile CV exists and owned),
      `checking_pdf` (≤ 5 MB and starts with `%PDF-`), `attaching_file`
      (build the MIME with the attachment → raw string).
      A failed check → status `failed`, `stage = failed`, `sub_step` = the
      failing sub-step, `last_error` = existing reason text. No retry.
2. **Lock and mark sending** in a short transaction: `lockForUpdate`, status
   must still be `queued` (else exit), user not paused, quota not exceeded
   for today (the quota was reserved at queue time; re-check defensively),
   then `status = sending`, `attempts + 1`, `stage = sending`,
   `sub_step = connecting_gmail`.
3. `delivering`: call `SendsGmailMessages::send`. Result handling exactly as
   today (sent / ambiguous / failed / retry). On success: `stage = sent`,
   `sub_step = null`. On retry (status back to `queued`): `stage` and
   `sub_step` reset to null.
4. Every stage/sub-step change: update `stage`, `sub_step`, append to
   `stage_log` with a query-builder update (no model events), then dispatch
   `ApplicationProgressed` (contract payload) explicitly. Status changes keep
   using the model (public admin broadcast + `ApplicationProgressed` +
   `AccountStatusUpdated` from `client-core-wiring`).
5. After any terminal status, dispatch `SendingUpdated` for the user.
6. **Auto-pause:** on reauthorization (existing branch) set pause reason
   `reauthorization_required`; after the terminal status, if the last N
   (`auto_pause_after_failures`) terminal applications of the user are all
   `failed`/`ambiguous`, pause with `repeated_failures`. Both send the
   `sending_auto_paused` notification. `CompleteConnectedIntegration`: when a
   user reconnects Gmail and the pause reason is `reauthorization_required`,
   call `resume`.

### B.6 Automatic mode dispatcher

Command `outreach:dispatch-auto`, scheduled in `routes/console.php`
`everyMinute()->withoutOverlapping()->onOneServer()`:

For each user with plan mode `auto`, active, not paused, eligible
(`CanSendApplications` ok), `remaining > 0`, inside the window, and with
**no** application `queued` or `sending`: pick the next posting from
`MatchingJobPostings::forUser` ordered by `first_seen_at desc`, restricted
to `first_seen_at >= now - MAX_POSTING_AGE_DAYS`, one per company, and call
`QueueApplication` with origin `Auto`. One application per user per tick
(the spacing comes from `nextSlot`). Log a summary line per run.
Add `DevCommands::artisan('schedule:work', 'scheduler')` to the local dev
processes in `AppServiceProvider` so `composer dev` runs the scheduler.

### B.7 Mode enforcement and endpoints

`/internal` group (`client-core-wiring` B.6):

- `POST /internal/applications` `{ jobIds: int[] (1..remaining) }` —
  `select` plans only. `review` plans get 403 "Review each email before
  sending" (they use the reviewed endpoint); `auto` plans get 403 "Your plan
  sends automatically." Returns `QueueResult` (per-job rejections with
  translated reasons; duplicates per company rejected).
- `POST /internal/applications/drafts` `{ jobIds }` (review only, max 20)
  → `ReviewDraft[]`: rendered with the job-language profile, `{{ job_url }}`
  kept as a token, `recipientLabel` = "<Company> careers team" (localized;
  never the address).
- `POST /internal/applications/reviewed` `{ jobId, subject, body }` (review
  only): validate lengths, the body may contain only the `{{ job_url }}`
  token as a variable, re-check the job is in the pool, then
  `QueueApplication` with subject/body overrides (render the job URL into
  the token). Returns `QueueResult`.
- `GET /internal/sending` → `LiveSending`:
  `state`: `paused` if paused; `limit_reached` if remaining = 0 and nothing
  queued/sending; `outside_window` if queued items exist and now is outside
  the window; `sending` if one is `sending`; `waiting` if queued exist;
  else `idle`. `current`, `progress` (`index` = sent today + 1, `total` =
  limit), `queue` (next 3 by `scheduled_for`), `queuedCount`, `nextSendAt`,
  `waitStartedAt` (anchor of B.4), `estimatedFinishAt` (nextSendAt +
  (queuedCount − 1) × average interval), `spacing`, `window`.
- `POST /internal/sending/pause` / `resume` → `LiveSending` (+ broadcasts).
- `AccountStatus.sending` becomes real (paused, autoPausedReason).
- Event `SendingUpdated` → `sending.updated` on the private user channel
  after queueing, each terminal status, pause, resume.
- Notifications (database + live, via `client-core-wiring` B.8 base):
  `application_failed` (status `failed`), `daily_limit_reached` (first time
  the quota hits 0 in a day), `sending_auto_paused`.

Frontend: wire S1 matches actions, S2 selection + sticky bar, S3 confirm and
review modal, live panel, pause/resume to the real endpoints; the dev
emitter is no longer used when fixtures are off.

### B.8 Admin (Filament `/admin`)

- `UserResource`: plan select (already), columns "Sent today / limit",
  "Sending" badge (active / paused + reason), actions Pause/Resume sending.
- New page **Sending monitor** (Outreach group): realtime table
  (`Table::socket()`, existing driver) of applications `queued`/`sending`
  across users (user, company, stage, sub-step, scheduled for, attempts),
  plus the last 50 terminal ones. Read-only. Never polls.

### B.9 Remove the legacy Filament `/app` panel (last phase)

Only after everything above works in the new client app:
delete `app/Providers/Filament/AppPanelProvider.php` and its registration
in `bootstrap/providers.php`, `app/Filament/App/**`, the `'app'` branch in
`User::canAccessPanel`, any view under `resources/views/filament/app/**` if
present, and every remaining reference (`grep -R "panel: 'app'\|Filament\\\\App"`).
`/app/*` must return 404 afterwards. Admin `/admin` untouched.

## Acceptance criteria

- **AC01** Changing a user's plan in the admin changes their limit and mode
  immediately in the client app (live via `account.updated`).
- **AC02** Starter: selecting 5 jobs queues 5 applications with
  `scheduled_for` spaced by 45–120 s (or the configured values) and they are
  sent one at a time in that order; never two `sending` at once for a user.
- **AC03** Pro: the review modal shows drafts in the job's language without
  any URL or email address; edits are saved into the application snapshot
  and sent as edited (check with `OUTREACH_INTERCEPT_TO`).
- **AC04** Free: with onboarding complete and matching jobs, the dispatcher
  queues one application at a time inside the window until the daily limit;
  `POST /internal/applications` returns 403 for this plan.
- **AC05** Each send moves through the real stages and sub-steps; the
  dashboard stepper shows them live with the configured pacing; a missing CV
  fails at `attaching_cv` / `opening_cv` with the reason.
- **AC06** Outside the window nothing is sent; queued items show
  `outside_window` and are sent when the window opens. With
  `OUTREACH_WINDOW_ENABLED=false` the window is ignored.
- **AC07** Pause stops further sends (the current one finishes); Resume
  re-slots and continues; the live panel reflects both without reload.
- **AC08** A 401 from Gmail pauses sending with `reauthorization_required`,
  notifies the client, and reconnecting resumes automatically. Three
  consecutive failures pause with `repeated_failures`.
- **AC09** All previous guarantees hold: unique (user, company), no resend of
  `sent`/`ambiguous`, connection error → `ambiguous`, intercept mode.
- **AC10** Admin Sending monitor updates live; Users shows usage and pause
  state with actions.
- **AC11** After B.9, `/app` returns 404, the new client app covers every
  former `/app` capability, and `composer types:check` passes.

## Verification

`composer lint:check`, `composer types:check`, `yarn check`,
`yarn types:check`, existing tests; manual runs with
`OUTREACH_INTERCEPT_TO`, `OUTREACH_SEND_INTERVAL_MIN_SECONDS=10`,
`…_MAX_SECONDS=20`, `OUTREACH_WINDOW_ENABLED=false`, two browser windows.

## Out of scope

Billing (spec 8), reply/bounce tracking (needs inbox access), follow-ups,
AI-written emails, per-recipient timezones (the window uses the client's
timezone as an approximation).

## Owner decisions

- **D-TESTS (recommended):** this spec touches the part of the product that
  can hurt real people's Gmail accounts (duplicates, bursts). Recommended:
  ask for focused Pest tests for `SendScheduler`, the quota, the unique
  per company, the stale-dispatch/pause guards and mode enforcement. Tests
  are written only if the phase message says "write tests".
