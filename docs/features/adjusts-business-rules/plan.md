# Plan — Adjusts Business Rules

Source spec: `docs/features/adjusts-business-rules/spec.md` · SHA-256 `e080f1d173cd60a48650b89ee6116daf608dca5ee7ac2b889ff62e5ca0eaf87a`
Product truth: the spec itself (sections 1–9).
Run phases with `/execute-phases docs/features/adjusts-business-rules/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                           | Role             | Depends on | Size | Status      |
| ----- | ----------------------------------------------- | ---------------- | ---------- | ---- | ----------- |
| 1     | Batch "reviewed" endpoint (Approve all, API)    | laravel-backend  | none       | M    | DONE        |
| 2     | Approve all in the review modal                 | inertia-frontend | 1          | M    | DONE        |
| 3     | `JobCard` stack: matches first + `stackMatches` | laravel-backend  | none       | S    | DONE        |
| 4     | Highlight matching stack chips on job rows      | inertia-frontend | 3          | M    | PENDING     |
| 5     | Notifications popover header wraps, no clip     | inertia-frontend | none       | S    | DONE        |
| 6     | Language menu label alignment                   | inertia-frontend | none       | S    | DONE        |
| 7     | Job detail sheet highlight (OD2)                | inertia-frontend | 4          | S    | PENDING     |
| 8     | Verification and report                         | orchestrator     | 1–7        | S    | PENDING     |

## Audit — 2026-10-03

| Check               | Result                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Versions            | Laravel 13.33.0, Filament 5.8.4, inertia-laravel 3.3.4, Wayfinder 0.1.21, React 19.2, @inertiajs/react 3, @headlessui/react 2, @tanstack/react-query 5. No new dependency needed.                                                                                                                                                                                                                                                                                                 |
| Data model          | None needed (spec §3). No migrations in any phase.                                                                                                                                                                                                                                                                                                                                                                                                                                |
| Single approval     | `POST /internal/applications/reviewed` → `ReviewController::reviewed` (`QueueReviewedRequest`, mode `review` only, subject/body rules + messages) → `QueueApplication::handle(..., subject:, body:)`; returns `QueueResult` (`queued[]`, `rejected[]`, `quota`).                                                                                                                                                                                                                  |
| Drafts              | `POST /internal/applications/drafts` (`ReviewDraftsRequest`, `jobIds` max 20 → R9 already true).                                                                                                                                                                                                                                                                                                                                                                                  |
| `QueueApplication`  | Own transaction + user row lock per call; checks eligibility (incl. daily quota) → already applied → recipient → profile → pool match; broadcasts `SendingUpdated::broadcastFor($user->id)` per queued app; `origin`/`status = queued` / `scheduled_for` via `SendScheduler`. Reusable as-is for the batch (AC12).                                                                                                                                                                |
| "Quota reason"      | Quota exhaustion surfaces as eligibility failure → `QueueRejectionMessage` → `queue.reject.not_eligible` ("You can't queue applications right now."). That is the existing reason R5/AC6 refer to.                                                                                                                                                                                                                                                                                |
| Same-company drafts | Two drafts for one company: the 2nd gets `queue.reject.already_applied` after the 1st is queued — consistent with R4/R6.                                                                                                                                                                                                                                                                                                                                                          |
| Review modal        | `resources/js/features/review/review-modal.tsx`: state `index`, `edits: Record<jobId, {subject, body}>`, `outcomes: Record<jobId, 'queued'                                                                                                                                                                                                                                                                                                                                        | 'skipped'>`; "finished" = `load.isSuccess && index >= drafts.length`; summary `review.summary`; title `review.done.title`. Client trims subject/body before sending. Uses `useQueueReviewed` (`resources/js/data/hooks/use-queue-reviewed.ts`, real + fixture via `fromSource`). No confirm-dialog primitive in `components/ui`; `Modal`has`title`/`footer`. |
| Endpoints           | `resources/js/data/endpoints.ts` maps Wayfinder actions (`ReviewController.reviewed()`). Wayfinder output (`resources/js/actions`, `routes`, `wayfinder`) is git-ignored and regenerated by the Vite plugin / `php artisan wayfinder:generate`.                                                                                                                                                                                                                                   |
| Stack on cards      | `JobCardResource` sends `array_slice($profile->stack, 0, 6)` — matches beyond the 6th are cut (spec §9 assumption confirmed). `JobDetailResource` extends it (detail sheet gets the same 6). `ReviewDraftPresenter` also embeds `JobCardResource` (review modal `JobSummary` chips).                                                                                                                                                                                              |
| Normalization       | Profile `stack` is stored already normalized (`ExtractJobPostingProfileJob` → `StackNormalizer::normalize`); preference stack normalized in `PreferenceCriteria::fromPreference`; pool filter uses `job_posting_profiles.stack ?                                                                                                                                                                                                                                                  | array[...]` (AC18 holds today; not touched).                                                                                                                                                                                                                                                                                                                 |
| Job row             | `resources/js/components/patterns/job-row.tsx`, neutral chip = `<Chip variant="stack" className="border border-hairline bg-card">`; selected row tone = `border-accent-line bg-accent-soft`. Callers with real data: `features/dashboard/new-matches-card.tsx`, `features/jobs/jobs-list.tsx`; demo callers: `features/landing/feature-matching.tsx`, `features/styleguide/rows-section.tsx`. `cn` = `twMerge(clsx())`. Tokens `accent-soft`, `accent-deep`, `accent-line` exist. |
| Fixtures            | `toJobCard` in `resources/js/data/fixtures/handlers/cards.ts`; stored fixture jobs typed `JobDetail` (`fixtures/state.ts`), preferences in `fixtureState.get().preferences`.                                                                                                                                                                                                                                                                                                      |
| Notifications       | `NotificationsList` header: `flex items-center gap-2 px-1 justify-between`, ghost `Button` (`whitespace-nowrap`). Desktop `Popover className="w-80"`; mobile `Sheet` uses `showTitle={false}`.                                                                                                                                                                                                                                                                                    |
| Language menu       | `useLocaleEntries()` sets `icon: Check` only on the current locale; `Menu` renders the icon (20px, `gap-3`) only when present. `mobile-nav.tsx` reuses `useLocaleEntries()` and detects selection via `Boolean(entry.icon)` → unselected entries must keep `icon: undefined`. Other menus: `user-menu` (all items have icons), `region-menu` (no icons), profiles/styleguide — no mixed icon/no-icon menu found besides the language one.                                         |
| Copy                | `lang/en.json`, `lang/pt.json` (flat keys). New keys from spec §5 not present yet.                                                                                                                                                                                                                                                                                                                                                                                                |
| Tests               | No existing test covers review/JobCard specifically (`tests/Feature/Outreach/SendingGuardsHttpTest.php` is the closest). No tests are written (CLAUDE.md).                                                                                                                                                                                                                                                                                                                        |
| Commands            | `vendor/bin/pint --dirty --format agent`, `composer lint:check`, `composer types:check`, `composer test`, `yarn check`, `yarn check:fix`, `yarn types:check`, `yarn build`, `composer ci:check`.                                                                                                                                                                                                                                                                                  |
| Working tree        | Unrelated local changes in `.claude/settings.json`, `tests/TestCase.php` — not touched by any phase.                                                                                                                                                                                                                                                                                                                                                                              |

## Owner decisions

### D1 — Include drafts skipped earlier in Approve all? (spec OD1)

**Decided 2026-10-03 (owner): A.** Blocks: nothing (plan follows R2) · Options: A (recommended) exclude — skipped means "no", as R2/AC3 say; B include skipped ones too (would change R2 and AC3) · Why: the spec already settled on A; confirm so OD1 can be closed in the spec.

### D2 — Highlight matching tags in the job detail sheet too? (spec OD2)

**Decided 2026-10-03 (owner): A — Phase 7 unblocked.** Blocks: nothing · Options: A (recommended) yes, same accent chip in `job-detail-sheet.tsx` (data is already there after Phase 3, one small file); B no, rows only (Phase 7 is dropped) · Why: the spec covers rows only; the review modal's `JobSummary` chips will also receive matches-first order after Phase 3 but stay neutral unless the owner extends D2 to it.

## Global constraints (every phase)

- No git writes (only `/execute-phases` commits, one per phase, orchestrator only). No migrations, seeders or destructive DB ops. No new/changed packages. No new or modified tests.
- English everywhere in code/comments; UI copy only via `lang/en.json` + `lang/pt.json` (EN/PT only, no Spanish).
- Realtime, never polling. Do not add `refetchInterval`, `->poll()` or `wire:poll`.
- React Compiler: no speculative `useMemo`/`useCallback`/`React.memo`.
- Single approval (`/internal/applications/reviewed`) and Starter/Free flows must behave exactly as today (R1, §8).
- Do not change `MatchingJobPostings`, `StackNormalizer`, the 6-tag cap, the 20-draft cap, plan limits, or the Stack preference editor (§8).
- `QueueApplication` is reused unchanged; applications from the batch are `origin = manual`, `status = queued` (AC12).
- Wayfinder routes for any new endpoint; no hardcoded URLs in React.
- Report extra ideas instead of building them.

## Acceptance-criteria coverage

| AC   | Phases                                      |
| ---- | ------------------------------------------- |
| AC1  | 2, 8                                        |
| AC2  | 1 (403), 2 (button only in review modal), 8 |
| AC3  | 1, 2, 8                                     |
| AC4  | 1, 2, 8                                     |
| AC5  | 2, 8                                        |
| AC6  | 1, 8                                        |
| AC7  | 1, 2, 8                                     |
| AC8  | 2, 8                                        |
| AC9  | 2, 8                                        |
| AC10 | 2, 8                                        |
| AC11 | 2, 8                                        |
| AC12 | 1, 8                                        |
| AC13 | 3, 4, 8                                     |
| AC14 | 3, 4, 8                                     |
| AC15 | 4, 8                                        |
| AC16 | 3, 4, 8                                     |
| AC17 | 3, 4, 8                                     |
| AC18 | 3 (filter untouched), 8                     |
| AC19 | 5, 8                                        |
| AC20 | 5, 8                                        |
| AC21 | 5, 8                                        |
| AC22 | 6, 8                                        |
| AC23 | 6, 8                                        |
| AC24 | 6, 8                                        |

## Phases

### Phase 1 — Batch "reviewed" endpoint (Approve all, API)

Status: DONE
Evidence: route:list shows `applications.reviewed` and `applications.reviewed.batch` (POST); `pint --dirty` clean on phase files; `composer types:check` 0 errors; `php artisan test` 33/33 pass; code-reviewer APPROVED. `composer test` lint step still fails on unrelated pre-existing files (`ApplicationTemplateRenderer.php`, `tests/TestCase.php`), not touched by this phase.
Role: laravel-backend · Depends on: none · Covers: AC2 (403), AC3, AC4, AC6, AC7, AC12 (server side) · Size: M
Spec: R1–R6, R9, §4 "Approve all" steps 4–5 + unhappy paths, §9 (single batch request, `QueueResult`)

**Goal.** One request that queues a list of reviewed drafts in order, each through `QueueApplication` with its own subject/body, rejecting per draft without stopping the batch.

**Contract.**

- Route (inside the existing `internal.` group, next to `applications.reviewed`):
  `Route::post('/applications/reviewed/batch', [ReviewController::class, 'reviewedBatch'])->name('applications.reviewed.batch');`
- `App\Http\Requests\Client\QueueReviewedRequest`: move the subject/body rules and messages into public static methods, used by its own `rules()`/`messages()` (behaviour identical):
    - `public static function subjectRules(): array` → `['required', 'string', 'min:1', 'max:200', <closure: fail __('review.subject.variables') if /\{\{.*?\}\}/ matches>]`
    - `public static function bodyRules(): array` → `['required', 'string', 'min:1', 'max:5000', <closure: fail __('review.body.variables') if any {{ var }} other than job_url>]`
    - `public static function contentMessages(): array` → the current eight `subject.*`/`body.*` → `review.subject.length` / `review.body.length` messages.
- New `App\Http\Requests\Client\QueueReviewedBatchRequest`:
    - `authorize(PlanCatalog $plans): Response` — same match as `QueueReviewedRequest`: `'review'` allow; `'select'` deny `__('review.mode.denied')`; default deny `__('queue.mode.select_plans_only')`.
    - `rules()` (shape only, so content problems reject one draft instead of 422-ing the batch):
      `'drafts' => ['required', 'array', 'min:1', 'max:20']`, `'drafts.*' => ['required', 'array:jobId,subject,body']`, `'drafts.*.jobId' => ['required', 'integer', 'distinct']`, `'drafts.*.subject' => ['present', 'nullable', 'string']`, `'drafts.*.body' => ['present', 'nullable', 'string']`.
- `ReviewController::reviewedBatch(QueueReviewedBatchRequest $request, QueueApplication $queueApplication, AccountStatusPresenter $presenter): JsonResponse`
    - Iterates `drafts` in request order (= modal order, R5). Per draft:
        1. `Validator::make(['subject' => $subject, 'body' => $body], ['subject' => QueueReviewedRequest::subjectRules(), 'body' => QueueReviewedRequest::bodyRules()], QueueReviewedRequest::contentMessages())`; on failure → `rejected[] = ['jobId' => $jobId, 'reason' => <first error message>]`, continue (R4, R6).
        2. Posting not found → reason `QueueRejectionMessage::for('missing')`.
        3. `$queueApplication->handle($user, $posting, ApplicationOrigin::Manual, subject: $subject, body: $body)`; `null` → reason `QueueRejectionMessage::for((string) $queueApplication->rejectionReason())`; else `queued[] = ['jobId', 'applicationId', 'scheduledFor' => ?->toIso8601String()]`.
    - Returns `['queued' => [...], 'rejected' => [...], 'quota' => $presenter->quota($user)]` (the `QueueResult` contract).
    - Quota exhaustion mid-batch needs no special code: later drafts fail eligibility → `queue.reject.not_eligible` (R5, AC6); queued ones stay queued.
    - Postings loaded once (`JobPosting::query()->whereKey($ids)->get()->keyBy('id')`, as in `ApplicationsController::store`).
    - Optional: extract a private helper shared with `reviewed()` for the "handle + build row" part; `reviewed()`'s response must stay byte-identical.
- Docblock in the controller's style: "Queue several reviewed emails in the given order (Pro plan). Returns the `QueueResult` contract."

**Steps.**

1. Add the static rule methods to `QueueReviewedRequest` and use them in its `rules()`/`messages()`.
2. Create `QueueReviewedBatchRequest`.
3. Add `reviewedBatch()` to `ReviewController`.
4. Register the route.
5. `php artisan wayfinder:generate` so `ReviewController.reviewedBatch` exists for Phase 2.

**Done when.**

- `php artisan route:list --name=applications.reviewed` lists both `applications.reviewed` and `applications.reviewed.batch` (POST).
- Starter/Free (`select`/other mode) gets 403 with the existing messages; `review` mode passes authorization (read the code path; no new tests).
- A draft with `{{ company }}` in the subject or an empty body is rejected with the translated validation message while the other drafts are queued.
- `vendor/bin/pint --dirty --format agent`, `composer types:check` and `composer test` pass.

**Not in this phase.** Any React/i18n change (Phase 2). No change to `QueueApplication`, `ReviewDraftsRequest` or the single endpoint's behaviour.

### Phase 2 — Approve all in the review modal

Status: DONE
Evidence: `yarn run check` 0 errors (7 pre-existing unused-var warnings outside this phase), `yarn types:check` passes; code-reviewer APPROVED. Button, confirmation, batch hook (real + fixture) and rejection summary in place; owner to verify in fixture mode or against the API.
Role: inertia-frontend · Depends on: 1 · Covers: AC1, AC2 (UI), AC3, AC4, AC5, AC7–AC11 · Size: M
Spec: R1–R3, R7, R8, §4 "Approve all" steps 1–6 + unhappy paths, §5 review modal bullets + copy table

**Goal.** A secondary **Approve all** button in the review footer that, after an in-modal confirmation, sends every undecided draft (as currently edited) in one request and shows the completion summary with rejections.

**Contract.**

- `resources/js/data/endpoints.ts`: `queueReviewedBatch: (): Endpoint => ReviewController.reviewedBatch()`.
- New hook `resources/js/data/hooks/use-queue-reviewed-batch.ts`, modelled on `use-queue-reviewed.ts`:
    - Variables: `{ drafts: { jobId: number; subject: string; body: string }[] }`; result `QueueResult`; error `ApiError`; `onSuccess: () => invalidateAfterQueue(queryClient)`.
    - `real`: `apiFetch<QueueResult>(e.url, { method: e.method, body: { drafts } })`.
    - `fixture`: for each draft in order: try `validateReviewed(subject, body)`; on `ApiError` push `{ jobId, reason: <first field error> }` to `rejected`; else `fixtureState.queue([jobId], 'manual')`, merge its `queued`/`rejected`, set `reviewedOverrides` for queued rows (trimmed); start `engine` if anything was queued; return the merged result with the last `quota`.
- `review-modal.tsx` (`ReviewSession`):
    - `undecided = drafts.filter((d) => !outcomes[d.job.id])` (skipped and queued excluded — R2, AC3).
    - Content per undecided draft = `edits[id] ?? { subject: draft.subject, body: draft.body }`, sent trimmed (R3, AC4).
    - Disabled reason (R8, AC5): any undecided draft that has an entry in `edits` whose trimmed subject length is outside 1–200 or body outside 1–5000. Also disabled while either mutation is pending.
    - Hidden when `undecided.length === 0` (AC11). Shown in the reviewing footer (whenever a draft is displayed), `variant="secondary-tile"`, label `review.approve_all`, placed between Skip and "Approve and queue"; single approval stays the primary button.
    - Confirmation (R7, AC8): a `confirming` state inside the same `Modal` — title `review.approve_all.title`, content `review.approve_all.body` with `count = undecided.length`, footer `review.approve_all.cancel` (secondary-tile, returns to the draft with nothing sent) and `review.approve_all` (primary, `loading` while pending).
    - On success: mark each `queued[].jobId` as `'queued'`; store `rejected` (`{ jobId, reason }[]`) in new state; set `index = drafts.length` → existing finished state (AC9).
    - On error (AC10): leave `outcomes`/`index` untouched, leave confirmation, `toast.error(error.status === 403 ? error.message : t('review.failed'))`; button usable again for retry.
    - Finished state: existing `review.summary` (queued count now includes batch-queued drafts, skipped unchanged), then, when rejections exist, `review.summary_rejected` with `rejected = count` and a list of `<company name> — <reason>` (company from the matching draft's `job.company.name`).
    - `close` stays blocked while any mutation is pending; `onClose(queuedCount)` unchanged.
- Copy (add to `lang/en.json` and `lang/pt.json`, next to the other `review.*` keys):

    | Key                         | EN                                                                                                            | PT                                                                                                                               |
    | --------------------------- | ------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
    | `review.approve_all`        | Approve all                                                                                                   | Aprovar todas                                                                                                                    |
    | `review.approve_all.title`  | Approve all drafts?                                                                                           | Aprovar todas as candidaturas?                                                                                                   |
    | `review.approve_all.body`   | This will queue :count applications as shown, including your edits. They will be sent on your daily schedule. | Isso vai enfileirar :count candidaturas como estão, incluindo suas edições. Elas serão enviadas conforme sua programação diária. |
    | `review.approve_all.cancel` | Cancel                                                                                                        | Cancelar                                                                                                                         |
    | `review.summary_rejected`   | Not queued: :rejected                                                                                         | Não enfileiradas: :rejected                                                                                                      |

**Steps.**

1. Add the endpoint and the hook.
2. Add the copy keys (EN + PT).
3. Extend `ReviewSession` with undecided/disabled logic, confirmation step, batch submit, rejection summary.

**Done when.**

- Pro user, 5 drafts, 1 approved + 1 skipped → confirmation says 3; confirm → 3 queued, skipped absent, "Review complete" summary (AC3, AC9). Verified in fixture mode (`VITE_USE_FIXTURES=true`) or against the real API by the owner.
- Emptying a draft's subject disables Approve all (AC5); Cancel creates nothing (AC8); with everything decided the button is gone (AC11).
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Server changes; stack highlighting; any change to Starter `confirm-send-modal`.

### Phase 3 — `JobCard` stack: matches first + `stackMatches`

Status: DONE
Evidence: tinker checks on StackHighlight::forCard pass (matches first, normalized comparison, empty prefs keep order, >6 matches capped); pint clean on phase files; phpstan 0 errors (needs --memory-limit=1G; composer types:check hits the 128M default); php artisan test 33/33; code-reviewer APPROVED.
Role: laravel-backend · Depends on: none · Covers: AC13, AC14, AC16, AC17, AC18 (server side) · Size: S
Spec: R10, R11, R12 (order + cap), R13, §4 "Stack tags", §9 (cause = display cap)

**Goal.** The card's 6 stack tags always include the user's matching technologies, listed first, and the payload says which tags match.

**Contract.**

- New `App\Client\StackHighlight` (final class):
    ```php
    /**
     * Matching tags first (original order), then the rest (original order), capped at $limit.
     * Comparison uses StackNormalizer on both sides; displayed values keep the stored spelling.
     *
     * @param  array<array-key, mixed>  $stack      profile stack
     * @param  list<string>             $preferred  already normalized (StackNormalizer)
     * @return array{stack: list<string>, matches: list<string>}
     */
    public static function forCard(array $stack, array $preferred, int $limit = 6): array
    ```
    - Empty `$preferred` → `['stack' => first $limit items in original order, 'matches' => []]` (R13, AC16).
    - An item matches when `StackNormalizer::normalize([$item])[0]` is in `$preferred` (R11, AC14).
    - `matches` = the displayed items that match (subset of `stack`, same strings). More than `$limit` matches → `$limit` matches, all highlighted (AC17).
- `JobCardResource::toArray`:
    - `$preferred = $user instanceof User && $user->jobPreference !== null ? StackNormalizer::normalize($user->jobPreference->stack ?? []) : []` (relation cached on the request user; one query per request).
    - Replace `'stack' => array_slice(...)` with the `forCard()` result: `'stack' => $highlight['stack']`, `'stackMatches' => $highlight['matches']`.
    - Update the class docblock to mention `stackMatches`.
- Applies to every `JobCard` consumer (dashboard matches, jobs page, review drafts) and `JobDetailResource` via inheritance — expected.
- `MatchingJobPostings` is not modified (AC18 is the existing rule).

**Steps.**

1. Create `StackHighlight`.
2. Use it in `JobCardResource`.

**Done when.**

- `php artisan tinker --execute` with `StackHighlight::forCard(['android','kotlin','java','websocket','mqtt','grpc','php'], ['react','php'])` returns `stack[0] === 'php'`, 6 items, `matches === ['php']` (AC13); `forCard(['nodejs','go'], ['node.js'])` returns `matches === ['nodejs']` (AC14); `forCard([...], [])` keeps order and `matches === []`.
- `vendor/bin/pint --dirty --format agent`, `composer types:check`, `composer test` pass.

**Not in this phase.** TS contract and rendering (Phase 4). Pool filter, `StackNormalizer`, preference editor.

### Phase 4 — Highlight matching stack chips on job rows

Status: PENDING
Role: inertia-frontend · Depends on: 3 · Covers: AC13–AC17 (UI) · Size: M
Spec: R12, R13, §5 job row bullet

**Goal.** On dashboard "Novas vagas" and the jobs page, matching chips use the accent chip style; the rest stay neutral.

**Contract.**

- `resources/js/types/contracts.ts` → `JobCard`: add `stackMatches: string[]; // subset of stack matching the Stack preference, listed first` (update the `stack` comment: "max 6 shown, matches first").
- `JobRow` (`job-row.tsx`): new optional prop `stackMatches?: string[]` (default none → nothing highlighted, so landing/styleguide callers are unchanged). Chip per item:
    - match: `<Chip variant="stack" className="border border-accent-line bg-accent-soft text-accent-deep">` (accent-line border keeps it visible on a selected row, whose background is also `bg-accent-soft`; border width keeps size/shape identical).
    - non-match: unchanged `className="border border-hairline bg-card"` (AC15).
    - Order comes from the server; do not re-sort client side.
- Pass `stackMatches={job.stackMatches}` in `features/dashboard/new-matches-card.tsx` and `features/jobs/jobs-list.tsx`.
- Fixtures: `toJobCard` (`fixtures/handlers/cards.ts`) produces the same shape — matches first against `fixtureState.get().preferences.stack` (lowercase/trim comparison is enough), cap 6, `stackMatches`. If `JobDetail` (stored fixture jobs) now requires `stackMatches`, compute it where fixture `JobDetail`s are returned instead of editing catalog data; keep fixture edits minimal and `yarn types:check` green.

**Steps.**

1. Contract type.
2. `JobRow` prop + chip styling.
3. Two callers.
4. Fixture mapping.

**Done when.**

- With Stack preferences, every row on the dashboard and jobs page shows ≥1 accent chip, first in the list; without preferences no accent chip and original order.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Job detail sheet (Phase 7, D2) and review modal `JobSummary` chips.

### Phase 5 — Notifications popover header wraps instead of clipping

Status: DONE
Evidence: popover header now flex-wrap with min-w-0 title and wrapping button label; yarn run check 0 errors (warnings in unrelated files), yarn types:check passes; reviewed against contract. Owner visual check pending (PT/EN, desktop + mobile).
Role: inertia-frontend · Depends on: none · Covers: AC19, AC20, AC21 · Size: S
Spec: R14, §5 notifications bullet

**Goal.** Title and "mark all" action are fully visible in the `w-80` popover in EN and PT.

**Contract.**

- Only `resources/js/features/notifications/notifications-popover.tsx`, header `div` of `NotificationsList`:
    - Allow wrapping: `flex flex-wrap items-center gap-x-2 gap-y-1 px-1` + existing `justify-between` / `justify-end`; title `h2` gets `min-w-0`.
    - If the button still overflows on its own line, let its label wrap (`whitespace-normal text-left` on that button via `className`) — do not change the shared `Button` component.
- Copy, `onClick`, and `disabled={markAllRead.isPending || !notifications.data?.some((row) => row.readAt === null)}` unchanged (AC21). Empty state unchanged. Mobile sheet (`showTitle={false}`) must still right-align the button.

**Done when.**

- PT: "Notificações" and "Marcar todas como lidas" fully visible, no horizontal overflow; EN likewise (owner visual check, desktop popover + mobile sheet).
- `yarn check` passes.

**Not in this phase.** Copy, popover width, notification rows.

### Phase 6 — Language menu label alignment

Status: DONE
Evidence: MenuEntry inset spacer + LanguageSwitcher inset entries; yarn types:check passes, yarn run check 0 errors; code-reviewer APPROVED. Owner visual check pending (both locales).
Role: inertia-frontend · Depends on: none · Covers: AC22, AC23, AC24 · Size: S
Spec: R15, §5 language menu bullet, §9 last assumption

**Goal.** "English" and "Português" start at the same x; the selected option keeps check + spacing.

**Contract.**

- `resources/js/components/ui/menu.tsx`: `MenuEntry` gains `inset?: boolean` ("reserve the icon slot when there is no icon"). When `!Icon && item.inset`, render `<span aria-hidden="true" className="size-5 shrink-0" />` (20px = icon size) before the label. No change for entries without `inset` (all other menus unchanged).
- `resources/js/components/patterns/language-switcher.tsx`: `LanguageSwitcher` passes `entries.map((entry) => ({ ...entry, inset: true }))` to `Menu`. `useLocaleEntries()` stays as is, so `mobile-nav.tsx` (selection via `Boolean(entry.icon)`) is unaffected.
- Selected item markup (check 20px + `gap-3` + label) unchanged (AC24).

**Done when.**

- In both locales, opened menu: labels share the same left edge, check on the current locale (AC22, AC23); mobile pills unchanged.
- `yarn check` passes.

**Not in this phase.** Option order, copy, mobile pills, other menus.

### Phase 7 — Job detail sheet highlight

Status: PENDING
Role: inertia-frontend · Depends on: 4 · Covers: — (spec OD2, only if D2 = A) · Size: S
Spec: §10 OD2

**Goal.** Same accent chip for matching tags in the job detail sheet.

**Contract.**

- `resources/js/features/jobs/job-detail-sheet.tsx`: chips whose value is in `job.stackMatches` use `className="border border-accent-line bg-accent-soft text-accent-deep"`, others unchanged; order from the server.
- OD2 resolved in the spec (2026-10-03, owner: yes).

**Done when.**

- Detail sheet highlights exactly the chips highlighted on that job's row; `yarn check` passes.

**Not in this phase.** Review modal `JobSummary` (only if the owner extends D2).

### Phase 8 — Verification and report

Status: PENDING
Role: orchestrator (+ `code-reviewer`) · Depends on: 1–7 · Covers: AC1–AC24 · Size: S
Spec: §6, §7

**Goal.** Prove the whole feature against the spec and hand the owner a manual checklist.

**Steps.**

1. Full gate: `composer ci:check`; `yarn build` (bundle touched).
2. Forbidden-pattern grep on the feature diff: `->poll(`, `wire:poll`, `refetchInterval`, `useMemo(`, `useCallback(`, `React.memo`, Spanish copy, hardcoded `/internal/` URLs in `resources/js`, `migrate:fresh|db:wipe|TRUNCATE`.
3. Confirm untouched: `git diff --stat` shows no change in `MatchingJobPostings.php`, `StackNormalizer.php`, `QueueApplication.php`, `ReviewDraftsRequest.php`, `confirm-send-modal.tsx`, `mobile-nav.tsx`, `tests/`.
4. Route/authorization read-through for AC2; `StackHighlight` tinker checks from Phase 3 (AC13, AC14, AC16, AC17).
5. `code-reviewer` on the feature diff vs the AC list.
6. AC walkthrough table (AC1–AC24: pass / owner-check / fail).
7. Report divergences and propose spec updates (`Deviation:` notes), at least: quota reason is the generic `queue.reject.not_eligible` text; matches-first order also reaches the review modal `JobSummary` and job detail sheet via `JobCardResource` inheritance; D1/D2 outcomes.

**Done when.**

- Gate green; grep clean; review findings addressed or reported.
- Owner manual checklist delivered (from §7): Pro user, ~10 postings → edit one, skip one, Approve all, confirm → counts, edited content, skipped absent, summary; low remaining quota (AC6); Starter user (no button, 403); network failure (AC10); Stack preferences on dashboard + jobs page (highlight first, none without prefs); notifications popover PT/EN desktop + mobile; language menu both locales.

**Not in this phase.** New features or fixes beyond reported findings.
