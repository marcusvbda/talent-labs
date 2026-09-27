# Plan — client-app-screens — every client screen as a navigable prototype on typed fixtures

Source spec: `docs/features/client-app-screens/spec.md` · SHA-256
`2624433ba122f9a645d1819cbfdcdaf5327014cfcc6b203a8aab927982e704bb`
Product truth: `docs/features/client-app-screens/spec.md` (Part B)
Run phases with `/execute-phases docs/features/client-app-screens/plan.md <phases>` —
one or a few per session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                                                         | Role             | Depends on         | Size | Status  |
| ----- | ----------------------------------------------------------------------------- | ---------------- | ------------------ | ---- | ------- |
| 1     | Data contracts, realtime contracts, endpoint map, query keys                  | inertia-frontend | none               | S    | DONE    |
| 2     | `LockOverlay` extraction + `Stepper` primitive + styleguide                   | inertia-frontend | none               | M    | DONE    |
| 3     | Fixture catalog I: companies, jobs, stack suggestions                         | inertia-frontend | 1                  | M    | DONE    |
| 4     | Fixture catalog II: applications, profiles, preferences, plans, notifications | inertia-frontend | 1, 3               | M    | DONE    |
| 5     | Fixture state store, sending engine, DevToolbar simulations                   | inertia-frontend | 3, 4               | M    | DONE    |
| 6     | Hooks I: account status, dashboard, chart                                     | inertia-frontend | 5                  | S    | DONE    |
| 7     | Hooks II: live sending, pause, resume, jobs, job detail                       | inertia-frontend | 5                  | S    | DONE    |
| 8     | Hooks III: applications, drafts, queueing mutations                           | inertia-frontend | 5                  | M    | DONE    |
| 9     | Hooks IV: preferences, preview, profiles, CV, template preview                | inertia-frontend | 5                  | M    | DONE    |
| 10    | Hooks V: plans, notifications, account, onboarding basics                     | inertia-frontend | 5                  | M    | DONE    |
| 11    | Realtime cache wiring (`useRealtimeCache` in `AppLayout`)                     | inertia-frontend | 6, 7, 8            | S    | DONE    |
| 12    | S7 Plans page + `PlanGate` links                                              | inertia-frontend | 10                 | M    | DONE    |
| 13    | S6 Preferences page                                                           | inertia-frontend | 9                  | M    | DONE    |
| 14    | S5 Profiles page                                                              | inertia-frontend | 9                  | M    | DONE    |
| 15    | S2 Jobs page: filters, detail sheet, selection, confirm modal                 | inertia-frontend | 7, 8, 12, 13, 14   | M    | PENDING |
| 16    | S3 Review modal (Pro review mode)                                             | inertia-frontend | 8, 15              | M    | PENDING |
| 17    | S4 Applications page + detail sheet                                           | inertia-frontend | 8, 11              | M    | PENDING |
| 18    | S9 Onboarding, 4 steps                                                        | inertia-frontend | 2, 9, 10, 13, 14   | M    | PENDING |
| 19    | S1 Dashboard part 1: header, hero, KPI tiles, chart                           | inertia-frontend | 6, 11, 15          | M    | PENDING |
| 20    | S1 Dashboard part 2: live sending card                                        | inertia-frontend | 7, 11, 19          | M    | PENDING |
| 21    | S1 Dashboard part 3: matches, activity, setup card, Gmail banner              | inertia-frontend | 15, 16, 17, 18, 20 | M    | PENDING |
| 22    | S8 Account page                                                               | inertia-frontend | 10                 | M    | PENDING |
| 23    | S10 Auth pages: register, closed, forgot, reset                               | inertia-frontend | 10                 | M    | PENDING |
| 24    | S11 Notifications popover + realtime toasts                                   | inertia-frontend | 10, 11, 12, 17, 22 | M    | PENDING |
| 25    | S12 Command palette                                                           | inertia-frontend | 7, 13, 14, 15, 17  | S    | PENDING |
| 26    | Verification and report                                                       | qa-tester        | 1–25               | S    | PENDING |

## Audit — 2026-09-27

| Check                          | Result                                                                                                                                                                                                                                                                                                    |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `client-app-foundation` status | `plan.md` phases 1–32 all DONE; kit, data layer, i18n, shell, login present                                                                                                                                                                                                                               |
| Laravel / Filament / Inertia   | 13.33.0 / 5.8.4 / inertia-laravel 3.3.4, `@inertiajs/react` ^3                                                                                                                                                                                                                                            |
| React / Tailwind / Vite        | React 19.2 + React Compiler (babel preset), Tailwind 4, `vite-plus` 0.3.0                                                                                                                                                                                                                                 |
| New dependencies needed        | **None.** Everything in the spec is built from the existing kit                                                                                                                                                                                                                                           |
| Design kit present             | 31 `components/ui/*`, 22 `components/patterns/*`, incl. `PlanGate`, `FilterBar`, `StickyActionBar`, `JobRow`, `QueueRow`, `LiveStepper`, `CountdownBar`, `BarChart`, `TickMeter`, `FileDrop`, `Modal`, `Sheet`, `Popover`, `Menu`, `Tabs`, `MultiSelect`, `TagsInput`, `RadioGroup`, `Segmented`, `toast` |
| Data layer present             | `data/api.ts` (`apiFetch`, `ApiError`), `data/query-client.ts` (no `refetchInterval`), `data/source.ts` (`fromSource`), `data/keys.ts` (4 keys, **no consumer yet — free to restructure**), `data/define-query.ts` (`initialDataFrom`)                                                                    |
| Fixtures present               | `fixtures/runtime.ts` (`fixtureCall`, 350 ms ±40 %, loading/empty/error/failNext), `fixtures/dev-state.ts`, `fixtures/store.ts` (`createFixtureStore`) — **no fixture data and no fixture handlers exist yet**                                                                                            |
| Realtime present               | `realtime/use-user-channel.ts` (private `App.Models.User.{id}`, fixture transport via `dev-emitter`), `realtime/dev-emitter.ts` with `registerSimulation` — **no simulation is registered, so the 3 DevToolbar buttons are no-ops**                                                                       |
| `VITE_USE_FIXTURES`            | Present in `.env.example` and `.env`, both `true`                                                                                                                                                                                                                                                         |
| Routes existing                | `/` (`home`), `/dashboard` (`dashboard`, `auth`+`client`), `/login`, `/logout`, `/locale`, `integrations.oauth.{connect,reconnect,disconnect,callback}`, `/dev/styleguide` (local only)                                                                                                                   |
| Routes missing                 | `/jobs`, `/applications`, `/profiles`, `/preferences`, `/plans`, `/account`, `/onboarding`, `/register`, `/register/closed`, `/forgot-password`, `/reset-password/{token}` — all added by their screen phase                                                                                              |
| Nav state                      | `lib/navigation.ts` renders jobs/applications/profiles/preferences/plans as `disabled` placeholders; each screen phase enables its own item                                                                                                                                                               |
| TopBar state                   | Bell and search pill are inert (no popover, no palette); `UserMenu` has language + logout only, no Account entry                                                                                                                                                                                          |
| Current dashboard              | `pages/dashboard.tsx` is a 491-line static composition on a `DEMO` constant (owner-approved visuals); rewritten to live data in phases 19–21                                                                                                                                                              |
| `/internal/*` endpoints        | None exist. Spec B.3 accepts typed placeholders in `data/endpoints.ts`; in real mode these paths 404, which is expected until the backend specs                                                                                                                                                           |
| `cover_letter` variable        | `ApplicationTemplateRenderer::ALLOWED_VARIABLES` has 5 variables and **no `cover_letter`**; the spec contract has 6 → D2                                                                                                                                                                                  |
| Default templates              | Only `DEFAULT_SUBJECT` / `DEFAULT_BODY` in English exist (PHP constants); no PT/ES defaults → D3                                                                                                                                                                                                          |
| Seniority values               | `ExtractJobPostingProfile::SENIORITIES = intern, junior, mid, senior, lead, unknown` — matches the contract exactly                                                                                                                                                                                       |
| Gmail state naming             | `DevState.gmail` is `connected \| needs_reconnection \| disconnected`; the contract is `connected \| reauthorization_required \| disconnected` → mapped in the fixture layer (Phase 5), `DevState` is not renamed                                                                                         |
| `format.list`                  | Only `type: 'conjunction'`; spec B.8 needs disjunction ("or") for values inside a preference field → extended in Phase 13                                                                                                                                                                                 |
| `BarChart`                     | No daily-limit line; `limit` + legend added in Phase 19                                                                                                                                                                                                                                                   |
| Database                       | 16 migrations, all Ran. **This spec changes nothing in the database**                                                                                                                                                                                                                                     |
| i18n                           | `lang/{en,pt,es}.json`, 389 keys each, key sets identical (verified)                                                                                                                                                                                                                                      |
| `yarn types:check`             | passes (`tsc --noEmit`)                                                                                                                                                                                                                                                                                   |
| `yarn build`                   | passes (4.8 s)                                                                                                                                                                                                                                                                                            |
| `composer types:check`         | passes (phpstan, 0 errors)                                                                                                                                                                                                                                                                                |
| `php artisan test`             | passes (2 tests)                                                                                                                                                                                                                                                                                          |
| **`yarn check` is a trap**     | Yarn Classic resolves `yarn check` to its own built-in ("Folder in sync"), **not** to `vp check`. The real gate is **`yarn run check`**                                                                                                                                                                   |
| `yarn run check`               | **fails on the baseline**: formatting issues in `docs/features/client-app-foundation/plan.md` → D1                                                                                                                                                                                                        |
| `composer lint:check`          | **fails on the baseline**: pint `function_declaration` in `app/Outreach/Support/ApplicationTemplateRenderer.php` → D1                                                                                                                                                                                     |

## Owner decisions

None of these block a phase: each has a default the executor proceeds on
unless the owner says otherwise in the `/execute-phases` message.

### D1 — clear the two pre-existing gate failures, or measure against them?

Blocks: nothing (Phase 26 reports either way) · Options:
**A (recommended)** — the owner authorizes one formatting fix-up in the Phase 1
message ("fix the baseline formatting"), and the executor runs
`yarn run check --fix` and `composer lint`, which touch exactly
`docs/features/client-app-foundation/plan.md` (markdown) and
`app/Outreach/Support/ApplicationTemplateRenderer.php` (`fn(` → `fn (`), so
every later phase has a green gate. **B** — leave both; every phase compares
the gate output against this known baseline and only fails on new entries.
Why: with B, a red gate stops being a signal, and the two items are pure
formatting on files this spec otherwise never touches.

### D2 — `cover_letter` is in the spec contract but not in the PHP renderer

Blocks: nothing (Phases 14, 18) · Options: **A (recommended)** — keep the
frontend contract as B.2 writes it (6 variables, `cover_letter` included),
and record that `ApplicationTemplateRenderer::ALLOWED_VARIABLES` must gain
`cover_letter` in the backend spec that implements profiles
(`application-languages`). **B** — add the variable to the PHP constant now.
Why: this spec is frontend only (spec header); touching `app/` here would be
out of scope and untested against the real sending pipeline.

### D3 — default subject/body per language (new profile, onboarding step 3)

Blocks: nothing (Phases 14, 18) · Options: **A (recommended)** — fixtures own
`defaultTemplates: Record<JobLanguage, { subject; body }>`; EN is copied
verbatim from `ApplicationTemplateRenderer::DEFAULT_SUBJECT` /
`DEFAULT_BODY`, PT and ES are faithful translations of the same two strings
(Brazilian PT, neutral ES), none of them containing `{{ cover_letter }}` —
the client inserts it with the variable chips. **B** — all three languages
start from the English default. **C** — the owner supplies the three pairs.
Why: A keeps the prototype honest about what the backend will send for EN and
still shows a PT/ES client a template in their language.

## Global constraints (every phase)

- **Frontend only.** No migration, no seeder, no `app/` change, no new route
  besides the `Route::inertia(...)` lines in B.5, no queue/job/mail code. The
  only PHP touched in the whole plan is `routes/web.php`.
- **No new dependencies.** Not composer, not npm (`CLAUDE.md`).
- **Never run git writes.** No `add`, `commit`, `push`, `checkout`, `stash`,
  `restore`, `reset` (`CLAUDE.md`). Reporting a diff is fine.
- **Never write or modify tests** unless the owner asks in the
  `/execute-phases` message (`CLAUDE.md`). Running them is fine.
- **The gate is `yarn run check`, not `yarn check`** (see the audit).
  Per phase: `yarn run check`, `yarn types:check`, `yarn build`. Add
  `composer lint:check` + `composer types:check` only in the phases that
  touch `routes/web.php`. `php artisan test` in Phase 26.
- **URLs:** page navigation always through Wayfinder helpers from `@/routes`
  (`jobs()`, `plans()`, …). The `/internal/*` API paths come **only** from
  `data/endpoints.ts`; this is the one place the foundation B.7 rule
  "URLs always come from Wayfinder route helpers" is deliberately suspended,
  because those routes do not exist yet. No `/internal` string literal
  outside `data/endpoints.ts`.
- **No polling, ever.** No `refetchInterval`, no `setInterval` that fetches,
  no `wire:poll`, no `->poll()`. Countdowns and elapsed bars tick with one
  local 1 s timer that only changes what is displayed (the pattern already
  in `CountdownBar`).
- **Only files under `resources/js/data/` may import `@/data/source`.**
  Components and pages call hooks and never know whether the data is real or
  fixture.
- **Every hook is a `{ real, fixture }` pair** through `fromSource(...)`, and
  every fixture handler goes through `fixtureCall(...)` so the DevToolbar
  loading / empty / error / "simulate failure" switches work on it
  (foundation B.7, spec B.4 last line).
- **Every page hook accepts an optional Inertia prop** and passes it through
  `initialDataFrom(prop)`; no page in this spec is given props yet.
- **The client never sees:** a job URL, a recipient email address, a company
  website or domain. Fixture data must not contain any of them. Rendered
  template bodies keep `{{ job_url }}` as a token and show it as a
  non-editable "job link" chip.
- **i18n:** every visible string via `t()` / `plural()`; every new key added
  to `lang/en.json`, `lang/pt.json` and `lang/es.json` in the same phase, so
  the three key sets always match. Brazilian Portuguese, neutral Spanish.
  Numbers, currencies, dates and lists only through `lib/format.ts`.
- **Styling:** existing tokens and kit components only, no raw hex, no
  arbitrary pixel values where a token exists, `cn()` for class merging.
  Any genuinely new primitive or pattern is registered on `/dev/styleguide`
  in the same phase (AC15). Feature-local compositions under
  `resources/js/features/**` are not primitives and stay off the styleguide.
- **Responsive** per foundation B.5 at 360 / 768 / 1024 / 1440 px, no
  horizontal page scroll; `Modal` and `Sheet` become full-screen / bottom
  sheets on mobile.
- **Scope:** implement only the phase. Extra ideas are reported, not built.
- **File budget:** about 8 entries per phase; the three `lang/*.json` files
  count as one entry, since they are always edited together.

## Acceptance-criteria coverage

| AC                                                                           | Phases                                                 |
| ---------------------------------------------------------------------------- | ------------------------------------------------------ |
| AC01 contracts exist, everything typed against them                          | 1, 3, 4, 5, 6, 7, 8, 9, 10, 26                         |
| AC02 all routes render, every link and control reaches its target            | 12, 13, 14, 15, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26 |
| AC03 every screen on fixture data with loading / empty / error               | 3, 4, 5, 12–25, 26                                     |
| AC04 DevToolbar plan switch changes the mode everywhere                      | 5, 15, 16, 21, 26                                      |
| AC05 queueing runs the stages and updates every surface live                 | 5, 8, 11, 15, 16, 19, 20, 21                           |
| AC06 pause / resume / simulated failure                                      | 5, 7, 17, 20                                           |
| AC07 quota block and one application per company                             | 15, 21                                                 |
| AC08 no job URL, recipient address or company domain anywhere                | 3, 4, 14, 15, 16, 17, 26                               |
| AC09 profiles CRUD, CV rules, variables, preview, dirty guard, locked notice | 9, 14, 15                                              |
| AC10 preferences rule banner, summary sentence, counter, location rule       | 13                                                     |
| AC11 onboarding 4 steps, setup card and lock overlays                        | 2, 18, 21                                              |
| AC12 register / closed / forgot / reset                                      | 23                                                     |
| AC13 EN/PT/ES complete and identical key sets                                | every phase, verified in 26                            |
| AC14 responsive at 360 / 768 / 1024 / 1440                                   | every screen phase, verified in 26                     |
| AC15 kit reuse, new primitives on the styleguide, no raw hex, no polling     | 2, 25, 26                                              |

## Phases

### Phase 1 — Data contracts, realtime contracts, endpoint map, query keys

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (`docs/features/client-app-foundation/plan.md`, D1-B); no `/internal` literal outside `data/endpoints.ts`; single `PlanKey`/`Locale` definition in `types/contracts.ts`; `code-reviewer` APPROVED.
Role: inertia-frontend · Depends on: none · Covers: AC01 · Size: S
Spec: B.2, B.3

**Goal.** Create the two contract files exactly as the spec writes them, the
single endpoint map, and the query-key factory every later hook uses. No UI
change.

**Contract.**

- `resources/js/types/contracts.ts` — copy the whole B.2 TypeScript block
  **verbatim**: `ISODateTime`, `ISODate`, `Locale`, `JobLanguage`, `PlanKey`,
  `SendMode`, `RegionKey`, `Seniority`, `RemoteMode`, `ApplicationStatus`,
  `SendStage`, `SubStep`, `TemplateVariable`, `CompanyRef`, `Quota`,
  `AccountStatus`, `JobCard`, `JobDetail`, `JobFilters`, `Paginated<T>`,
  `JobsPage`, `ApplicationItem`, `ApplicationDetail`, `ApplicationFilters`,
  `DashboardPeriod`, `DashboardData`, `ChartData`, `LiveSending`,
  `ReviewDraft`, `QueueResult`, `Preferences`, `PreferencesPreview`,
  `ApplicationProfile`, `ProfilesData`, `TemplatePreview`, `PlanOffer`,
  `PlansData`, `NotificationType`, `NotificationItem`, `Account`,
  `OnboardingBasics`, `InviteCheck`. Keep the spec's comments. Renames are
  forbidden; additions are allowed but none are needed here.
- One source of truth for the duplicated aliases:
    - `resources/js/types/plans.ts` keeps `PLAN_KEYS` and becomes
      `export type { PlanKey } from './contracts';`
    - `resources/js/types/shared.ts` becomes
      `export type { Locale } from './contracts';` and keeps the rest.
      Nothing else changes; `Locale` stays importable from `@/types/shared`.
- `resources/js/types/realtime.ts` — the two B.2 realtime maps verbatim:
  `UserChannelEvents` (`application.progressed`, `sending.updated`,
  `account.updated`, `notification.created`) and `JobsChannelEvents`
  (`jobs.collected`), with the comment that Echo listens with a leading dot.
- `resources/js/data/endpoints.ts` — one exported object, every entry a
  function returning `{ url: string; method: 'get' | 'post' | 'put' | 'delete' }`,
  so a later spec can swap an entry for a Wayfinder helper without touching
  the hooks. All paths prefixed `/internal`, exactly the B.3 table:
  `accountStatus`, `dashboard(period)`, `chart(range)`, `sending`,
  `pauseSending`, `resumeSending`, `jobs(filters)`, `job(id)`,
  `queueApplications`, `reviewDrafts`, `queueReviewed`,
  `applications(filters)`, `application(id)`, `preferences` (get),
  `savePreferences` (put), `preferencesPreview`, `profiles`, `createProfile`,
  `saveProfile(language)`, `deleteProfile(language)`, `uploadCv(language)`,
  `deleteCv(language)`, `templatePreview(language)`, `plans(region?)`,
  `notifications`, `markAllNotificationsRead`, `account` (get),
  `saveAccount` (put), `changePassword`, `deleteAccount`,
  `saveOnboardingBasics`, `accountExport`. Query strings built with
  `URLSearchParams`, `undefined` / `null` / `'all'`-as-default values omitted.
- `resources/js/data/keys.ts` — replace the placeholder factory with:
  `account.status()`, `account.self()`, `dashboard(period)`, `chart(range)`,
  `sending()`, `jobs.all()`, `jobs.list(filters)`, `jobs.detail(id)`,
  `applications.all()`, `applications.list(filters)`,
  `applications.detail(id)`, `preferences.current()`,
  `preferences.preview(draft)`, `profiles()`, `plans(region)`,
  `notifications()`. Every entry returns an `as const` tuple whose first
  element is the group name, so `invalidateQueries({ queryKey: keys.jobs.all() })`
  matches every jobs query. It has no consumer yet (verified in the audit).

**Steps.**

1. Write `types/contracts.ts` from B.2, then `types/realtime.ts`.
2. Point `types/plans.ts` and `types/shared.ts` at `contracts.ts` for
   `PlanKey` and `Locale`.
3. Write `data/endpoints.ts` and rewrite `data/keys.ts`.
4. Run the gate.

**Done when.**

- `resources/js/types/contracts.ts` contains every B.2 type with the spec's
  names, and no second definition of `PlanKey` or `Locale` exists in
  `resources/js/types/`.
- `grep -rn "'/internal" resources/js` returns only `data/endpoints.ts`.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** No hook, no fixture, no component.

### Phase 2 — `LockOverlay` extraction + `Stepper` primitive + styleguide

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (D1-B); lang key sets identical (401 each); `plan-gate.tsx` no longer holds overlay markup; `code-reviewer` APPROVED (visual check on `/dev/styleguide` not performed in a browser).
Role: inertia-frontend · Depends on: none · Covers: AC11, AC15 · Size: M
Spec: B.6 S1 (setup state), B.6 S9, foundation B.10

**Goal.** Extract the generic overlay base out of `PlanGate` (the spec asks
for it explicitly: "the generic base of `PlanGate`, extract it now") and add
the one missing primitive the onboarding wizard needs, both on the
styleguide.

**Contract.**

- `resources/js/components/patterns/lock-overlay.tsx` — new:
  `LockOverlay({ locked, title, description, actions, radius = 'tile', children })`,
  `radius` keyed `tile | card | card-sm` as in `PlanGate` today. Same
  mechanics as the current `PlanGate` body: one `grid`, children in
  `col-start-1 row-start-1` with `inert` + `aria-hidden`, overlay in the same
  cell with `bg-scrim backdrop-blur-xs`, the 46 px `bg-ink` disc with the
  `Lock` icon, title, description, `actions` row. Returns `children`
  unchanged when `locked` is false.
- `plan-gate.tsx` — keeps its exact public props
  (`locked`, `requiredPlans`, `featureKey`, `radius`, `children`) and its
  rendered result, but builds `title` / `description` / `actions` and
  delegates to `LockOverlay`. Visual output must not change.
- `resources/js/components/ui/stepper.tsx` — new:
  `Stepper({ steps, currentIndex, onStepChange?, ariaLabel })` with
  `steps: { key: string; label: string; done: boolean }[]`. Horizontal
  numbered discs joined by a rule, `StatusDisc` for done steps, current step
  emphasised, a `ProgressBar` under it for `currentIndex / steps.length`;
  a done step is a button when `onStepChange` is given (spec S9: "Steps
  already done show as done and can be revisited"), otherwise plain text.
  On mobile it collapses to "Step 2 of 4 · <label>" plus the progress bar.
- New i18n keys: `patterns.lock.title` is not needed (callers pass copy);
  add `patterns.stepper.progress` = `"Step :current of :total"` and
  `patterns.stepper.step` = `"Step :number"` in the three files.
- Styleguide: `features/styleguide/gating-section.tsx` gains a `LockOverlay`
  example (locked, with a title, a line and one button) next to the existing
  `PlanGate` ones; `features/styleguide/navigation-section.tsx` gains a
  `Stepper` example (4 steps, second current, first done) — plus their
  `styleguide.*` labels in the three lang files.

**Steps.**

1. Extract `LockOverlay`, rewire `PlanGate`, confirm the gating styleguide
   section still looks identical for `PlanGate`.
2. Add `Stepper` and its styleguide entry.
3. Add the i18n keys to `en`, `pt` and `es`.
4. Run the gate.

**Done when.**

- `/dev/styleguide` shows `LockOverlay` and `Stepper`, and the `PlanGate`
  examples are visually unchanged.
- `plan-gate.tsx` no longer contains the overlay markup.
- The three lang files have the same key set.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** No page, no data. `PlanGate`'s `#` links are fixed in
Phase 12, when the Plans route exists.

### Phase 3 — Fixture catalog I: companies, jobs, stack suggestions

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (D1-B); catalog evaluated: 60 jobs / 30 companies, languages 33/18/9, 20 collectedToday, no `url` key; AC08 grep over `fixtures/catalog` returns nothing; `code-reviewer` APPROVED (fixed 2 pt rows to Brazilian PT, renamed a look-alike company).
Role: inertia-frontend · Depends on: 1 · Covers: AC01, AC03, AC08 · Size: M
Spec: B.4

**Goal.** The static half of the fixture world: companies and the 60-job
pool, typed against `contracts.ts`, with no URL or domain anywhere.

**Contract.**

- `resources/js/data/fixtures/catalog/companies.ts` — the 25 fictional
  companies named in B.4 plus 5 more in the same style, reaching ~30:
  Klarwerk (Berlin), Lumen Health (Lisbon), Estrela Pay (São Paulo), Nuvia
  (Madrid), Cobalt Freight (Remote EU), Pampa Logística (Porto Alegre),
  Brisa Seguros (Recife), Norte Analytics (Porto), Arcadia Games
  (Barcelona), Fjord Mobility (Oslo), Tessera Cloud (Dublin), Maré Energia
  (Florianópolis), Solvio (Amsterdam), Quanta Retail (Remote), Oriol Studio
  (Valencia), Duna Fintech (Belo Horizonte), Kestrel Security (London), Alto
  Commerce (Remote LATAM), Ribeira Tech (Coimbra), Faro Data (Remote), Helix
  Bio (Munich), Tinta Media (Buenos Aires), Vértice (Curitiba), Moraga
  Systems (Bilbao), Kiln (Remote US). Each row is
  `CompanyRef & { city: string | null; isRemote: boolean }` with a stable
  numeric `id` and `initials` derived once (first letters of the first two
  words, uppercase). **Never a real company name, never a domain.**
- `resources/js/data/fixtures/catalog/jobs.ts` — exactly 60 `JobDetail`
  rows (a `JobDetail` is a `JobCard` plus the detail fields, so one array
  serves both list and detail): 33 `en`, 18 `pt`, 9 `es` (≈55/30/15 %);
  titles realistic per language ("Senior Backend Engineer, PHP / Laravel",
  "Desenvolvedor Full-stack Pleno", "Ingeniero Backend (Go)"); `seniority`
  spread over all six values including a few `unknown`; `stack` 2–6
  canonical lowercase-free display names; `summary` one client-safe sentence
  **in the job's own language**; `location`/`locations`/`isRemote`
  consistent with the company; `employmentType`, `department`,
  `publishedAt`; `sourceLabel` one of "RemoteOK", "WeWorkRemotely",
  "Greenhouse", "Lever", "Workable" — plain text, never a link. **No `url`
  field on any row.** 20 rows carry `collectedToday: true` with
  `firstSeenAt` inside today; the rest are spread over the previous 13 days.
  Timestamps are generated relative to `Date.now()` at module load through
  one helper (`daysAgo`, `hoursAgo`) so the fixture never goes stale.
- `resources/js/data/fixtures/catalog/stacks.ts` — `STACK_SUGGESTIONS`, ~40
  common technologies for the Preferences stack `TagsInput` (React, Vue,
  Laravel, PHP, Node.js, TypeScript, Go, Python, Django, Rails, Kotlin,
  Swift, PostgreSQL, MySQL, Redis, Docker, Kubernetes, AWS, GCP, Terraform,
  …), and `SENIORITY_KEYS: Seniority[]` for the choice chips.
- `resources/js/data/fixtures/catalog/index.ts` — re-exports the three.

**Steps.**

1. Write `companies.ts` with the relative-time helpers it shares.
2. Write `jobs.ts`, checking language / seniority / today distributions.
3. Write `stacks.ts` and `index.ts`.
4. Run the gate.

**Done when.**

- `jobs.ts` exports 60 rows typed `JobDetail[]`, 20 with
  `collectedToday: true`, languages split 33/18/9.
- `grep -riE "https?://|\\.com|\\.io|\\.dev|@" resources/js/data/fixtures/catalog`
  returns nothing (no URL, domain or address in fixture content).
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** No mutable state, no handler, no hook.

### Phase 4 — Fixture catalog II: applications, profiles, preferences, plans, notifications

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (D1-B); evaluated catalog: 420 historical sent + today 18 sent / 12 queued / 1 failed / 2 ambiguous, `es` has no profile, prices in minor units, billing false; AC08 grep clean; `code-reviewer` APPROVED. Deliberate deviation: companies grown 30→44 (33 today's rows on 33 distinct companies, 11 companies free today) so the one-application-per-company-per-day rule still lets queueing succeed.
Role: inertia-frontend · Depends on: 1, 3 · Covers: AC01, AC03, AC08 · Size: M
Spec: B.4

**Goal.** The rest of the static fixture world, all typed against
`contracts.ts`.

**Contract.**

- `catalog/applications.ts` — a generator producing **420** `ApplicationItem`
  rows over the last 30 days with realistic per-day counts and weekend dips
  (weekdays ~16–20, weekends 0–3), all `status: 'sent'` with `sentAt` inside
  the working window; **today on top of that**: 18 `sent`, 12 `queued` with
  staggered `scheduledFor` 45–120 s apart, 1 `failed`, 2 `ambiguous`.
  `origin` mixed `auto`/`manual`; `language` following the job's language;
  `company`/`title` taken from `catalog/jobs.ts` so detail views line up.
  `lastError` only on `failed`/`ambiguous`, as client-safe, already
  translated sentences: `"Your CV file could not be read."`,
  `"The company mailbox rejected the message."`,
  `"We could not confirm delivery."` (EN strings live in the catalog because
  the contract says the backend sends them translated; the DevToolbar locale
  does not re-translate them).
- `catalog/applications.ts` also exports `detailFor(item)` building
  `ApplicationDetail`: `subject`, `body` (rendered from the language's
  default template with `{{ job_url }}` **left as the token**),
  `cvFileName`, and a `timeline` of the stages the row reached with times.
- `catalog/profiles.ts` — `ApplicationProfile[]`: `en` active and complete,
  `pt` active and complete, **`es` not created at all** (so ES jobs are
  locked by language); each complete one has
  `cv: { fileName: 'ana-silva-cv.pdf', sizeBytes: 284_612, uploadedAt }`,
  subject, body, cover letter, `complete: true`, `missing: []`. Also
  `TEMPLATE_VARIABLES: TemplateVariable[]` in the B.2 order and
  `defaultTemplates: Record<JobLanguage, { subject: string; body: string }>`
  per D3 option A (EN verbatim from
  `ApplicationTemplateRenderer::DEFAULT_SUBJECT` / `DEFAULT_BODY`).
- `catalog/preferences.ts` — one `Preferences` row that actually matches the
  job pool: `titles: ['Backend', 'Full-stack', 'Frontend']`,
  `seniorities: ['mid', 'senior', 'lead']`,
  `stack: ['Laravel', 'React', 'TypeScript']`,
  `locations: ['Berlin', 'Lisbon', 'São Paulo', 'Remote']`,
  `remoteMode: 'remote_or_locations'`, `excludeWords: ['WordPress', 'Intern']`.
- `catalog/plans.ts` — `PLAN_OFFERS`: free 25/day `auto`, starter 50/day
  `select`, pro 150/day `review`; `highlighted` on starter; prices as
  **integer minor units** per region — `br` BRL `{0, 4900, 9900}`, `eu` EUR
  `{0, 1900, 3900}`, `row` USD `{0, 1900, 3900}`; `interval: 'month'`;
  `regions: [{ br, BRL }, { eu, EUR }, { row, USD }]`;
  `billingAvailable: false`.
- `catalog/notifications.ts` — 8 `NotificationItem` rows covering all five
  `NotificationType` values, 3 unread, `data` carrying only the params the
  i18n line needs (`company`, `count`, `time`), relative `createdAt` over
  the last 2 days.

**Steps.**

1. Write the application generator and check the daily distribution sums to
   420 plus today's rows.
2. Write profiles (with `defaultTemplates`), preferences, plans,
   notifications.
3. Extend `catalog/index.ts`.
4. Run the gate.

**Done when.**

- The catalog exports 420 historical sent applications plus today's 18 sent /
  12 queued / 1 failed / 2 ambiguous, and `es` has no profile.
- Plan prices are minor units and `billingAvailable` is `false`.
- The URL/domain/address grep from Phase 3 still returns nothing for
  `resources/js/data/fixtures/catalog`.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** Mutable state and the sending simulation (Phase 5).

### Phase 5 — Fixture state store, sending engine, DevToolbar simulations

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass (engine is a separate lazy chunk behind the fixtures flag via `data/boot-fixtures.ts`); `yarn run check` fails only on the known baseline entry (D1-B). Virtual-clock runs: send walks 10 sub-steps with `application.progressed` / `sending.updated` / `account.updated`; failure ends `failed` at `attaching_cv` with the CV error + notification; pause/resume continues from the same sub-step; `jobs.collected` emitted; auto mode on Free ran 18→25 and stopped at the limit with exactly one `daily_limit_reached`, at most 1 timer alive, no duplicate company per day; `matches()` = 9; queue() rejections (limit, company today, no profile) verified. `code-reviewer` APPROVED; 3 non-blocking findings fixed. Known limitation: the send window is only a label, the simulated engine still sends outside it.
Role: inertia-frontend · Depends on: 3, 4 · Covers: AC03, AC04, AC05, AC06 · Size: M
Spec: B.4 (in-memory store), B.2 (`LiveSending`, `SendStage`, `SubStep`), B.7

**Goal.** One in-memory world that mutations actually change, and the
simulated sender that walks the queue through the real pipeline stages and
emits the four user-channel events. After this phase the three DevToolbar
simulate buttons stop being no-ops.

**Contract.**

- `resources/js/data/fixtures/state.ts` — `fixtureState = createFixtureStore(initial)`
  holding: `companies`, `jobs` (pool), `applications`, `profiles`,
  `preferences`, `notifications`, `sentToday`, `paused`,
  `sendingApplicationId`, `currentStage`, `currentSubStep`, `failNextSend`,
  and `window = { start: '09:00', end: '18:00', weekdaysOnly: true, timezone: 'Europe/Berlin' }`.
  Plus derived selectors used by the hooks:
    - `planConfig(plan)` → `free: { dailyLimit: 25, mode: 'auto' }`,
      `starter: { 50, 'select' }`, `pro: { 150, 'review' }`.
    - `quota()` → `{ usedToday, limit, remaining, resetsAt }`, `resetsAt`
      tomorrow 00:00 local.
    - `accountStatus()` → the full `AccountStatus`, with
      `plan` from `getDevState().plan`, `gmail.state` mapped from
      `DevState.gmail` (`needs_reconnection → 'reauthorization_required'`,
      others 1:1) and `accountEmail` `null` when disconnected,
      `sending.paused` true when paused or when Gmail needs reauthorization,
      `autoPausedReason` `'reauthorization_required'` in that case,
      `onboarding` from `DevState.onboardingComplete` (all four steps done, or
      `basics` done and the rest open), `profiles.activeLanguages` = active and
      complete profiles, `region: 'eu'`, `country: 'DE'`,
      `timezone: 'Europe/Berlin'`, `unreadNotifications` from the list.
    - `liveSending()` → `LiveSending` with `state` resolved in this order:
      `paused` → `limit_reached` (remaining 0) → `outside_window` (local time
      outside the window, weekend when `weekdaysOnly`) → `sending` (an
      application is mid-flight) → `waiting` (queue non-empty) → `idle`;
      `progress = { index: usedToday + 1, total: limit }`, `queue` the next 3
      by `scheduledFor`, `spacing: { minSeconds: 45, maxSeconds: 120 }`.
    - `matches()` → the jobs the preferences match, newest first, excluding
      jobs already queued/sent and jobs whose language has no active complete
      profile; `lockedByLanguage` counts the ones excluded only by that rule.
    - `queue(jobIds, origin)` → creates `queued` applications with
      `scheduledFor` staggered by a random 45–120 s from the last scheduled
      slot, returns `QueueResult`; rejects with a client-safe translated
      `reason` when: the daily quota is exhausted
      (`"You have reached today's limit."`), the company already has an
      application today (`"You already applied to this company today."`), or
      the job's language has no complete profile
      (`"You have no application profile in this language."`).
- `resources/js/data/fixtures/engine.ts` — the simulated sender:
    - `STEP_DELAY_MS = 1200`; stage order
      `validating_recipient → adapting_template → attaching_cv → sending → sent`
      with sub-steps `checking_company, confirming_recipient, checking_gmail` /
      `filling_variables, building_html` / `opening_cv, checking_pdf, attaching_file`
      / `connecting_gmail, delivering` (the B.2 grouping), each sub-step
      lasting `STEP_DELAY_MS`.
    - On every sub-step: `devEmitter.emit('application.progressed', { application })`.
      On every stage change also `sending.updated` with the fresh
      `liveSending()`. When an item reaches `sent`/`failed`: `account.updated`
      with the fresh `accountStatus()`, plus a `notification.created` when a
      send fails or the daily limit is reached.
    - Between items it waits a random 45–120 s (the item's `scheduledFor`);
      `pause()` stops the timer and leaves the current item where it is,
      `resume()` continues. No fetch ever happens from the engine.
    - `failNextSend` makes the next item fail at `attaching_cv` with
      `lastError` `"Your CV file could not be read."`, status `failed`.
    - Auto mode (plan `free`): when the queue empties and the quota allows, the
      engine queues the next matching job by itself, so sends run continuously.
    - Everything is driven by `setTimeout`, cleared on
      `import.meta.hot` dispose, and the engine never runs when
      `useFixtures` is false.
    - `registerSimulation('send', …)` queues one matching job and starts the
      engine; `registerSimulation('failure', …)` sets `failNextSend` and
      starts; `registerSimulation('newJobs', …)` marks 3 pool jobs as collected
      now and emits the public-channel shape
      `devEmitter.emit('jobs.collected', { collectionRunId, newJobs: 3 })`.
- `resources/js/app.tsx` — import the engine for its side effects **only**
  behind the fixtures flag, e.g. a static
  `if (useFixtures) { void import('@/data/fixtures/engine'); }` at boot, so
  production bundles drop it.

**Steps.**

1. Write `state.ts` (store + selectors + `queue`).
2. Write `engine.ts` (stage walker, pause/resume, auto mode, simulations).
3. Boot it from `app.tsx` behind the flag.
4. Manually verify the three DevToolbar buttons emit events (React Query
   devtools / console) even though no screen consumes them yet.
5. Run the gate.

**Done when.**

- Clicking "Simulate send" walks one application through the 5 stages with
  sub-steps and emits `application.progressed`, `sending.updated` and
  `account.updated` (observable in the console).
- "Simulate failure" ends at `attaching_cv` with the CV error;
  "Simulate new jobs" emits `jobs.collected`.
- Pausing through `engine.pause()` stops the timers; resuming continues.
- `grep -rn "refetchInterval" resources/js` returns nothing.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** No hook, no screen.

### Phase 6 — Hooks I: account status, dashboard, chart

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (D1-B); three hooks are `{ real, fixture }` pairs with `fixtureCall` and `empty` variants; handlers evaluated: today sent 18 / failed 1 / queued 12, week KPIs collected 43 (prev 17) and sent 120 (prev 102), chart 14d = 14 days ending today, matches total 9 / newToday 6 / 4 items, activity 5 newest-first; `code-reviewer` APPROVED.
Role: inertia-frontend · Depends on: 5 · Covers: AC01, AC03 · Size: S
Spec: B.3, B.7

**Goal.** The first three read hooks, each a `{ real, fixture }` pair that
honours the DevToolbar state switches.

**Contract.**

- `data/hooks/use-account-status.ts` — `useAccountStatus(initial?: AccountStatus)`:
  `useQuery({ queryKey: keys.account.status(), queryFn, ...initialDataFrom(initial) })`.
  `real`: `apiFetch<AccountStatus>(endpoints.accountStatus().url)`.
  `fixture`: `fixtureCall(() => fixtureState.accountStatus())`.
- `data/hooks/use-dashboard.ts` — `useDashboard(period: DashboardPeriod, initial?)`
  → `DashboardData`. Fixture builds it from the store: `kpis.collected`
  (period count + previous period), `kpis.sent` likewise,
  `kpis.totalSent` = every sent application, `kpis.firstSentAt` = the oldest
  `sentAt`; `hero` = today's sent/failed/queued, `nextSendAt` from
  `liveSending()`, `lastDays` = the 5 days before today oldest first;
  `matches` = `{ total, newToday, items: matches().slice(0, 4) }`;
  `activity` = the 5 newest applications.
  `empty` variant (DevToolbar "empty"): zeroed KPIs, empty `lastDays`,
  `matches.items: []`, `activity: []`.
- `data/hooks/use-chart.ts` — `useChart(range: '14d' | '30d', initial?)`
  → `ChartData`: `days` oldest first including today, `averagePerActiveDay`
  over days with `count > 0` rounded to one decimal, `limit` from the plan.
- All three: no `refetchInterval`, no `refetchOnMount: 'always'`.

**Steps.** 1. Write the three hooks. 2. Check each one against the four
DevToolbar states. 3. Run the gate.

**Done when.**

- Each hook exists with a `fromSource({ real, fixture })` pair and a
  `fixtureCall` wrapper with an `empty` variant where the contract has one.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** UI.

### Phase 7 — Hooks II: live sending, pause, resume, jobs, job detail

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (D1-B) plus the owner's concurrent manual edit of `page-header.tsx` (not ours); `listJobs` re-evaluated: page 1 = 12 rows, total 51 (es locked), summary {51, 17 today, es 9}, 5 pages of unique ids, filters (es 9, q laravel 3, accent-insensitive sênior/senior 5, today 17, lead 7, remote 15 / not_remote 36, react 9); pause/resume optimistic with snapshot rollback, engine loaded by dynamic import; `code-reviewer` APPROVED. Interpretation: `summary` is computed over the language-visible pool, not over the preference matches.
Role: inertia-frontend · Depends on: 5 · Covers: AC01, AC03, AC06 · Size: S
Spec: B.3, B.6 S2

**Goal.** The sending-control hooks and the job list/detail reads, including
cursor pagination.

**Contract.**

- `data/hooks/use-live-sending.ts` — `useLiveSending(initial?)` →
  `LiveSending` on `keys.sending()`.
- `data/hooks/use-pause-sending.ts` — `usePauseSending()` and
  `useResumeSending()`, `useMutation` returning `LiveSending`, **optimistic**:
  `onMutate` writes `state: 'paused'` (resp. recomputed) into
  `keys.sending()` and keeps the snapshot, `onError` rolls back,
  `onSuccess` writes the returned payload. Fixture calls
  `engine.pause()` / `engine.resume()` then returns `liveSending()`.
- `data/hooks/use-jobs.ts` — `useJobs(filters: JobFilters)` with
  `useInfiniteQuery` on `keys.jobs.list(filters)` (the `cursor` excluded from
  the key), `getNextPageParam: (last) => last.meta.nextCursor`, page size 12.
  Returns `JobsPage` pages; `summary` comes from the first page. Fixture
  applies, in this order: language rule (only languages with an active
  complete profile, unless `language` is set explicitly), `q` over title,
  company and stack (case- and accent-insensitive), `today`, `seniority[]`,
  `remote`, `stack[]`, then slices by cursor. `empty` variant returns
  `{ data: [], meta: { nextCursor: null, total: 0 }, summary: { total: 0, collectedToday: 0, lockedByLanguage: [] } }`.
- `data/hooks/use-job.ts` — `useJob(id: number | null)` → `JobDetail`,
  `enabled: id !== null`, on `keys.jobs.detail(id)`.

**Steps.** 1. Write the four files. 2. Verify pause/resume optimism and that
loading a second jobs page appends. 3. Run the gate.

**Done when.**

- `useJobs` paginates by cursor and never fetches on an interval.
- Pause/resume write `keys.sending()` optimistically and roll back on error.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** UI, selection rules (Phase 15).

### Phase 8 — Hooks III: applications, drafts, queueing mutations

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (D1-B) plus the owner's manual `page-header.tsx` edit; evaluated: list 20/page, total 453, 23 pages of unique ids, in_progress 12 / attention 3 / sent 438, counts {453,12,438,3}; queue moves a job out of `matches()`, stagger gaps 53–110 s, same-company rejection verified; drafts skip `es`, no address/URL, keep `{{ job_url }}`; reviewed validation 422 on subject/body; `code-reviewer` APPROVED. Scope note: added `endpoints.applicationCounts()` (GET `/internal/applications/counts`) — not in spec B.3, needed by the counts hook's real path; the backend spec must provide it or the tab counts must derive from another route.
Role: inertia-frontend · Depends on: 5 · Covers: AC01, AC05 · Size: M
Spec: B.3, B.6 S3, B.6 S4

**Goal.** Reading the application list and detail, and the three mutations
that create applications.

**Contract.**

- `data/hooks/use-applications.ts` — `useApplications(filters: ApplicationFilters)`,
  `useInfiniteQuery` on `keys.applications.list(filters)` returning
  `Paginated<ApplicationItem>`, page size 20. Fixture maps
  `status: 'in_progress'` → `queued` + `sending`, `'attention'` → `failed` +
  `ambiguous`, `'sent'` → `sent`, `'all'` → everything; then `language`, then
  `q` over company and title. Also exports `useApplicationCounts()` reading
  the same store for the four tab counts (one query key
  `[...keys.applications.all(), 'counts']`).
- `data/hooks/use-application.ts` — `useApplication(id: number | null)` →
  `ApplicationDetail` on `keys.applications.detail(id)`, `enabled` when set.
- `data/hooks/use-queue-applications.ts` — `useQueueApplications()`:
  `useMutation<QueueResult, ApiError, { jobIds: number[] }>`. Fixture calls
  `fixtureState.queue(jobIds, 'manual')` and starts the engine. `onSuccess`
  invalidates `keys.jobs.all()`, `keys.applications.all()`,
  `keys.dashboard(...)` (all periods), `keys.chart(...)`, `keys.sending()`
  and `keys.account.status()`.
- `data/hooks/use-review-drafts.ts` — `useReviewDrafts()`:
  `useMutation<ReviewDraft[], ApiError, { jobIds: number[] }>`; fixture
  renders each job's language template into `subject`/`body` keeping
  `{{ job_url }}` as the token, `cvFileName` from the profile,
  `recipientLabel` = `"<Company> careers team"` (localised through the
  caller, **never an address**).
- `data/hooks/use-queue-reviewed.ts` — `useQueueReviewed()`:
  `useMutation<QueueResult, ApiError, { jobId: number; subject: string; body: string }>`,
  same invalidations as `useQueueApplications`.

**Steps.** 1. Write the five files with one shared `invalidateAfterQueue(qc)`
helper. 2. Check the tab counts and the three rejection reasons. 3. Run the
gate.

**Done when.**

- Queueing through the hook moves jobs out of `matches()` and creates
  `queued` applications with staggered `scheduledFor`.
- `recipientLabel` and every draft body are free of addresses and URLs.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** Modals and selection UI.

### Phase 9 — Hooks IV: preferences, preview, profiles, CV, template preview

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (D1-B) plus the owner's manual `page-header.tsx` edit; handlers re-evaluated: creating `es` keeps `activeLanguages` [en,pt] until a valid PDF is stored, then [en,pt,es] and `summary.lockedByLanguage` empties (9 es jobs listed), deleting it restores the lock; CV rules (PDF only, ≤ 5 MB) and profile validation (200/5000, unknown variable, empty subject → incomplete, duplicate create → 409) verified; template preview keeps `{{ job_url }}` and treats `$&`/`$1` literally; `FormData` now passes through `apiFetch` unchanged for JSON callers; `code-reviewer` APPROVED. Matcher corrected per spec S6 (unknown seniority always included, empty fields unconstrained, exclude words also match stack, blank needles ignored): matches unchanged (11 in pool / 9 surviving).
Role: inertia-frontend · Depends on: 5 · Covers: AC01, AC09, AC10 · Size: M
Spec: B.3, B.6 S5, B.6 S6

**Goal.** Everything the Preferences and Profiles screens need, including the
file-upload mutation shape.

**Contract.**

- `data/hooks/use-preferences.ts` — `usePreferences(initial?)` → `Preferences`
  on `keys.preferences.current()`, and `useSavePreferences()`
  (`useMutation<Preferences, ApiError, Preferences>`, `onSuccess` writes the
  result into the cache and invalidates `keys.jobs.all()`,
  `keys.dashboard(...)` and `keys.preferences.preview(...)`).
- `data/hooks/use-preferences-preview.ts` — `usePreferencesPreview(draft: Preferences)`
  → `PreferencesPreview`, `placeholderData: keepPreviousData` so the previous
  count stays visible while the next one loads (spec S6). Debouncing is the
  screen's job (400 ms), the hook only keys on the draft.
- `data/hooks/use-profiles.ts` — `useProfiles(initial?)` → `ProfilesData` on
  `keys.profiles()`; `unlockCounts` counts pool jobs per language ignoring the
  language rule. Mutations in the same file, all invalidating
  `keys.profiles()`, `keys.account.status()` and `keys.jobs.all()`:
  `useCreateProfile()` (`{ language }` → `ApplicationProfile`, seeded from
  `defaultTemplates[language]`), `useSaveProfile()`
  (`{ language, subject, body, coverLetter, active }`), `useDeleteProfile()`
  (`{ language }` → `{}`).
- `data/hooks/use-cv.ts` — `useUploadCv()` takes `{ language, file: File }`;
  the `real` function posts `FormData` with the field name `cv` through
  `fetch` (not `apiFetch`, which is JSON-only) keeping the same
  `X-XSRF-TOKEN`/`X-Requested-With` headers and `ApiError` mapping; the
  `fixture` function validates client-side rules again (PDF only, ≤ 5 MB),
  waits one `FIXTURE_LATENCY_MS` and stores
  `{ fileName: file.name, sizeBytes: file.size, uploadedAt: now }`.
  `useDeleteCv()` takes `{ language }`.
- `data/hooks/use-template-preview.ts` — `useTemplatePreview({ language, subject, body, coverLetter })`
  → `TemplatePreview`; fixture renders the 6 variables against a sample job
  from the catalog, replaces `{{ cover_letter }}` with the cover letter and
  **keeps `{{ job_url }}` as the token** so the screen can show the chip.
  Debouncing (500 ms) is the screen's job.

**Steps.** 1. Write the five files. 2. Confirm the multipart path compiles and
that the fixture path enforces the two CV rules. 3. Run the gate.

**Done when.**

- Creating a profile for `es` makes `accountStatus().profiles.activeLanguages`
  include `es` once it is complete, and removes the `lockedByLanguage` entry
  from the next `useJobs` read.
- `useTemplatePreview` output still contains the literal `{{ job_url }}`.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** UI.

### Phase 10 — Hooks V: plans, notifications, account, onboarding basics

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on the known baseline entry (D1-B) plus `page-header.tsx` (owner's manual edit, now committed with double quotes); all 31 B.3 hooks exist (`useInviteCheck` added), 23 hook files carry a `fromSource` pair (the only file without one is the foundation's `use-fixture-plan.ts`); no `/internal` literal outside `data/endpoints.ts`, no `refetchInterval`/`refetchOnMount`; lang key sets still identical (401); handlers evaluated: plans eu 0/1900/3900 EUR and br 0/4900/9900 BRL with `current` following the DevToolbar, 8 notifications / 3 unread → 0 after mark-all-read, account save validation 422 per field and region follows country, change-password 422 on wrong current password, derived onboarding steps complete after basics + preferences, invite `ok…` valid; `code-reviewer` APPROVED. Decision: onboarding steps are DERIVED from fixture state (basics/preferences flags, Gmail switch, real profiles) instead of the plan's fixed 'basics done, rest open'.
Role: inertia-frontend · Depends on: 5 · Covers: AC01, AC03 · Size: M
Spec: B.3, B.6 S7, S8, S9, S10, S11

**Goal.** The remaining hooks: plans, the notification list, account
settings, onboarding basics, and the fixture invite check.

**Contract.**

- `data/hooks/use-plans.ts` — `usePlans(region?: RegionKey)` → `PlansData` on
  `keys.plans(region)`; fixture returns `PLAN_OFFERS` for the region (default
  from `accountStatus().region`), `current` from the DevToolbar plan,
  `billingAvailable: false`.
- `data/hooks/use-notifications.ts` — `useNotifications()` →
  `NotificationItem[]` on `keys.notifications()`, and `useMarkAllRead()`
  setting `readAt` on every row, invalidating `keys.notifications()` and
  `keys.account.status()` (unread badge). `empty` variant returns `[]`.
- `data/hooks/use-account.ts` — `useAccount(initial?)` → `Account` on
  `keys.account.self()`; `useSaveAccount()`
  (`{ name, locale, timezone, country }` → `Account`, `onSuccess` also
  invalidates `keys.account.status()`); `useChangePassword()`
  (`{ currentPassword, password, passwordConfirmation }` → `{}`; the fixture
  rejects with an `ApiError(422, …, { current_password: [...] })` when the
  current password is not `"password"`, so the form's error path is
  demonstrable); `useDeleteAccount()` (`{ password }` → `{}`; fixture only
  resolves, no navigation).
- `data/hooks/use-onboarding.ts` — `useSaveOnboardingBasics()`
  (`OnboardingBasics` → `AccountStatus`); the fixture stores country/locale/
  timezone, marks the `basics` step done and returns the fresh status.
- `data/hooks/use-invite-check.ts` — `useInviteCheck(invite: string | null)`
  → `InviteCheck` (`enabled` when the value is a non-empty string); the
  fixture returns `{ valid: true, email: 'tester@example.test' }` when the
  value starts with `ok` and `{ valid: false }` otherwise (spec B.5). This
  hook has no endpoint in B.3, so `real` throws
  `new ApiError(501, 'Invites are not implemented yet.')` — it is only
  reachable in fixtures mode and the register page handles the error state.

**Steps.** 1. Write the five files. 2. Verify the 422 shape from
`useChangePassword` matches what `Field`'s `error` prop expects. 3. Run the
gate.

**Done when.**

- Every B.3 hook now exists; `grep -c "fromSource" resources/js/data/hooks/*`
  shows a pair in each file.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** UI.

### Phase 11 — Realtime cache wiring

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `yarn run check` fails only on baseline files (`docs/features/client-app-foundation/plan.md`, `resources/js/components/patterns/page-header.tsx`, both untouched by this phase; D1-B); `useRealtimeCache()` mounted once in `AppLayout`, "Simulate send" path traced to `setQueryData` on `sending` / `account.status` with no fetch or interval; `code-reviewer` APPROVED (not clicked in a browser).
Role: inertia-frontend · Depends on: 6, 7, 8 · Covers: AC05 · Size: S
Spec: B.7

**Goal.** Map the four user-channel events and the public jobs event onto the
query cache exactly as the B.7 table says, mounted once for the whole app.

**Contract.**

- `data/realtime/handlers.ts` — pure functions taking `(payload, qc)`:
    - `application.progressed`: upsert by `id` in every cached
      `keys.applications.list(*)` page, in `keys.dashboard(*)`'s `activity`
      (prepend when the id is new, cap at 5), and in `keys.sending()`'s
      `current`; when the new status is `sent`, `failed` or `ambiguous`,
      invalidate `keys.dashboard(*)`, `keys.chart(*)` and
      `keys.account.status()`.
    - `sending.updated`: `qc.setQueryData(keys.sending(), payload.sending)`.
    - `account.updated`: `qc.setQueryData(keys.account.status(), payload.status)`.
    - `notification.created`: prepend to `keys.notifications()` and bump
      `unreadNotifications` in the cached `AccountStatus` (the toasts are added
      in Phase 24).
    - `jobs.collected`: **debounced 2 s**, then invalidate `keys.jobs.all()`,
      `keys.dashboard(*)` and `keys.preferences.preview(*)`.
- `data/realtime/use-realtime-cache.ts` — `useRealtimeCache()` calls
  `useUserChannel<UserChannelEvents>({...})` with those handlers, and in
  fixtures mode also receives `jobs.collected` from the same `devEmitter`
  (real mode: the public `jobs` channel is wired by the backend spec; here
  the handler is registered through the same map and the comment says so).
- `layouts/app-layout.tsx` — calls `useRealtimeCache()` once, next to
  `useFlashToasts()`.

**Steps.** 1. Write `handlers.ts` with the debounce helper. 2. Write the hook
and mount it in `AppLayout`. 3. With the dashboard still on demo data, verify
through the React Query devtools that "Simulate send" updates
`keys.sending()` and `keys.account.status()`. 4. Run the gate.

**Done when.**

- Clicking "Simulate send" changes the cached `sending` and `account.status`
  entries without any network request and without any interval.
- `yarn types:check`, `yarn run check` and `yarn build` pass.

**Not in this phase.** Toasts for notifications (Phase 24).

### Phase 12 — S7 Plans page + `PlanGate` links

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `composer types:check` pass (0 errors); `yarn run check` and `composer lint:check` fail only on the known baseline (foundation `plan.md`, `page-header.tsx`, `ApplicationTemplateRenderer.php`; D1-B); lang key sets identical (426 each); both `PlanGate` links now `plans().url`; `code-reviewer` APPROVED (not viewed in a browser; the fixture never returns an empty plan list, so no empty-state UI was added).
Role: inertia-frontend · Depends on: 10 · Covers: AC02, AC03, AC04, AC13, AC14 · Size: M
Spec: B.5, B.6 S7

**Goal.** The first new screen, and the route that makes every `PlanGate`
CTA in the app point somewhere real.

**Contract.**

- `routes/web.php`: `Route::inertia('/plans', 'plans')->middleware(['auth', 'client'])->name('plans');`
  (same middleware as `/dashboard`).
- `lib/navigation.ts`: the `plans` nav item becomes a real link
  (`plans().url`, active detection as for `dashboard`).
- `pages/plans.tsx` — `AppLayout`, `PageHeader` (eyebrow
  `plans.eyebrow`, title `plans.title`, summary `plans.summary`), loading
  skeleton mirroring three cards, `ErrorState` with retry, then the three
  cards side by side (`AppGrid`, 4 columns each on desktop, stacked on
  mobile).
- `features/plans/plan-card.tsx` — name, price
  (`format.currency(price / 100, currency)`, **prices are minor units**),
  `plans.per_month`, daily limit line
  (`plans.limit` = `":count applications per day"`), mode explanation from
  `plans.mode.auto|select|review` with the exact copy: auto = "We choose and
  send for you within your preferences", select = "You choose which jobs to
  apply to", review = "You choose and edit every email before it goes";
  feature list with `Check` icons; CTA `plans.cta.current` ("Current plan",
  disabled) / `plans.cta.upgrade` ("Upgrade") / `plans.cta.switch`
  ("Switch"). `highlighted` uses the accent treatment (accent border +
  accent-soft surface, no new token).
- `features/plans/region-menu.tsx` — the line
  `plans.region` = `"Prices for :region"` plus a `Menu` ("Change") listing the
  three regions; changing it refetches through `usePlans(region)` and is
  local state only.
- `billingAvailable === false` → every enabled CTA opens a `Modal` with title
  `plans.closed_beta.title` and body `plans.closed_beta.body` = "Plans are
  assigned by us during the closed beta. Contact us to change your plan.",
  single "Got it" button. No mailto yet.
- `components/patterns/plan-gate.tsx`: replace both `href="#"` with
  `plans().url` (foundation B.10 said to do this once the route exists).
- i18n: `nav.plans` exists; add `plans.eyebrow`, `plans.title`,
  `plans.summary`, `plans.per_month`, `plans.limit`, `plans.mode.*`,
  `plans.features.*`, `plans.cta.*`, `plans.region`, `plans.region.<key>`,
  `plans.change_region`, `plans.closed_beta.*` to the three files.

**Steps.** 1. Add the route and enable the nav item. 2. Build the page, card
and region menu. 3. Point `PlanGate` at `plans()`. 4. Add EN/PT/ES keys. 5. Check 360 / 768 / 1024 / 1440 px and the four DevToolbar states. 6. Run the gate, including `composer lint:check` and `composer types:check`
(this phase touches `routes/web.php`).

**Done when.**

- `/plans` renders three cards with region-correct currency, the current plan
  disabled, and the closed-beta modal on the other CTAs.
- Any locked `PlanGate` in the app now links to `/plans`.
- Loading, empty and error states work from the DevToolbar; no horizontal
  scroll at 360 px.
- Gate passes.

**Not in this phase.** Real billing, mailto config, plan switching.

### Phase 13 — S6 Preferences page

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `composer types:check` pass; `yarn run check` / `composer lint:check` fail only on the known baseline (D1-B); lang key sets identical (478 each); `format.list` gained `disjunction` and `unit`; sentence matches the spec example in EN; 1 correction round (missing `preferences.summary` key, "and" in the sentence), then `code-reviewer` APPROVED (not viewed in a browser).
Role: inertia-frontend · Depends on: 9 · Covers: AC02, AC03, AC10, AC13, AC14 · Size: M
Spec: B.6 S6, B.8

**Goal.** The preferences editor with the live summary sentence and the
debounced match counter.

**Contract.**

- `routes/web.php`: `Route::inertia('/preferences', 'preferences')->middleware(['auth', 'client'])->name('preferences');`
  and `lib/navigation.ts` enables the item.
- `lib/format.ts`: `formatList(locale, items, type: 'conjunction' | 'disjunction' = 'conjunction')`
  and the matching `format.list(items, type?)`, for the "or" joins inside a
  field (spec B.8).
- `pages/preferences.tsx` — `PageHeader`; a permanent rule banner
  (`preferences.rule` = "Inside a field, any value can match. Between fields,
  all must match."); left column five `DataCard` sections; right column
  sticky on desktop, a bottom summary sheet on mobile.
- `features/preferences/preferences-sections.tsx` — **Roles**: `TagsInput`,
  hint `preferences.titles.help` = "e.g. Backend, Frontend, React Developer".
  **Seniority**: toggle chips for `intern|junior|mid|senior|lead` plus the
  note `preferences.seniority.note` = "Jobs where the level is unclear are
  always included". **Stack**: `TagsInput` with `STACK_SUGGESTIONS`.
  **Location and remote**: `RadioGroup` of three cards
  (`remote_only`, `remote_or_locations`, `locations_only`) and a locations
  `TagsInput` shown for the last two, **required** for `locations_only`
  (inline `Field` error `preferences.locations.required`, Save disabled).
  **Exclude**: `TagsInput`, hint "matched against job titles and stack, e.g.
  Java, WordPress, Intern".
- `features/preferences/preferences-summary.tsx` — the client-built sentence
  from `preferences.summary.*` fragments joined with `Intl.ListFormat`
  (disjunction inside a field, conjunction between fields), e.g. "Frontend or
  backend roles, senior or lead level, with React or Vue, remote or in
  Dublin, excluding WordPress."; plus the match counter from
  `usePreferencesPreview(draft)` **debounced 400 ms** keeping the previous
  value while loading, with a per-language breakdown, and the Save button
  (disabled until dirty, success toast `preferences.saved`).
- Dirty tracking compares the draft with the loaded value; `Save` calls
  `useSavePreferences`.
- i18n: `preferences.*` for every label, hint, section title, summary
  fragment, counter line and toast, in EN/PT/ES.

**Steps.** 1. Route + nav. 2. `format.list` disjunction. 3. Sections. 4.
Summary + counter + save. 5. EN/PT/ES. 6. Responsive check. 7. Gate
(+ PHP gates for `routes/web.php`).

**Done when.**

- The rule banner is always visible; typing a role updates the sentence
  immediately and the counter after ~400 ms without flicker.
- Choosing "Only these locations" with no location shows the inline error and
  keeps Save disabled.
- Saving shows the success toast and the jobs cache is invalidated.
- Gate passes.

**Not in this phase.** The compact onboarding variant (Phase 18).

### Phase 14 — S5 Profiles page

Status: DONE
Evidence: `yarn types:check` pass; `yarn build` pass; `composer types:check` pass; `yarn run check` / `composer lint:check` fail only on the known baseline (D1-B); lang key sets identical (531 each); D2-A/D3-A followed (created `es` profile seeded from the fixture `defaultTemplates.es`, no URL anywhere, `{{ job_url }}` drawn as a "job link" chip); `code-reviewer` APPROVED (not viewed in a browser).
Role: inertia-frontend · Depends on: 9 · Covers: AC02, AC03, AC08, AC09, AC13, AC14 · Size: M
Spec: B.6 S5

**Goal.** Per-language application content: CV, email template with variable
chips, cover letter, live preview, and the unsaved-changes guard.

**Contract.**

- `routes/web.php`: `Route::inertia('/profiles', 'profiles')->middleware(['auth', 'client'])->name('profiles');`
  and `lib/navigation.ts` enables the item.
- `pages/profiles.tsx` — `PageHeader` title `profiles.title` =
  "Application profiles", summary `profiles.summary` = "A job is only sent
  with a profile in its language. You have :count active languages.", action
  a `Menu` "Add language" listing only the languages with no profile
  (`useCreateProfile`). `Tabs` per created language with a status dot
  (`StatusDisc`: complete / incomplete / inactive). Empty state (no
  profiles): `profiles.empty.title` = "Create your first application
  profile" with the three language options and
  `profiles.empty.unlock` = ":count jobs in :language" from `unlockCounts`.
- `features/profiles/profile-form.tsx` — left column: CV block, Email block
  (Subject `Input`, Body `Textarea` + variable bar), Cover letter `Textarea`
  with the help text `profiles.cover_letter.help` (explains it is inserted
  where `{{ cover_letter }}` appears), Active `Switch`, Save, Delete profile
  (danger + confirm `Modal`). Dirty state drives Save; switching tab or
  navigating while dirty opens the unsaved-changes guard
  (`profiles.unsaved.*`, Inertia `router.on('before')` + tab interception).
- `features/profiles/cv-card.tsx` — `FileDrop` (`accept: 'application/pdf'`,
  `maxSizeMb: 5`), showing name, size (`format.number`) and upload date when
  present, Replace, and Remove behind a confirm. Client-side validation
  errors reuse `forms.file.wrong_type` / `forms.file.too_large`.
- `features/profiles/variable-bar.tsx` — six chips inserting
  `{{ company }}`, `{{ job_title }}`, `{{ job_location }}`, `{{ job_url }}`,
  `{{ client_name }}`, `{{ cover_letter }}` **at the cursor** of the focused
  field (subject or body), keeping the caret after the inserted token.
- `features/profiles/preview-card.tsx` — right column, sticky on desktop:
  live preview from `useTemplatePreview` **debounced 500 ms**, rendering the
  subject and body with `{{ job_url }}` drawn as a non-editable "job link"
  `Chip` (`profiles.job_link` = "job link"); "Jobs unlocked" stat
  `profiles.unlocked` = ":count jobs in :language match your preferences";
  and a "Missing" warning list when `complete` is false, from
  `profiles.missing.cv|subject|body`.
- i18n: all `profiles.*` in EN/PT/ES.

**Steps.** 1. Route + nav. 2. Page shell, tabs, empty state. 3. Form, CV
card, variable bar. 4. Preview card + dirty guard. 5. EN/PT/ES. 6. Responsive
(two columns → stacked). 7. Gate (+ PHP gates).

**Done when.**

- Creating `es` adds a tab seeded from `defaultTemplates.es`; deleting a
  profile asks first.
- A non-PDF or a 6 MB file is refused client-side with the right message.
- Clicking a variable chip inserts the token at the caret; the preview
  updates ~500 ms later and shows the "job link" chip, never a URL.
- Leaving a dirty tab prompts.
- Gate passes.

**Not in this phase.** The onboarding profile step (Phase 18).

### Phase 15 — S2 Jobs page: filters, detail sheet, selection, confirm modal

Status: PENDING
Role: inertia-frontend · Depends on: 7, 8, 12, 13, 14 · Covers: AC02, AC03, AC04, AC05, AC07, AC08, AC09, AC13, AC14 · Size: M
Spec: B.6 S2, B.6 S3 (confirm modal)

**Goal.** The jobs browser with URL-reflected filters, the no-link-out detail
sheet, and the select-mode send flow end to end.

**Contract.**

- `routes/web.php`: `Route::inertia('/jobs', 'jobs')->middleware(['auth', 'client'])->name('jobs');`
  and `lib/navigation.ts` enables the item.
- `lib/use-query-filters.ts` — reads `JobFilters` from
  `window.location.search` and writes them back with
  `router.get(url, {}, { preserveState: true, preserveScroll: true, replace: false })`
  so **back / forward restore the filter set** (AC02). Defaults are omitted
  from the query string.
- `pages/jobs.tsx` — `PageHeader` title `jobs.title`, summary
  `jobs.summary` = ":total jobs match your preferences · :today collected
  today", action `jobs.edit_preferences` = "Edit preferences" (secondary,
  → `preferences()`). Below: the filter bar, the locked-by-language notice,
  the list, the sticky bar. Loading skeleton mirrors the row list; empty and
  error states through `DataCard` / `ErrorState`.
- `features/jobs/job-filters.tsx` — one `FilterBar` row (wraps on mobile):
  search `Input`, "Collected today" toggle, Language `Select` (only
  `activeLanguages` + "All"), Seniority `MultiSelect`, Remote `Select`
  (`any|remote|not_remote`), Stack `TagsInput`.
- Locked-by-language notice: shown when `summary.lockedByLanguage` is not
  empty — `jobs.locked` = ":count :language jobs are hidden because you have
  no :language application profile." with a "Create :language profile" button
  → `profiles()`.
- List of `JobRow`s; **"Load more" button only**, never scroll-triggered
  loading. Clicking the row body opens `features/jobs/job-detail-sheet.tsx`
  (`Sheet side="right"`, full-screen on mobile): company, title, location and
  remote, seniority, language, stack, summary, employment type, department,
  published date, `jobs.detail.source` = "Source: :label" as **plain text**.
  **No link out, no URL, ever.** Its action is the same select control as the
  row, or the plan gate in auto mode.
- `features/send/use-selection.ts` — selection state shared with the
  dashboard later: `toggle(job)`, `clear()`, `selectAllOnPage(jobs)`,
  `selected: JobCard[]`, `overQuota: boolean`. Rules: **one job per
  company** — selecting a second job of an already-selected company replaces
  the first and fires `toast.info(t('send.one_per_company'))` = "Only one
  application per company"; selecting more than `quota.remaining` sets
  `overQuota`.
- Sticky bar: `StickyActionBar` with
  `send.selected` = ":count selected · :left sends left today", "Clear", and
  the primary button `send.send` = "Send :count applications" (select mode).
  When `overQuota`, the bar shows the inline error
  `send.over_quota` = "That is more than the :count sends you have left
  today." and the button is disabled.
- `features/send/confirm-send-modal.tsx` — `Modal` (full-screen on mobile),
  title `send.confirm.title` = "Send :count applications?", the list of
  companies, the line `send.confirm.body` = "They will go out one by one from
  :gmail, about every 45–120 seconds. Estimated finish :time.", buttons
  Cancel / Send. On success: `toast.success(send.confirm.queued)` =
  ":count applications queued", selection cleared; any
  `QueueResult.rejected` rows are appended to the toast message as
  "<Company>: <reason>".
- Auto mode (plan `free`): no checkboxes anywhere, the list is read-only, the
  sticky-bar area shows a `PlanGate` covering only the selection features
  (`jobs.gate` = "Choose your own jobs on Starter and Pro",
  `requiredPlans: ['starter','pro']`), and a status banner
  `jobs.auto_banner` = "Sending automatically · :left left today · next at
  :time" with Pause / Resume through `usePauseSending` / `useResumeSending`.
- i18n: `jobs.*` and `send.*` in EN/PT/ES.

**Steps.** 1. Route + nav + URL filter helper. 2. Filters + list + load more. 3. Detail sheet. 4. Selection hook + sticky bar + confirm modal. 5. Auto-mode
gate and banner. 6. EN/PT/ES. 7. Responsive + the three DevToolbar plans. 8. Gate (+ PHP gates).

**Done when.**

- Filters change the URL, are restored on reload, and back / forward work.
- Selecting a second job of the same company replaces it and shows the toast;
  going over the remaining quota blocks the send with the inline message.
- Confirming queues the applications, clears the selection, and the queued
  jobs disappear from the list.
- Free plan: no checkboxes, the gate covers the selection area, the banner
  pauses and resumes.
- No URL or address anywhere on the page or in the sheet.
- Gate passes.

**Not in this phase.** The review modal (Phase 16).

### Phase 16 — S3 Review modal (Pro review mode)

Status: PENDING
Role: inertia-frontend · Depends on: 8, 15 · Covers: AC03, AC04, AC05, AC08, AC13, AC14 · Size: M
Spec: B.6 S3 (review modal)

**Goal.** The Pro review flow: edit every email before it is queued.

**Contract.**

- `features/review/review-modal.tsx` — large `Modal`, full-screen on mobile,
  opened from the Jobs sticky bar when the mode is `review` (the button label
  becomes `send.review` = "Review :count applications"). Loads
  `useReviewDrafts({ jobIds })`; header `review.progress` = "Review :index of
  :total" plus progress dots. Left: the job summary card (company, title,
  language tag, seniority, stack). Right: editable Subject (`Input`) and Body
  (`Textarea`), the CV `Chip` with `cvFileName`, the line
  `review.to` = "To: :recipient" (**label only, never an address**), and the
  language profile tag. Footer: `review.skip` = "Skip",
  `review.previous` = "Previous", `review.approve` = "Approve and queue" →
  `useQueueReviewed` then the next draft. Validation: subject 1–200
  characters, body 1–5000 characters, inline `Field` errors
  `review.subject.length` / `review.body.length`, Approve disabled while
  invalid. When the last draft is done: the summary
  `review.summary` = "Queued :queued, skipped :skipped" and Close.
- `features/review/body-editor.tsx` — the body editor that renders the
  `{{ job_url }}` token as a **non-editable** "job link" chip: the textarea
  holds the token, an overlay marks it, and the token cannot be partially
  deleted (removing it removes the whole token). The rest of the body stays
  freely editable.
- `pages/jobs.tsx` — wires the review path: mode `review` opens this modal
  instead of the confirm modal.
- i18n: `review.*` in EN/PT/ES.

**Steps.** 1. Body editor with the protected token. 2. Modal shell,
navigation between drafts, validation. 3. Wire it into the Jobs sticky bar. 4. EN/PT/ES. 5. Mobile full-screen check. 6. Gate.

**Done when.**

- On Pro, selecting jobs and pressing "Review n applications" walks the
  drafts, approving queues each one and the final summary reports the counts.
- A 0-character subject or a 5001-character body blocks Approve with the
  inline message.
- The body shows the "job link" chip and no URL; the token survives editing
  around it.
- Gate passes.

**Not in this phase.** The dashboard entry point (Phase 21).

### Phase 17 — S4 Applications page + detail sheet

Status: PENDING
Role: inertia-frontend · Depends on: 8, 11 · Covers: AC02, AC03, AC06, AC08, AC13, AC14 · Size: M
Spec: B.6 S4

**Goal.** The full application history with live-updating rows and the
read-only snapshot sheet.

**Contract.**

- `routes/web.php`: `Route::inertia('/applications', 'applications')->middleware(['auth', 'client'])->name('applications');`
  and `lib/navigation.ts` enables the item.
- `pages/applications.tsx` — `PageHeader` title `applications.title`;
  `Tabs` All / In progress / Sent / Needs attention with counts from
  `useApplicationCounts()`; a language `Select` and a search `Input` in a
  `FilterBar`; "Load more" button pagination.
- `features/applications/applications-table.tsx` — desktop table (company,
  role, language tag, status badge with icon + label, the stage for
  in-progress rows, queued / sent time); mobile renders stacked
  `ActivityRow` cards from the same rows. Feature-local composition, not a
  new primitive.
- `features/applications/status-badge.tsx` — `Pill` + icon per
  `ApplicationStatus`: `queued` (Clock), `sending` (`Spinner`), `sent`
  (Check, success), `failed` (CircleAlert, danger), `ambiguous`
  (CircleHelp, warning) with labels
  `applications.status.queued|sending|sent|failed|ambiguous` ("Queued",
  "Sending", "Sent", "Not delivered", "Needs review").
- `features/applications/application-detail-sheet.tsx` — `Sheet`
  (right / full-screen mobile) from `useApplication(id)`: status, the stage
  timeline with times, the subject and body snapshot read-only with the "job
  link" chip, the CV file name, the failure reason, and the explanation —
  for `ambiguous`: `applications.ambiguous.explain` = "We could not confirm
  delivery, so we will not send it again to avoid a duplicate"; for `failed`:
  `applications.failed.explain` = "This one will not be retried
  automatically."
- Rows update live: Phase 11's `application.progressed` handler already
  upserts into `keys.applications.list(*)`, so no extra wiring — verify it.
- i18n: `applications.*` in EN/PT/ES.

**Steps.** 1. Route + nav. 2. Tabs, filters, table, mobile cards. 3. Status
badge. 4. Detail sheet. 5. EN/PT/ES. 6. Responsive. 7. Gate (+ PHP gates).

**Done when.**

- The four tabs show the right counts and rows; the language filter and
  search narrow them.
- "Simulate send" moves a row through `sending` → `sent` **without a
  reload**; "Simulate failure" makes it appear under "Needs attention" with
  its reason.
- The sheet shows the timeline and the body with the chip, no URL, no
  address.
- Gate passes.

**Not in this phase.** Nothing from the dashboard.

### Phase 18 — S9 Onboarding, 4 steps

Status: PENDING
Role: inertia-frontend · Depends on: 2, 9, 10, 13, 14 · Covers: AC02, AC03, AC11, AC13, AC14 · Size: M
Spec: B.6 S9

**Goal.** The 4-step setup wizard on the app shell without navigation, so the
dashboard's setup card has a destination.

**Contract.**

- `routes/web.php`: `Route::inertia('/onboarding', 'onboarding')->middleware(['auth', 'client'])->name('onboarding');`
- `layouts/app-layout.tsx`: new optional prop `nav?: boolean` (default
  `true`); when `false`, `TopBar` receives an empty `nav` array, so only the
  logo, the bell and the user menu remain (spec S9). No other change.
- `pages/onboarding.tsx` — `AppLayout nav={false}`, `PageHeader`, the
  `Stepper` from Phase 2 (steps `basics`, `gmail`, `profile`, `preferences`,
  `done` flags from `AccountStatus.onboarding.steps`, done steps clickable),
  one `DataCard` per step with Back / Continue. Finish → `router.visit(dashboard())`
  plus `toast.success(onboarding.done)`.
- `features/onboarding/step-basics.tsx` — country `Select`
  (`Intl.DisplayNames`), interface language `Segmented`, timezone `Select`
  prefilled from `Intl.DateTimeFormat().resolvedOptions().timeZone`;
  Continue calls `useSaveOnboardingBasics`.
- `features/onboarding/step-gmail.tsx` — the explanation
  `onboarding.gmail.body` = "Applications leave from your own Gmail address."
  and a "Connect Gmail" button pointing at the **existing** route
  `integrations.oauth.connect('gmail')` with a `?redirect=/onboarding`
  intent; in fixtures mode the button flips `DevState.gmail` to `connected`
  instead of navigating (spec B.3). Shows the connected address when done.
- `features/onboarding/step-profile.tsx` — language choice, CV upload and
  subject / body / cover letter prefilled from `defaultTemplates[language]`,
  with the live preview — **reusing** `features/profiles/cv-card.tsx`,
  `variable-bar.tsx` and `preview-card.tsx`, not copies of them.
- `features/onboarding/step-preferences.tsx` — the compact S6: roles,
  seniority, remote mode, locations plus the live counter, reusing
  `features/preferences/*`.
- i18n: `onboarding.*` in EN/PT/ES.

**Steps.** 1. Route + `AppLayout nav` prop. 2. Page + stepper wiring. 3. The
four steps, reusing the profile and preference components. 4. EN/PT/ES. 5. Responsive. 6. Gate (+ PHP gates).

**Done when.**

- `/onboarding` walks the 4 steps, Back / Continue work, a done step can be
  revisited, and Finish lands on the dashboard with the success toast.
- With `onboardingComplete` off in the DevToolbar the steps show as open;
  with it on they show as done.
- The shell shows no nav items, only logo, bell and user menu.
- Gate passes.

**Not in this phase.** The dashboard setup card (Phase 21).

### Phase 19 — S1 Dashboard part 1: header, hero, KPI tiles, chart

Status: PENDING
Role: inertia-frontend · Depends on: 6, 11, 15 · Covers: AC02, AC03, AC05, AC13, AC14 · Size: M
Spec: B.6 S1 (header, row 1, chart card)

**Goal.** Replace the top half of the approved demo dashboard with live data,
leaving the other three cards on their current demo props until phases 20–21.
The screen keeps working at every step.

**Contract.**

- `pages/dashboard.tsx` — drop the `DEMO` entries this phase covers and read
  `useAccountStatus()`, `useDashboard(period)`, `useChart(range)`. Keep the
  existing composition, `DashboardGrid` and the visual result identical to
  the approved mockup.
    - Header eyebrow: `<weekday, date> · <mode label>` where the mode label is
      `dashboard.mode.auto|select|review` ("Auto mode" / "Select mode" /
      "Review mode") — the current single `dashboard.mode` key is replaced by
      the three.
    - Title: `dashboard.greeting.morning|afternoon|evening` chosen from the
      user's local hour (< 12, < 18, else), with the first name.
    - Summary: `dashboard.summary` through the existing `RichText`, values from
      `hero.sentToday` and `matches.total`.
    - Actions: the `Segmented` Today / Week / Month now drives `period`;
      "Browse jobs" links to `jobs()`.
- Hero (`HeroCard`, span 5): `value = hero.sentToday`,
  `suffix = "/ <quota.limit>"`, bars from `hero.lastDays` counts plus today
  (today solid), bar labels the day numbers plus `dashboard.hero.now`,
  caption `dashboard.hero.caption` with `quota.remaining` and the plan name,
  stats strip Queued (`hero.queued`) / Not delivered (`hero.failedToday`) /
  Next send in (a local countdown from `hero.nextSendAt`, `"—"` when null).
- KPI card (span 7), title from the period
  (`dashboard.period.today|week|month`), four `StatTile`s: Jobs collected
  (`kpis.collected.value`, delta vs `previous`), Sent (`kpis.sent.value`,
  delta), Total sent (`kpis.totalSent`, context
  `dashboard.stat.since` with `kpis.firstSentAt`), Daily limit
  (`quota.usedToday / quota.limit` with a `TickMeter` of **20 ticks** and
  `dashboard.stat.left`). Delta direction is `up` when the value is ≥ the
  previous one.
- `features/dashboard/chart-card.tsx` — extracted from the page: "Sends per
  day", the 14d / 30d `Segmented` driving `useChart`, the headline
  `averagePerActiveDay` + `dashboard.chart.average` ("avg per active day" —
  the current "avg per working day" copy is replaced), the `BarChart` with
  today highlighted and the **daily limit as a dashed line**, and the legend.
- `components/patterns/bar-chart.tsx` — add `limit?: number`: a dashed
  horizontal rule at the limit value using `stroke-dasharray` and an existing
  line token, included in the `niceMax` calculation, announced in the
  accessible table as an extra row. Add its styleguide example variant in
  `features/styleguide/chart-section.tsx`.
- Loading: the existing `HeroCard`/`StatTile`/`BarChart` `loading` props
  mirror the final layout. Error: `DataCard state="error"` with retry.
- i18n: replace `dashboard.mode`, add `dashboard.mode.*`,
  `dashboard.greeting.morning|evening`, `dashboard.period.*`,
  `dashboard.hero.no_next` (`"—"` context label), adjust
  `dashboard.chart.average`, `dashboard.stat.collected` and
  `dashboard.stat.sent_week` to period-neutral copy, in EN/PT/ES.

**Steps.** 1. Wire the three hooks and the header. 2. Hero and KPI tiles. 3. Extract the chart card and add the limit line + styleguide variant. 4. EN/PT/ES. 5. Compare against `client-app-foundation/reference/dashboard-mockup.png`
at 1440 px, then check 360 / 768 / 1024. 6. Gate.

**Done when.**

- Switching Today / Week / Month changes the KPI card title and all four
  tiles; switching 14d / 30d changes the chart and the average.
- The hero numbers, the remaining-quota caption and the next-send countdown
  come from the fixtures and change after "Simulate send".
- The chart shows the dashed daily-limit line and today highlighted.
- The three cards not yet converted still render exactly as before.
- Gate passes.

**Not in this phase.** Live sending, matches, activity.

### Phase 20 — S1 Dashboard part 2: live sending card

Status: PENDING
Role: inertia-frontend · Depends on: 7, 11, 19 · Covers: AC03, AC05, AC06, AC13, AC14 · Size: M
Spec: B.6 S1 (live sending), B.2 (`LiveSending`), B.8

**Goal.** The centrepiece: the dark live-sending card driven by
`LiveSending`, with all six states and the honest stage narration.

**Contract.**

- `features/dashboard/live-sending-card.tsx` — rewritten to take
  `sending: LiveSending` plus the plan/mode, replacing the string props it has
  today. Renders:
    - Current application panel: company tile, title, "company · location", the
      language tag, and `dashboard.live.position` = ":position of :limit" from
      `progress`.
    - `LiveStepper` over the five stages with labels from
      **`sending.stage.<stage>`** and the sub-step line under the active step
      from **`sending.sub.<subStep>`** — the current `dashboard.live.step.*` and
      `dashboard.live.sub_step` keys are removed in favour of these, per B.8.
      Stage keys: `validating_recipient`, `adapting_template`, `attaching_cv`,
      `sending`, `sent`, `failed`. Sub-step keys: `checking_company`,
      `confirming_recipient`, `checking_gmail`, `filling_variables`,
      `building_html`, `opening_cv`, `checking_pdf`, `attaching_file`,
      `connecting_gmail`, `delivering`.
    - `CountdownBar` from `waitStartedAt` → `nextSendAt` with
      `live.next_in` and `dashboard.live.spacing` from `spacing`.
    - "Up next · :count queued" with the estimated finish from
      `estimatedFinishAt`, then up to 3 `QueueRow`s from `queue` (ETA =
      `format.relativeTime(scheduledFor)`).
    - Header: the "Live" `Pill` with a pulsing `LiveDot` while `state` is
      `sending` or `waiting`, and a ghost Pause / Resume button on
      `usePauseSending` / `useResumeSending` (optimistic).
- The other states, each as a dark-surface empty state inside the same card:
    - `idle` → `dashboard.live.idle.title` = "Nothing in the queue" plus the CTA
      `dashboard.live.idle.choose` = "Choose jobs" (select / review, →
      `jobs()`) or the line `dashboard.live.idle.auto` = "Sending starts
      automatically when new jobs match" (auto).
    - `paused` → `dashboard.live.paused` = "Sending is paused" + Resume.
    - `limit_reached` → `dashboard.live.limit` = "Daily limit reached, sending
      resumes tomorrow at :time" + an upgrade link to `plans()`.
    - `outside_window` → `dashboard.live.window` = "Sending resumes at :time"
      from `window.start`.
- Failed send: the stepper marks the failed step red
  (`LiveStepper failedIndex`) with the reason for **6 s**, then the panel
  moves on to the next item (a local timer, no fetch).
- `pages/dashboard.tsx` — passes `useLiveSending()` data in, removes the
  matching `DEMO` entries.
- i18n: `sending.stage.*`, `sending.sub.*`, the new `dashboard.live.*` keys
  in EN/PT/ES; remove the replaced keys from all three files.

**Steps.** 1. Rewrite the card against the contract type. 2. All six states. 3. The 6 s failure display. 4. EN/PT/ES (add and remove). 5. Visual check
against the mockup, then mobile. 6. Gate.

**Done when.**

- "Simulate send" runs the panel through the five stages with the sub-step
  line changing every 1.2 s, then the countdown to the next one.
- Pause switches to the paused state and stops the engine; Resume continues.
- "Simulate failure" shows the red step with "Your CV file could not be
  read." for ~6 s, then moves on.
- Setting the plan to Free (limit 25) and exhausting the quota shows
  `limit_reached` with the upgrade link.
- No `dashboard.live.step.*` key remains in any lang file.
- Gate passes.

**Not in this phase.** Matches, activity, setup card.

### Phase 21 — S1 Dashboard part 3: matches, activity, setup card, Gmail banner

Status: PENDING
Role: inertia-frontend · Depends on: 15, 16, 17, 18, 20 · Covers: AC02, AC03, AC04, AC05, AC07, AC11, AC13, AC14 · Size: M
Spec: B.6 S1 (row 3, onboarding state, Gmail banner)

**Goal.** Finish the dashboard: the mode-aware matches card, live activity,
and the two whole-page states (setup incomplete, Gmail expired). After this
phase `DEMO` is gone.

**Contract.**

- `features/dashboard/new-matches-card.tsx` — rewritten on
  `DashboardData.matches` and the mode: subtitle
  `dashboard.matches.subtitle` with `total`, up to 4 `JobRow`s, header arrow →
  `jobs()`.
    - `select`: checkboxes through `features/send/use-selection.ts` (so the
      one-per-company and quota rules are the same as on Jobs), footer
      ":count selected · :left sends left today", Clear, primary
      "Send :count applications" → the Phase 15 confirm modal.
    - `review`: the same selection, primary "Review :count applications" → the
      Phase 16 review modal.
    - `auto`: rows without checkboxes, the whole card wrapped in
      `PlanGate(locked, requiredPlans: ['starter','pro'], featureKey: 'dashboard.matches.gate_benefit')`.
- `features/dashboard/activity-card.tsx` — extracted and rewritten on
  `DashboardData.activity`: one `ActivityRow` per item — `sending` with the
  spinner, `sent`, `failed` with its reason, and `ambiguous` as "Needs
  review" — the "Live" `Pill`, and the footer button "View all applications"
  → `applications()`.
- `features/dashboard/setup-card.tsx` — when
  `AccountStatus.onboarding.complete` is `false`, **row 1 is replaced** by a
  full-width accent hero card: title `dashboard.setup.title`, the four steps
  (`basics`, `gmail`, `profile`, `preferences`) as a checklist from
  `onboarding.steps`, and "Continue setup" → `onboarding()`. In that state the
  live-sending and new-matches cards are wrapped in the Phase 2
  `LockOverlay` with `dashboard.setup.locked` = "Finish setup to start
  sending".
- `features/dashboard/gmail-banner.tsx` — when
  `gmail.state === 'reauthorization_required'`, a full-width warning banner
  **above row 1**: `dashboard.gmail.expired` = "Your Gmail connection expired.
  Sending is paused until you reconnect." plus "Reconnect Gmail"
  (`integrations.oauth.reconnect('gmail')`; in fixtures mode it flips the
  DevToolbar Gmail state).
- `pages/dashboard.tsx` — composes the three, drops the last `DEMO` entries
  and the now-unused local demo helpers.
- i18n: the new `dashboard.setup.*`, `dashboard.gmail.*`,
  `dashboard.activity.*` keys in EN/PT/ES.

**Steps.** 1. Matches card on the shared selection hook + both modals. 2. Activity card. 3. Setup card + lock overlays. 4. Gmail banner. 5. Remove
`DEMO`. 6. EN/PT/ES. 7. Visual check against the mockup, then 360 / 768 / 1024. 8. Gate.

**Done when.**

- `grep -n "DEMO" resources/js/pages/dashboard.tsx` returns nothing.
- Starter: selecting matches and sending queues them, they leave the matches
  card and appear in the live queue and in Recent activity without a reload.
- Pro: the same selection opens the review modal.
- Free: the matches card is gated and the live panel sends automatically.
- Turning `onboardingComplete` off replaces row 1 with the setup card and
  locks the live and matches cards; the Gmail "needs reconnection" state shows
  the banner.
- Gate passes.

**Not in this phase.** Notifications and the palette.

### Phase 22 — S8 Account page

Status: PENDING
Role: inertia-frontend · Depends on: 10 · Covers: AC02, AC03, AC13, AC14 · Size: M
Spec: B.6 S8, B.3 (export link)

**Goal.** Account settings, including the Gmail card and the two destructive
flows behind confirmation.

**Contract.**

- `routes/web.php`: `Route::inertia('/account', 'account')->middleware(['auth', 'client'])->name('account');`
- `components/patterns/user-menu.tsx` — an "Account" entry above the language
  entries, linking to `account()` (`user_menu.account`).
- `pages/account.tsx` — `PageHeader` + six `DataCard`s in a two-column grid:
    - **Profile** (`features/account/profile-card.tsx`): name editable, email
      read-only, Save through `useSaveAccount`.
    - **Language & region**: interface language `Segmented`, country `Select`
      built with `Intl.DisplayNames`, timezone `Select` defaulting to the
      browser's zone; saving the language also goes through `useSetLocale` so
      the UI switches immediately.
    - **Gmail** (`features/account/gmail-card.tsx`): status, the connected
      address, the explanation `account.gmail.body` = "Applications leave from
      your own Gmail address.", and Connect / Reconnect / Disconnect
      (the existing OAuth routes; Disconnect behind a confirm `Modal`; in
      fixtures mode all three flip the DevToolbar Gmail state).
    - **Password** (`features/account/password-card.tsx`): current, new,
      confirm; `useChangePassword`; server 422 errors mapped onto the fields.
    - **Data**: "Download my data" as a plain `<a>` to
      `endpoints.accountExport().url` with `download`.
    - **Danger zone** (`features/account/danger-zone.tsx`): delete account
      behind a confirm `Modal` requiring the password, destructive styling,
      `account.delete.warning` explaining it cannot be undone.
- i18n: `account.*` and `user_menu.account` in EN/PT/ES.

**Steps.** 1. Route + user-menu entry. 2. Profile, language & region cards. 3. Gmail card. 4. Password card with the 422 path. 5. Data + danger zone. 6. EN/PT/ES. 7. Responsive. 8. Gate (+ PHP gates).

**Done when.**

- `/account` is reachable from the user menu and every card saves (or shows
  its error) on fixtures.
- Entering a wrong current password shows the field error from the simulated 422.
- Disconnect and Delete both ask first; the export is a plain link.
- Gate passes.

**Not in this phase.** Real password change, real export, real deletion.

### Phase 23 — S10 Auth pages: register, closed, forgot, reset

Status: PENDING
Role: inertia-frontend · Depends on: 10 · Covers: AC02, AC03, AC12, AC13, AC14 · Size: M
Spec: B.5 (guest routes), B.6 S10

**Goal.** The four guest pages on `GuestLayout`, with the fixture invite rule.

**Contract.**

- `routes/web.php`, inside the existing `Route::middleware('guest')` group:
    - `Route::inertia('/register', 'auth/register')->name('register');`
    - `Route::inertia('/register/closed', 'auth/closed')->name('register.closed');`
    - `Route::inertia('/forgot-password', 'auth/forgot-password')->name('password.request');`
    - `Route::inertia('/reset-password/{token}', 'auth/reset-password')->name('password.reset');`
- `pages/auth/register.tsx` — reads `?invite=` from the URL, checks it with
  `useInviteCheck`; **any value starting with `ok` is valid**, anything else
  (or a missing value) redirects to `register.closed` (spec B.5). Fields:
  name, email (prefilled and read-only when the invite carries one), password
    - confirm, and a hidden timezone from
      `Intl.DateTimeFormat().resolvedOptions().timeZone`. "Create account"
      submits on fixtures: success toast plus a success card, no request.
      Client-side validation only (required, email shape, password ≥ 8 and
      matching confirm).
- `pages/auth/closed.tsx` — `auth.closed.title` = "Sign-ups are closed for
  now." + `auth.closed.body` = "We are in a closed beta with a small group of
  testers." + "Go to login" → `login()`.
- `pages/auth/forgot-password.tsx` — email field, submit shows the success
  state `auth.forgot.sent` = "If that address has an account, a reset link is
  on its way."
- `pages/auth/reset-password.tsx` — reads the `{token}` route parameter,
  password + confirm, submit shows the success state with a "Go to login"
  link.
- `pages/auth/login.tsx` — add the "Forgot password?" link
  (`auth.forgot.link`) to `password.request()`.
- i18n: `auth.*` additions in EN/PT/ES.

**Steps.** 1. Routes. 2. Register + closed. 3. Forgot + reset. 4. The login
link. 5. EN/PT/ES. 6. Mobile check. 7. Gate (+ PHP gates).

**Done when.**

- `/register?invite=ok-123` shows the form with the invite email read-only;
  `/register?invite=nope` and `/register` land on `/register/closed`.
- Forgot and reset both show their success states; login links to forgot.
- Gate passes.

**Not in this phase.** Real registration, real invites, real password reset
(spec "Out of scope").

### Phase 24 — S11 Notifications popover + realtime toasts

Status: PENDING
Role: inertia-frontend · Depends on: 10, 11, 12, 17, 22 · Covers: AC02, AC03, AC13 · Size: M
Spec: B.6 S11, B.7

**Goal.** Make the bell work, and turn three notification types into toasts
when they arrive over the channel.

**Contract.**

- `features/notifications/notifications-popover.tsx` — a `Popover` anchored
  on the bell `IconButton`: the list from `useNotifications()`, each row a
  `StatusDisc` by type + the one-line text from
  `notifications.<type>` with the row's `data` params + the relative time,
  an unread dot on unread rows, a "Mark all as read" action
  (`useMarkAllRead`), and the empty state
  `notifications.empty` = "Nothing new". Each row links to where it matters:
  `gmail_reauthorization_required` → `account()`,
  `application_failed` → `applications()` (attention tab),
  `daily_limit_reached` → `plans()`, `jobs_collected` → `jobs()`,
  `sending_auto_paused` → `account()`.
- `components/patterns/top-bar.tsx` — the bell becomes the popover trigger;
  `notificationsDot` is driven by `AccountStatus.unreadNotifications > 0`
  (read in `AppLayout`, which already owns the layout-level queries).
- `data/realtime/handlers.ts` — extend `notification.created`: after the
  cache update, `toast` for exactly the three types
  `gmail_reauthorization_required`, `sending_auto_paused` and
  `daily_limit_reached` (tone `error` for the first two, `info` for the
  third), using the same `notifications.<type>` line.
- i18n: `notifications.<type>` for the five types plus
  `notifications.title`, `notifications.mark_all`, `notifications.empty`,
  in EN/PT/ES. Copy: "Your Gmail connection expired — reconnect to keep
  sending.", "An application to :company could not be delivered.", "You
  reached today's limit of :count applications.", ":count new jobs match your
  preferences.", "Sending was paused automatically."
- The popover never polls; new rows arrive through the channel only.

**Steps.** 1. Popover + rows + mark all read. 2. Wire the bell and the unread
dot. 3. Toast rules in the handlers. 4. EN/PT/ES. 5. Mobile (the popover
becomes a bottom `Sheet` under 768 px). 6. Gate.

**Done when.**

- The bell opens the list with the unread dot; "Mark all as read" clears it
  and the dot disappears.
- Switching the DevToolbar Gmail state to "needs reconnection" emits the
  notification, shows the toast once and adds the row.
- Every row links to an existing page.
- Gate passes.

**Not in this phase.** Backend notifications.

### Phase 25 — S12 Command palette

Status: PENDING
Role: inertia-frontend · Depends on: 7, 13, 14, 15, 17 · Covers: AC02, AC03, AC15 · Size: S
Spec: B.6 S12

**Goal.** ⌘K / Ctrl+K and the search pill open a keyboard-navigable palette.

**Contract.**

- `features/palette/command-palette.tsx` — a `Modal` (full-screen on mobile)
  with a search `Input` and three grouped sections:
    - **Pages**: the six nav destinations plus Account and Plans.
    - **Jobs**: `useJobs({ q })` with the query debounced 250 ms, top 5, each
      opening the Jobs page with `?q=` (the palette navigates, it does not open
      the sheet).
    - **Actions**: "Pause sending" / "Resume sending" (whichever applies, via
      `usePauseSending`/`useResumeSending`), "Add language" → `profiles()`,
      "Edit preferences" → `preferences()`.
      Keyboard: ↑ / ↓ move, Enter runs, Esc closes, the active row is
      `aria-selected`; the whole list is one `role="listbox"`.
- `features/palette/use-palette.ts` — the open state plus the global
  ⌘K / Ctrl+K listener (ignored while an input has focus with a modifier-free
  key), exported so `TopBar` can also open it from the pill and the mobile
  search icon.
- `components/patterns/top-bar.tsx` — the search pill and the icon button
  open the palette; the pill keeps its `Kbd` hint (`⌘K`).
- Styleguide: a `CommandPalette` entry in
  `features/styleguide/overlays-section.tsx` (it is a new reusable pattern →
  AC15), with its `styleguide.*` labels.
- i18n: `palette.*` (placeholder, the three group titles, the action labels,
  the empty result line) in EN/PT/ES.

**Steps.** 1. `use-palette.ts` + the shortcut. 2. The palette with the three
groups and keyboard navigation. 3. Wire `TopBar`. 4. Styleguide entry. 5. EN/PT/ES. 6. Gate.

**Done when.**

- ⌘K (and Ctrl+K) and both search controls open the palette; Esc closes it.
- Typing filters pages, jobs and actions; ↑ / ↓ + Enter navigate and run
  without the mouse.
- "Pause sending" pauses the live panel.
- The pattern is on `/dev/styleguide`.
- Gate passes.

**Not in this phase.** Anything else.

### Phase 26 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–25 · Covers: every AC · Size: S
Spec: Acceptance criteria, Verification

**Goal.** Run the full gate, the forbidden-pattern greps and the AC
walkthrough, then hand the owner a short manual checklist. No feature work; if
something fails, report it — fixes are a follow-up phase decided by the owner.

**Contract.**

- Deterministic gate, in this order: `yarn run check`, `yarn types:check`,
  `yarn build`, `composer lint:check`, `composer types:check`,
  `php artisan test`. Compare any failure against the D1 baseline
  (`docs/features/client-app-foundation/plan.md` formatting and
  `ApplicationTemplateRenderer.php` pint) and say explicitly whether the
  failure is new.
- Forbidden-pattern greps over `resources/js` (excluding
  `resources/js/{actions,routes,wayfinder}`):
    - `refetchInterval`, `wire:poll`, `->poll(` → must be empty (no polling).
    - `#[0-9a-fA-F]{3,8}` in `.tsx` / `.ts` → must be empty (no raw hex).
    - `https?://` and `@[a-z0-9-]+\.[a-z]{2,}` in `data/fixtures/**` → must be
      empty (no URLs, no addresses in client-visible fixture data).
    - `'/internal` outside `data/endpoints.ts` → must be empty.
    - `dashboard.live.step.` and `dashboard.mode"` in `lang/*.json` → must be
      empty (keys replaced in phases 19–20).
- i18n check: the three `lang/*.json` files have **identical key sets**
  (a small script or `python3 -c` diff) and no value is left in English in
  `pt.json` / `es.json` for the keys this feature added.
- AC walkthrough: go through AC01–AC15 in order and record, per AC, what was
  exercised and the result.
- Spec smoke tests: queue from Jobs on Starter → watch the dashboard live
  panel run the stages → the row appears in Applications and the KPI tiles
  and chart change; the same on Pro through the review modal; Free running
  automatically; pause / resume; simulate failure.
- Manual checklist for the owner (things only they can sign off): each plan
  in the DevToolbar, each state (normal / loading / empty / error), each
  language, and the widths 360 / 390 / 768 / 1024 / 1280 / 1440 / 1600 px, on
  every screen; plus the visual comparison of the dashboard against
  `docs/features/client-app-foundation/reference/dashboard-mockup.png`.

**Steps.** 1. Run the gate and record the output. 2. Run the greps and the
i18n key diff. 3. Walk AC01–AC15. 4. Run the smoke tests. 5. Write the report
in the session (not into a new doc file) and update this plan's status board.

**Done when.**

- Every command above has been run and its result recorded, with new failures
  separated from the D1 baseline.
- Every grep listed returns nothing (or the exception is explained).
- The three lang files have the same key set.
- AC01–AC15 each have a verdict, and the owner has the manual checklist.

**Not in this phase.** Fixing whatever it finds, writing tests, committing.
