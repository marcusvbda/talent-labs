# collection-scheduler — automatic daily collection, controlled from the admin

> **Order:** 7 of 10. **Depends on:** `client-core-wiring` DONE (for the
> `jobs.collected` event). Independent of specs 4–6; can run earlier.
> **Kind:** backend + Filament admin page.
>
> **How to run:** `/plan-spec docs/features/collection-scheduler/spec.md`,
> then `/execute-phases`. No new dependencies.

## Part 0 — Context and decisions (owner, final)

Collection runs today only when an admin clicks "Collect jobs now"
(`App\Actions\Collection\StartCollectionRun`); `sources.interval_minutes`
is stored but does nothing. A run takes a while, so clients must always find
already-collected jobs when they log in. Decisions:

1. Collection runs **automatically at least once a day** in production.
2. The schedule is **stored in the database and edited in the admin**
   (runs per day, times, timezone, pause/resume, run now) — **no deploy**
   needed to change it.
3. A console command keeps manual runs possible locally and in production.
4. Existing rules stay: one run at a time (lock), batch per source,
   finalize idempotent, contact discovery and AI extraction per new posting.
5. `sources.interval_minutes` stays decorative; remove its mention from
   admin help texts if it suggests it schedules anything.
6. Migrations: new create migration allowed; edit existing ones directly.
   `CLAUDE.md` rules apply.

## Part B — Product spec

### B.1 Files to read first

`app/Actions/Collection/{StartCollectionRun,FinalizeCollectionRun,MarkCollectionRunFailed}.php`,
`app/Models/{CollectionRun,Source}.php`, `app/Jobs/FetchJobsFromSource.php`,
`app/Filament/Resources/CollectionRuns/**` (the "Collect jobs now" action),
`routes/console.php`, `app/Providers/AppServiceProvider.php` (DevCommands;
`schedule:work` is added by `plans-and-sending-modes` B.6 — add it here if
this spec runs first), skill `job-collection`.

### B.2 Data model

New table `collection_schedules` (singleton, one row, created by a seeder
if missing — idempotent `firstOrCreate(['id' => 1], defaults)`):

| Column             | Type                                                                                   | Default                  |
| ------------------ | -------------------------------------------------------------------------------------- | ------------------------ |
| id                 | bigint                                                                                 | 1                        |
| enabled            | boolean                                                                                | true                     |
| times              | jsonb (list of `"HH:MM"`, 24 h, unique, sorted, 1–12 items)                            | `["06:00", "18:00"]`     |
| timezone           | string(64) IANA                                                                        | `config('app.timezone')` |
| last_slot_key      | string(32) nullable (`"YYYY-MM-DD HH:MM"` of the last slot handled)                    | null                     |
| last_dispatched_at | timestamp nullable                                                                     | null                     |
| last_result        | string(200) nullable (e.g. "Started run #42", "Skipped: a run is already in progress") | null                     |
| updated_by         | foreignId users nullable, nullOnDelete                                                 | null                     |
| timestamps         |                                                                                        |                          |

Model `CollectionSchedule` with `current(): self` (cached per request) and
casts. Broadcast changes with the existing `BroadcastsRealtime` concern on a
public admin channel `collection_schedule` (ids only).

### B.3 Behavior

- `StartCollectionRun::handle(?User $triggeredBy)` — accept null (scheduler);
  `triggered_by` is already nullable. The notification to the trigger user
  is skipped when null.
- Command **`collection:tick`**, scheduled in `routes/console.php`
  `everyMinute()->withoutOverlapping()->onOneServer()`:
    1. Load the schedule; exit if `enabled` is false.
    2. `now` in the schedule timezone; `slot = now->format('H:i')`; exit if
       `slot` is not in `times`.
    3. `slotKey = date + slot`; exit if equal to `last_slot_key` (a slot runs
       at most once, even if the scheduler ticks twice).
    4. Store `last_slot_key` first (atomic update `where last_slot_key is
distinct from slotKey`; if 0 rows updated, exit), then call
       `StartCollectionRun::handle(null)`.
    5. On `CollectionRunException` (already running / no active sources):
       `last_result` = "Skipped: <message>". On success: "Started run #<id>".
       Always set `last_dispatched_at`.
       Missed slots (server down at that minute) are **not** caught up; the next
       slot runs normally.
- Command **`collection:run`**: manual run now through
  `StartCollectionRun::handle(null)`; prints the run id or the reason. Works
  even when the schedule is paused (pausing only affects automatic runs).

### B.4 Admin page (Filament `/admin`, group "Collection")

Page **Collection schedule** (custom Filament page, form + infolist):

- Status header: "Automatic collection is **on/off**", next run at
  (computed from times + timezone, "today 18:00" / "tomorrow 06:00"), last
  result + time, link to the latest `CollectionRun`.
- Form: `enabled` toggle, `times` (repeater or `TagsInput` validated as
  `HH:MM`, 1–12 unique values, sorted on save), `timezone` (searchable
  select of `DateTimeZone::listIdentifiers()`), Save. Helper text: "Runs per
  day = number of times. A run already in progress makes the next slot
  skip."
- Header actions: **Pause** / **Resume** (flip `enabled`, confirm),
  **Run now** (same as the existing "Collect jobs now": `StartCollectionRun`
  with the admin as trigger; show the exception message as a danger
  notification).
- Realtime refresh with the driver's listener on `collection_schedule` and
  `collection_runs`; never poll.

### B.5 Production note (documentation only, in this spec)

The Laravel scheduler must run: locally `php artisan schedule:work` (added
to `composer dev`), in production one cron entry
`* * * * * php /path/artisan schedule:run >> /dev/null 2>&1` (configured in
`production-readiness`).

## Acceptance criteria

- **AC01** With times `["06:00","18:00"]` and the scheduler running, exactly
  one collection run starts at each time in the configured timezone, and
  none at other minutes.
- **AC02** Changing times/timezone/enabled in the admin takes effect at the
  next tick without a deploy or restart.
- **AC03** Pause stops automatic runs; Resume restarts them; Run now works in
  both states.
- **AC04** If a run is still in progress at a slot, the slot is skipped and
  `last_result` says so; no second run is started.
- **AC05** Running `collection:tick` twice in the same minute starts at most
  one run.
- **AC06** `php artisan collection:run` starts a run manually and prints its id.
- **AC07** A finished run with new jobs refreshes the client Jobs and
  Dashboard live (`jobs.collected`, from `client-core-wiring`).
- **AC08** The admin page shows next run, last result and state, updating
  live.

## Verification

`composer lint:check`, `composer types:check`, existing tests; manual: set a
time 2 minutes ahead, run `php artisan schedule:work`, watch the run appear.

## Out of scope

Per-source schedules (`interval_minutes`), catching up missed slots,
notifications to clients about new jobs (possible later).

## Owner decisions

None open.
