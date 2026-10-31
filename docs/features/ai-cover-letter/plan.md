# Plan — AI Cover Letter and AI Plan

Spec: `docs/features/ai-cover-letter/spec.md` · SHA-256 `a81edc407ea118a2dee512a2c37fa39ea7cba7e942a088100c0cfa0572187235`
Run phases with `/exec-phase ai-cover-letter <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — 2026-10-31

Implemented: 0 · Partial: 1 · Divergent: 0 · Missing: 21 · Unverifiable: 0

| Requirement                      | State   | Evidence                                                                                                         |
| -------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------- |
| P1 Max plan                      | Missing | `app/Enums/PlanKey.php`, `config/talent.php` `plans.catalog`                                                     |
| P2 Plan capabilities             | Missing | `app/Plans/Plan.php`, `app/Plans/PlanCatalog.php`                                                                |
| P3 Config and env keys           | Missing | `config/talent.php`, `.env.example`                                                                              |
| P4 Enum, admin selects           | Missing | Filament uses `PlanKey::class`, so only the enum changes; `plan_key` columns are `string(32)`, no migration      |
| P5 Plans page and checkout       | Partial | `PlansResource` and `BillingController` are generic; frontend hard-codes 3 plans (`FEATURES`, `PLAN_KEYS`, grid) |
| P6-P9 Agent, inputs, schema      | Missing | Only `ExtractJobPostingProfile` and `ExtractCompanyPeople` exist                                                 |
| P10 Only the slot is AI          | Missing | `ApplicationTemplateRenderer` has the slot; no AI value                                                          |
| P11-P12 Length constants, layers | Missing | —                                                                                                                |
| P13 Manual letter maximum        | Missing | `max:6000` in `UpdateApplicationProfileRequest`, `PreviewProfileRequest`, fixture `MAX_COVER_LETTER`             |
| P14-P15 Validation and fallback  | Missing | —                                                                                                                |
| P16-P17 On demand, asynchronous  | Missing | `ReviewController::drafts` is synchronous, no AI                                                                 |
| P18 Cache and Regenerate         | Missing | `AiAgentResponseCache::remember` uses `firstOrCreate` (cannot overwrite), no user column                         |
| P19 Monthly quota                | Missing | —                                                                                                                |
| P20 `ai_usage_records.user_id`   | Missing | `2026_09_24_100002_create_ai_usage_records_table.php`                                                            |
| P21 Review screen                | Missing | `resources/js/features/review/review-modal.tsx`, `body-editor.tsx`                                               |
| P22 Privacy                      | Missing | Nothing logs prompts today; policy text belongs to `landing-and-legal`                                           |

Flagged, not planned: spec §2.3 is outdated (it says one agent and verified companies only; `ExtractCompanyPeople` and the tier-A gate exist). Recommend `/update-feature-spec ai-cover-letter`.

## Status board

| Phase | Title                                | Role             | Depends on | Origin               | Size | Status  |
| ----- | ------------------------------------ | ---------------- | ---------- | -------------------- | ---- | ------- |
| 1     | Plan capabilities                    | laravel-backend  | none       | P2                   | S    | DONE    |
| 2     | Frontend accepts the Max plan        | inertia-frontend | none       | P5                   | M    | PENDING |
| 3     | Max plan enabled                     | laravel-backend  | 1, 2       | P1, P3, P4, P5       | S    | PENDING |
| 4     | Length limits and manual maximum     | laravel-backend  | none       | P11, P13             | S    | PENDING |
| 5     | AI usage per user and letter cache   | laravel-backend  | none       | P18, P20             | S    | PENDING |
| 6     | Cover letter agent and validator     | laravel-backend  | 4          | P6-P9, P12, P14, P22 | M    | PENDING |
| 7     | Letter job, quota, fallback, event   | laravel-backend  | 1, 5, 6    | P10, P15, P17-P20    | M    | PENDING |
| 8     | Draft AI state and generate endpoint | laravel-backend  | 7          | P10, P16-P19         | M    | PENDING |
| 9     | Frontend contract, hook and realtime | inertia-frontend | 8          | P17, P21             | S    | PENDING |
| 10    | Review screen shows the AI letter    | inertia-frontend | 9          | P16, P17, P19, P21   | M    | PENDING |
| 11    | Profile hint and quota usage         | inertia-frontend | 9          | P10, P19             | S    | PENDING |
| 12    | Verification and report              | laravel-backend  | 1-11       | spec x code gap      | S    | PENDING |

## Owner decisions

All answered on 2026-10-31 and recorded in `spec.md` as D6-D17. None is open.

### D6-D17 — Summary

Blocks: nothing · Answer: plan key `max`, name "Max"; 100 per day, 500 letters per month, R$ 90 / € 29 / US$ 30; `gpt-5-mini` in config, extraction stays on `gpt-5-nano`; 100 words, 800 characters; no special treatment for longer manual letters (no clients yet); no CV text; automatic sends use the manual letter; AI CV in a later spec; no URL or email in a letter; generate for the open draft and the next one; a late letter keeps edits and offers "Use AI version"; chip below the editor.

## Global constraints (every phase)

- Never run git writes; `/exec-phase` makes the commits. Never `migrate:fresh/refresh/reset/rollback`, `db:wipe`, DROP or TRUNCATE of data. This feature uses new forward migrations (P20); never edit an existing migration. Running `php artisan migrate` is allowed.
- Never add, remove or upgrade packages. Never write or modify tests. If an existing test fails because of intended behaviour, stop and report it.
- Everything in English in the repo, except PT values in `lang/pt.json`. New copy goes to both `lang/en.json` and `lang/pt.json`. No Spanish copy.
- Only append missing keys to `.env.example`; never touch `.env`.
- Code checks plan capabilities (`aiWriting`, `aiMonthlyQuota`), never a `PlanKey`, for AI behaviour.
- Realtime, never polling. The private channel is `App.Models.User.{id}`; events implement `ShouldBroadcastNow` and are sent through `DispatchesClientEvent::dispatchClientEvent`, which is best-effort.
- Clients never see a recipient, the real job URL, a tier or a source. `{{ job_url }}` stays a token in draft bodies.
- Never log prompt text, AI output or personal data; record tokens and status only (P22). In `catch` blocks do not log the exception message of an AI call.
- Queue job `$timeout` stays below the database queue's `retry_after` (90).
- The AI never blocks queueing or sending. `QueueApplication` and the automatic path do not change.
- Checks per phase: `vendor/bin/pint --dirty --format agent`, `composer types:check`, `php artisan test --compact --filter=Outreach`. Frontend phases and any phase editing docs also run `yarn run check` and `yarn types:check`.

## Requirement coverage

| Requirement | Phases       |
| ----------- | ------------ |
| P1          | 3            |
| P2          | 1            |
| P3, P4      | 3            |
| P5          | 2, 3         |
| P6-P9       | 6            |
| P10         | 7, 8, 11     |
| P11         | 4            |
| P12         | 4, 6         |
| P13         | 4            |
| P14         | 6            |
| P15         | 7            |
| P16         | 8, 10        |
| P17         | 7, 8, 9, 10  |
| P18         | 5, 7, 8      |
| P19         | 7, 8, 10, 11 |
| P20         | 5, 7         |
| P21         | 9, 10        |
| P22         | 6, 12        |

## Phases

### Phase 1 — Plans declare their AI capabilities

Status: DONE
Evidence: `pint` passed; `composer types:check` 0 errors; `php artisan test --compact --filter=Outreach` 31 passed; tinker `get('pro')` gives `aiWriting` false and `aiMonthlyQuota` 0.
Role: laravel-backend · Depends on: none · Covers: P2 · Size: S
Origin: P2 "`Plan` gains capabilities read from the catalog config: `aiWriting` (bool) and `aiMonthlyQuota` (int). Existing plans: false / 0."

**Goal.** Code can ask a plan whether it writes with AI.

**Gap -> desired.** `Plan` has key, name, mode and daily limit -> it also has `aiWriting` and `aiMonthlyQuota`.

**Contract.**

- `app/Plans/Plan.php`: add constructor properties `public bool $aiWriting = false` and `public int $aiMonthlyQuota = 0`.
- `app/Plans/PlanCatalog.php` `catalog()`: read `ai_writing` and `ai_monthly_quota` from each entry, defaulting to false and 0. Update the `@var` shape.
- `config/talent.php` `plans.catalog`: add `'ai_writing' => false, 'ai_monthly_quota' => 0` to `free`, `starter` and `pro`.

**Steps.**

1. Value object and catalog.
2. Config.

**Done when.**

- In tinker, `app(App\Plans\PlanCatalog::class)->get('pro')` has `aiWriting` false and `aiMonthlyQuota` 0.
- `composer types:check` passes.

**Not in this phase.** The Max plan.

### Phase 2 — The frontend can display a fourth plan

Status: PENDING
Role: inertia-frontend · Depends on: none · Covers: P5 · Size: M
Origin: P5 "Client plans page (`PlansResource`) and billing checkout support the new plan."

**Goal.** Adding `max` to the backend can't break a screen.

**Gap -> desired.** `FEATURES[plan.key]` is undefined for an unknown key and the grids assume 3 plans -> `max` is known everywhere and the grids fit 4.

**Contract.**

- `resources/js/types/contracts.ts`: `PlanKey = 'free' | 'starter' | 'pro' | 'max'`.
- `resources/js/types/plans.ts`: `PLAN_KEYS` adds `'max'`.
- `resources/js/features/plans/plan-card.tsx` `FEATURES`: `max: ['gmail', 'matching', 'review_emails', 'ai_letter']`.
- `resources/js/features/landing/landing-plan-card.tsx` `FEATURES`: `max: ['auto', 'select', 'review', 'ai_letter']`. Only this minimum is added: the rest of the landing copy belongs to `landing-and-legal`.
- `resources/js/pages/plans.tsx`: `CELL` becomes `min-w-0 lg:col-span-6 xl:col-span-3`; the skeleton shows 4 cells.
- `resources/js/features/landing/pricing.tsx`: grid becomes `lg:grid-cols-2 xl:grid-cols-4`.
- `resources/js/features/dashboard/new-matches-card.tsx`: `SELECTION_PLANS = ['starter', 'pro', 'max']`.
- Fixtures: `resources/js/data/fixtures/state.ts` `PLAN_CONFIG.max = { dailyLimit: 100, mode: 'review' }`, `PLAN_NAMES.max = 'Max'`. `resources/js/data/fixtures/catalog/plans.ts`: `PRICES` adds `max: 9000` (br), `2900` (eu), `3000` (row); `BASE` adds `{ key: 'max', name: 'Max', mode: 'review', dailyLimit: 100, interval: 'month', highlighted: false }`.
- Copy in `lang/en.json` and `lang/pt.json`:
    - `plans.max.name`: "Max" / "Max".
    - `plans.features.ai_letter`: "AI-written cover letter for each job" / "Carta de apresentação escrita por IA para cada vaga".
    - `landing.plans.feature.ai_letter`: "An AI-written cover letter for each job" / "Uma carta de apresentação escrita por IA para cada vaga".
    - `queue.mode.select_plans_only`: "Choosing jobs is available on Starter, Pro and Max." / translate the current PT value the same way.

**Steps.**

1. Types and feature maps.
2. Grids, fixtures, copy.

**Done when.**

- With `VITE_USE_FIXTURES`, `/plans` shows 4 cards at desktop and phone widths, in EN and PT, with no missing translation.
- `yarn run check` and `yarn types:check` pass.

**Not in this phase.** Backend enum and config, the rest of the landing copy.

### Phase 3 — The Max plan exists

Status: PENDING
Role: laravel-backend · Depends on: 1, 2 · Covers: P1, P3, P4, P5 · Size: S
Origin: P1, P3, P4, P5 (D6, D7)

**Goal.** Admins can assign Max, and clients can see and buy it once the Stripe prices exist.

**Gap -> desired.** 3 plans -> 4, Max above Pro.

**Contract.**

- `app/Enums/PlanKey.php`: `case Max = 'max';`, label `'Max'`.
- `config/talent.php`:
    - `plans.catalog.max`: `['name' => 'Max', 'mode' => 'review', 'daily_limit' => (int) env('PLAN_MAX_DAILY_LIMIT', 100), 'ai_writing' => true, 'ai_monthly_quota' => (int) env('PLAN_MAX_AI_MONTHLY_QUOTA', 500)]`, last in the catalog.
    - `plans.prices.max`: `br` `env('PRICE_MAX_BRL', 9000)`, `eu` `env('PRICE_MAX_EUR', 2900)`, `row` `env('PRICE_MAX_USD', 3000)`, cast to int like the others.
    - `plans.stripe_prices.max`: `env('STRIPE_PRICE_MAX_BRL')`, `env('STRIPE_PRICE_MAX_EUR')`, `env('STRIPE_PRICE_MAX_USD')`.
- `.env.example`: append `PLAN_MAX_DAILY_LIMIT=100`, `PLAN_MAX_AI_MONTHLY_QUOTA=500`, `PRICE_MAX_BRL=9000`, `PRICE_MAX_EUR=2900`, `PRICE_MAX_USD=3000`, `STRIPE_PRICE_MAX_BRL=`, `STRIPE_PRICE_MAX_EUR=`, `STRIPE_PRICE_MAX_USD=`, next to their siblings.
- `app/Plans/Plan.php`, `ReviewController` comments that say "Starter and Pro" or "Pro plan": update the wording only where it became false.
- `plan_key` columns are `string(32)`: no migration. Filament selects and filters use `PlanKey::class`: no change. `JobCardResource` (`!== Free`) needs no change.
- Without Stripe ids, `BillingController` already reports the missing `STRIPE_PRICE_MAX_*` key and refuses as `unavailable`.

**Steps.**

1. Enum and config.
2. `.env.example`.

**Done when.**

- `/admin` user edit and filters and the invitation select list Max; a Max user gets `mode` `review` and can open review drafts.
- `/plans` returns 4 plans with the right price per region.
- Without Stripe ids, checkout for Max refuses without an exception reaching the client.
- `composer types:check` passes; `php artisan test --compact --filter=Outreach` passes.

**Not in this phase.** Any AI behaviour.

### Phase 4 — Cover letters have one length limit

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P11, P12, P13 · Size: S
Origin: P11, P13 (D9, D10)

**Goal.** One place defines the length, and the manual letter obeys it.

**Gap -> desired.** Manual letters allow 6000 -> 800, for every plan.

**Contract.**

- `app/Outreach/OutreachLimits.php`: `public const int COVER_LETTER_TARGET_WORDS = 100;`, `public const int COVER_LETTER_MAX_CHARS = 800;`.
- `app/Http/Requests/Client/UpdateApplicationProfileRequest.php` and `app/Http/Requests/Client/PreviewProfileRequest.php`: `'max:'.OutreachLimits::COVER_LETTER_MAX_CHARS` for `coverLetter`.
- `resources/js/data/fixtures/handlers/profiles.ts`: `MAX_COVER_LETTER = 800`.
- D10: no data change, no warning, no effect on `ApplicationProfile::isComplete()` or on sending. A stored longer letter fails validation only when the profile is saved.

**Steps.**

1. Constants.
2. Requests and fixture.

**Done when.**

- Saving a profile with an 801-character letter returns 422 on `coverLetter`; 800 is accepted.
- `composer types:check`, `yarn run check` and `yarn types:check` pass.

**Not in this phase.** The schema and prompt layers (Phase 6).

### Phase 5 — AI usage and letters belong to a user

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: P18, P20 · Size: S
Origin: P20 "`ai_usage_records.user_id` (nullable, forward migration)"; P18 cache

**Goal.** Quota can be counted per user, Regenerate can replace a cached letter, and account deletion removes letters.

**Gap -> desired.** No user on usage or cache rows, and `remember` can't overwrite -> both have a user, and the cache can be overwritten.

**Contract.**

- Migration `database/migrations/2026_10_31_000006_add_user_id_to_ai_usage_records_and_caches.php`:
    - `ai_usage_records`: `foreignId('user_id')->nullable()->after('job_posting_id')->constrained()->nullOnDelete()`, plus an index on `(user_id, created_at)`.
    - `ai_agent_response_caches`: `foreignId('user_id')->nullable()->constrained()->cascadeOnDelete()`. Assumption: a cached letter is personal data, so it must disappear with the account (`DeleteClientAccount` deletes the user row).
- `app/Models/AiUsageRecord.php`: `user_id` in `Fillable` and `@property`, plus a `user()` relation.
- `app/Models/AiAgentResponseCache.php`: `user_id` in `Fillable` and `@property`. `remember` gains an optional `?int $userId = null` that is stored. New `overwrite(string $agent, string $model, string $fingerprint, array $response, ?int $userId = null): void` using `updateOrCreate` on the same key.
- `ExtractJobPostingProfileJob`, `NamedContactCoverage` and `DiscoverNamedContacts` keep working with a null user.

**Steps.**

1. Migration, then `php artisan migrate`.
2. Models.

**Done when.**

- Inside a rolled-back transaction: `overwrite` replaces a cached response; deleting a user removes that user's cache rows and leaves their usage rows with a null `user_id`.
- `composer types:check` passes.

**Not in this phase.** Writing letters.

### Phase 6 — The agent writes a letter that passes validation

Status: PENDING
Role: laravel-backend · Depends on: 4 · Covers: P6, P7, P8, P9, P12, P14, P22 · Size: M
Origin: P6-P9, P12, P14, P22 (D8, D14)

**Goal.** A testable agent and a strict output check.

**Gap -> desired.** No writing agent -> `GenerateCoverLetter` and `CoverLetterValidator`.

**Contract.**

- `config/talent.php`, new top-level `ai`: `'cover_letter' => ['provider' => env('AI_COVER_LETTER_PROVIDER', 'openai'), 'model' => env('AI_COVER_LETTER_MODEL', 'gpt-5-mini'), 'reasoning_effort' => env('AI_COVER_LETTER_REASONING_EFFORT', 'minimal')]`. `.env.example`: append the three keys, in the AI area.
- New `app/Ai/Agents/GenerateCoverLetter.php`, implementing `Agent`, `HasProviderOptions`, `HasStructuredOutput`, using `BuildsCompactAgentContext` and `Promptable`, with no `#[Model]` attribute. Callers pass config values: `$agent->prompt($context, provider: ..., model: ..., timeout: 30)`.
    - Constants: `CACHE_SCHEMA_VERSION = 'cover-letter-v1'`, `CACHE_KEY = 'cover_letter_generation'`, `MARKER = '[[COVER_LETTER]]'`, `MAX_DESCRIPTION_CHARS = 4000`.
    - Constructor: `User $user`, `JobPosting $posting` (with `company`, `profile`), `ApplicationProfile $profile`.
    - `context(): string` through `compactContext`, with: `company`; `normalized_title`, `seniority`, `stack`, `summary` from `$posting->profile`; `description` from `plainText(...)`, then `ClientSafeText::redact`, then cut at 4000 (reuse the double-decode approach in `ExtractJobPostingProfile::descriptionText`); `language`; `manual_cover_letter`; `link_labels`; `client_stack` from `$user`'s preference `stack`; `email_body`.
    - `email_body` is `ApplicationTemplateRenderer::render($profile->email_body, $variables)` where `$variables` comes from `variablesFor`, with `cover_letter` = `MARKER`, `job_url` = `''`, `links` = link labels joined by newlines, and `company`, `job_title`, `job_location` passed through `ClientSafeText::redact`. No URL of any kind reaches the model from this field.
    - `manual_cover_letter` is the `cover_letter` value from `variablesFor`, passed through `ClientSafeText::redact`.
    - `instructions()`: write only the text that replaces the marker; no greeting, no sign-off, no subject, no URLs, no email addresses, no placeholders; write in the given language; aim at `COVER_LETTER_TARGET_WORDS` words and stay under `COVER_LETTER_MAX_CHARS` characters; one concrete point about the job and one concrete point about the client; the manual cover letter is the only source of facts about the client; `client_stack` only guides emphasis and is never a claim of experience; never invent experience, years, employers, degrees or numbers.
    - `schema()`: `cover_letter` string `max(OutreachLimits::COVER_LETTER_MAX_CHARS)` required; `language` string enum `['en', 'pt']` required.
    - `providerOptions()`: `['reasoning' => ['effort' => $effort]]` when the configured `reasoning_effort` is a non-empty string, otherwise `[]`.
- New `app/Ai/Support/CoverLetterValidator.php`, `static accepts(array $output, string $language): ?string`, returning the trimmed letter or null. Rejects when: not a string or empty after trim; `mb_strlen` above the maximum; contains `{{`, `}}` or `[[`; `ClientSafeText::redact($letter) !== $letter`; contains a bare domain (`/\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.(?:com|org|net|io|co|ai|dev|app|eu|uk|de|br|fr|es|pt|nl|jobs|careers|tech)\b/iu`); `language` differs from the profile language. Never truncates.

**Steps.**

1. Config and agent.
2. Validator.

**Done when.**

- In tinker with `GenerateCoverLetter::fake`: the context holds the marker, no `http`, no `@`, and a description of more than 4000 characters is cut.
- The validator rejects an empty letter, 801 characters, `{{`, `[[`, an email, a URL, `acme.com` and a wrong language, and accepts a clean letter in EN and PT.
- `composer types:check` passes.

**Not in this phase.** Queue, quota, cache, broadcast.

### Phase 7 — A queued job writes the letter, within quota, with a fallback

Status: PENDING
Role: laravel-backend · Depends on: 1, 5, 6 · Covers: P10, P15, P17, P18, P19, P20 · Size: M
Origin: P15, P17, P18, P19, P20

**Goal.** One job produces a letter or reports the fallback, and never fails loudly.

**Gap -> desired.** Nothing runs the agent -> a job, a quota reader and an event.

**Contract.**

- New `app/Ai/Support/CoverLetterQuota.php`: `used(User $user): int` counts `ai_usage_records` with this `user_id`, `agent = GenerateCoverLetter::CACHE_KEY`, `status = Completed`, `cache_hit = false`, `created_at` within the current calendar month in the app timezone. `limit(User $user): int` is `$plan->aiMonthlyQuota`. `exhausted(User $user): bool`. Assumption: a small overrun from jobs already running is accepted, with no lock.
- New `app/Ai/Support/CoverLetterFingerprint.php`, `for(User $user, JobPosting $posting, ApplicationProfile $profile): string` joining `CACHE_SCHEMA_VERSION`, user id, posting id, language and `hash('sha256', $profile->cover_letter."\n".$profile->email_body)`. New `CoverLetterFingerprint::cached(...)`: returns the cached letter for the configured model, or null.
- New `app/Ai/Support/CoverLetterEligibility.php`, `applies(User $user, ApplicationProfile $profile): bool`: plan `aiWriting`, `ApplicationTemplateRenderer::containsCoverLetterVariable($profile->email_body)`, and a non-empty trimmed manual letter.
- New `app/Ai/Jobs/GenerateCoverLetterJob.php`: `ShouldQueue`, `ShouldBeUnique`, queue `ai`, `$tries = 1`, `$timeout = 75`, `$uniqueFor = 90`, `uniqueId()` = `"{$userId}:{$jobPostingId}"`, constructor `int $userId, int $jobPostingId, bool $regenerate = false`.
- `handle()`:
    1. Load user, posting (with `company`, `profile`) and the active profile for the posting's language. If any is missing, or `Application` already exists for user and posting, or not `CoverLetterEligibility::applies`: send the `manual` event with reason `failed` when something is missing or not eligible; send nothing when already applied.
    2. Without `$regenerate`, a cached letter: record usage with `cache_hit = true`, send `ready`, stop.
    3. If `CoverLetterQuota::exhausted`: send `manual` with reason `quota`, stop.
    4. Up to 2 attempts (P15): call the agent, 30 seconds each, validate with `CoverLetterValidator`. Each attempt records a usage row (`agent`, `model`, `job_posting_id`, `user_id`, tokens, `cache_hit = false`, `duration_ms`, status `Completed` when accepted and `Failed` otherwise).
    5. Accepted: `AiAgentResponseCache::overwrite(...)` with `['cover_letter' => $letter, 'language' => $language]` and the user id, then send `ready`.
    6. Both rejected: send `manual` with reason `failed`.
- Catch `Throwable` around each AI call; never log its message. `failed()` sends `manual` with reason `failed`.
- New `app/Events/Client/CoverLetterUpdated.php`: `ShouldBroadcastNow`, `PrivateChannel('App.Models.User.'.$userId)`, `broadcastAs` = `cover_letter.updated`. Payload: `{ jobId: int, state: 'ready' | 'manual', reason: 'quota' | 'failed' | null, body: string | null, aiQuota: { used: int, limit: int } }`.
- `body` for `ready` is the draft body with the new letter, built with `ReviewDraftPresenter` so it stays client-safe (job URL token, redactions). Add `ReviewDraftPresenter::bodyFor(User, JobPosting, ApplicationProfile, ?string $letter): string`, and make `forUser` use it.
- `app/Client/ReviewDraftPresenter.php`: the letter, when given, replaces `$variables['cover_letter']`. Only the slot changes (P10).

**Steps.**

1. Quota, fingerprint, eligibility.
2. Job and event, and `bodyFor`.

**Done when.**

- With `GenerateCoverLetter::fake`, `Event::fake` and a rolled-back transaction: a valid output gives `ready` and one Completed usage row with `user_id`; a second run without `regenerate` makes no agent call and records `cache_hit = true`; with `regenerate`, it calls the agent and overwrites the cache; invalid twice gives 2 Failed rows and `manual`/`failed`; invalid then valid counts 1 toward the quota; at the limit, no agent call and `manual`/`quota`; an existing application makes no call; a provider exception never escapes.
- No log entry holds prompt or output text.
- `composer types:check` passes.

**Not in this phase.** HTTP and UI.

### Phase 8 — Drafts report their AI state and the client can ask for a letter

Status: PENDING
Role: laravel-backend · Depends on: 7 · Covers: P10, P16, P17, P18, P19 · Size: M
Origin: P16 (D15), P17, P18, P19

**Goal.** The frontend knows each draft's state and asks for generation only for the drafts the client reaches.

**Gap -> desired.** Drafts have no AI data -> an `ai` field and a generate endpoint.

**Contract.**

- `ReviewDraftPresenter::forUser`: add `'ai'`:
    - `null` when the plan has no `aiWriting`;
    - `['state' => 'off']` when `CoverLetterEligibility::applies` is false;
    - `['state' => 'ready']`, with the cached letter already in `body`;
    - `['state' => 'quota']` when there is no cached letter and the quota is exhausted;
    - `['state' => 'idle']` otherwise.
- `ReviewController::drafts` dispatches nothing (D15).
- New `app/Http/Controllers/Client/Internal/ReviewCoverLetterController.php` (invokable) and `app/Http/Requests/Client/GenerateCoverLetterRequest.php`: `jobId` required integer, `regenerate` sometimes boolean. `authorize` denies with 403 when the plan has no `aiWriting`.
- Route in `routes/web.php`, next to `applications.drafts`: `Route::post('/applications/drafts/cover-letter', ReviewCoverLetterController::class)->middleware('throttle:30,1,review-cover-letter')->name('applications.drafts.cover-letter');`.
- Behaviour: resolve the posting through `MatchingJobPostings::forUser` and the active complete profile, as `drafts` does. Response JSON:
    - not found or not eligible: `{ state: 'off', body: null }`;
    - not regenerating, cached letter: `{ state: 'ready', body }`, with a cache-hit usage row and no job;
    - quota exhausted: `{ state: 'quota', body: null }`, with no job;
    - otherwise dispatch `GenerateCoverLetterJob` and return `{ state: 'writing', body: null }`.
- `app/Client/AccountStatusPresenter.php`: add `'aiWriting' => null` when the plan has no capability, otherwise `['used' => int, 'limit' => int]`.

**Steps.**

1. Presenter and account status.
2. Request, controller, route.

**Done when.**

- For a Pro user, `ai` is null and the endpoint returns 403; for a Max user, a draft is `idle`, a body without the slot or an empty manual letter is `off`, and a cached letter is `ready` with the letter in the body.
- `drafts` dispatches no job (`Queue::fake`); the endpoint dispatches exactly one; at the limit it dispatches none.
- A generate call for a job outside the user's pool returns `off`.
- `composer types:check` passes; `php artisan test --compact --filter=Outreach` passes.

**Not in this phase.** React.

### Phase 9 — The frontend knows the new contract

Status: PENDING
Role: inertia-frontend · Depends on: 8 · Covers: P17, P21 · Size: S
Origin: P17, P21

**Goal.** Types, hook, realtime event and fixtures are ready for the screen.

**Gap -> desired.** No AI fields -> typed contract and working fixtures.

**Contract.**

- `resources/js/types/contracts.ts`: `ReviewDraft.ai: { state: 'idle' | 'ready' | 'quota' | 'off' } | null`; `AccountStatus.aiWriting: { used: number; limit: number } | null`; new `CoverLetterResult = { state: 'writing' | 'ready' | 'quota' | 'off'; body: string | null }`.
- `resources/js/types/realtime.ts` `UserChannelEvents`: `'cover_letter.updated': { jobId: number; state: 'ready' | 'manual'; reason: 'quota' | 'failed' | null; body: string | null; aiQuota: { used: number; limit: number } }`.
- `resources/js/data/endpoints.ts`: `generateCoverLetter: (): Endpoint => ReviewCoverLetterController()`, following the file's Wayfinder imports.
- New `resources/js/data/hooks/use-generate-cover-letter.ts`, same shape as `use-review-drafts.ts`: `useMutation<CoverLetterResult, ApiError, { jobId: number; regenerate?: boolean }>` with `fromSource({ real, fixture })`.
- `resources/js/data/realtime/handlers.ts` and `use-realtime-cache.ts`: on `cover_letter.updated`, update `aiWriting` in the cached account status from `aiQuota`. The review modal adds its own listener in Phase 10.
- Fixtures: `resources/js/data/fixtures/handlers/drafts.ts` sets `ai`; the fixture plan `max` returns `idle`, others `null`. The fixture generate handler returns `writing`, then emits `cover_letter.updated` through the dev emitter after a short delay, with a body that includes a sample letter in the draft's language. Fixture account status carries `aiWriting`.

**Steps.**

1. Types, endpoint, hook.
2. Realtime handler and fixtures.

**Done when.**

- `yarn run check` and `yarn types:check` pass, and existing screens behave the same.

**Not in this phase.** Visible UI.

### Phase 10 — The review screen shows, regenerates and protects the letter

Status: PENDING
Role: inertia-frontend · Depends on: 9 · Covers: P16, P17, P19, P21 · Size: M
Origin: P16 (D15), P17, P19, P21 (D16, D17)

**Goal.** A Max client sees the letter arrive, keeps full control and can regenerate.

**Gap -> desired.** Review shows only the manual letter -> the AI state per draft, in `resources/js/features/review/review-modal.tsx`, with a new `resources/js/features/review/ai-letter-bar.tsx`.

**Contract.**

- Session state per job id: `{ state: 'idle' | 'writing' | 'ready' | 'manual' | 'quota' | 'off', reason, aiBody?: string, baseBody: string }`, seeded from `draft.ai`. Nothing applies when `draft.ai` is null, and the screen stays as today.
- **Trigger (D15):** when the current draft changes, call generate for it and for the next draft when their state is `idle`, and mark them `writing`. Skipped and unreached drafts get no call. Each draft is requested once per session unless regenerated. Trigger from `useEffect` on `index`/`drafts`; handle `ready`, `quota` and `off` from the response.
- **Arrival:** listen with `useUserChannel` for `cover_letter.updated` and ignore other job ids.
    - `state = ready`: if there is no edit for that draft (`edits[jobId]` absent, or its body equals `baseBody`), the new body becomes the displayed body and `baseBody`. If edited, keep the edit, store `aiBody` and show "Use AI version" (D16), which replaces the whole body and clears the edit marker.
    - `state = manual`: reason `quota` shows the quota message; reason `failed` shows the failed message.
- **Safety net, not polling:** a draft in `writing` for 90 seconds becomes `manual`/`failed` locally, so a lost broadcast never leaves it stuck.
- **Sending:** Approve and Approve all use today's logic, with whatever is displayed. A draft still writing goes with the manual letter (P17); a letter arriving for a queued draft is ignored.
- **`ai-letter-bar.tsx`,** rendered below `BodyEditor` (D17):
    - `writing`: `Chip` plus "Writing your letter…", with `role="status"`;
    - `ready`, body not edited: `Chip` "AI-written" and a "Regenerate" button;
    - `ready`, edited: "Use AI version" when `aiBody` is present and "Regenerate"; no `AI-written` chip;
    - `quota`: message, with Regenerate disabled;
    - `manual` with reason `failed`: message and Regenerate;
    - `off`, `idle`, null: nothing.
- **Regenerate:** disabled when `aiWriting.used >= limit`, while writing or when the draft is locked; calls generate with `regenerate: true`; while editing is allowed, the same arrival rule applies.
- Copy in `lang/en.json` and `lang/pt.json`:
    - `review.ai.label`: "AI-written" / "Escrita por IA".
    - `review.ai.writing`: "Writing your letter…" / "Escrevendo sua carta…".
    - `review.ai.regenerate`: "Regenerate" / "Gerar de novo".
    - `review.ai.use`: "Use AI version" / "Usar versão da IA".
    - `review.ai.use_hint`: "This replaces your edited message." / "Isso substitui a mensagem que você editou."
    - `review.ai.quota`: "You've used this month's AI letters, so this draft uses your own letter." / "Você usou as cartas de IA deste mês, então este rascunho usa a sua carta."
    - `review.ai.failed`: "We couldn't write a letter this time, so this draft uses your own letter." / "Não conseguimos escrever uma carta desta vez, então este rascunho usa a sua carta."

**Steps.**

1. Session state, trigger and listener.
2. `ai-letter-bar.tsx` and copy.

**Done when.**

- With `VITE_USE_FIXTURES` and plan `max`: opening a session generates only the first two drafts; advancing generates the next; the letter appears inside the body with the chip; an edit is never overwritten and "Use AI version" replaces it; Regenerate works and is disabled at the limit; Approve while writing works.
- A Pro fixture user sees today's screen, unchanged.
- `yarn run check` and `yarn types:check` pass, with EN and PT copy present.

**Not in this phase.** Profile hint and usage display.

### Phase 11 — Clients see the slot hint and their usage

Status: PENDING
Role: inertia-frontend · Depends on: 9 · Covers: P10, P19 · Size: S
Origin: P10 "the profile screen shows a hint for AI-plan users"; P19 "the plans/account screen shows usage"

**Goal.** A Max client knows why no letter is written and how much quota is left.

**Gap -> desired.** No hint or usage -> both.

**Contract.**

- `resources/js/features/profiles/profile-form.tsx`: when `AccountStatus.aiWriting` is not null and the draft body lacks `{{ cover_letter }}` (any inner whitespace) or the letter is empty, show below the cover letter field `profiles.ai.hint`: "To get an AI-written letter for each job, write your cover letter here and keep {{ cover_letter }} in the message." / "Para receber uma carta escrita por IA em cada vaga, escreva sua carta aqui e mantenha {{ cover_letter }} na mensagem." Use the hook the screen family already uses for the account status; follow `plan-gate`/`new-matches-card` for how it is read.
- `resources/js/pages/plans.tsx`: when `aiWriting` is not null, show above the grid `plans.ai.usage`: ":used of :limit AI letters used this month" / ":used de :limit cartas de IA usadas neste mês".
- Fixtures already provide `aiWriting` (Phase 9).

**Steps.**

1. Hint.
2. Usage.

**Done when.**

- With fixtures and plan `max`: the hint appears and disappears as the body or letter changes; usage appears on `/plans`. Pro shows neither.
- `yarn run check` and `yarn types:check` pass.

**Not in this phase.** New behaviour.

### Phase 12 — Verification and report

Status: PENDING
Role: laravel-backend · Depends on: 1-11 · Covers: all · Size: S
Origin: spec x code gap (mandatory final phase)

**Goal.** Prove the feature and hand the owner a checklist.

**Gap -> desired.** Phases done individually -> an integrated check.

**Contract.**

- Full gate: `composer ci:check`.
- Forbidden patterns, in the files changed by this plan: `->poll(`, `wire:poll`, `$pollingInterval`, `refetchInterval`, Spanish copy, git write commands in scripts.
- Capability check: `grep` the AI code in `app/Ai`, `ReviewDraftPresenter` and `ReviewCoverLetterController` for `PlanKey::`. Expect no match.
- Privacy check: `grep` `Log::` in `GenerateCoverLetterJob` and `GenerateCoverLetter`; none may carry prompt or output text.
- Client exposure: draft bodies and `cover_letter.updated` carry no real job URL or recipient.
- Requirement-by-requirement walkthrough of P1-P22, each with its evidence file.
- Report these to the owner, without building them:
    - the privacy policy must name the AI provider (`landing-and-legal`, P22);
    - the landing page needs real copy for Max (`landing-and-legal`);
    - spec §2.3 is outdated: run `/update-feature-spec ai-cover-letter`;
    - `gpt-5-mini` was chosen without comparing real letters, and cost per 100 letters is still an estimate (O3 asked to record it).

**Steps.**

1. Run the gate and the greps.
2. Do the walkthrough and write the report.

**Done when.**

- `composer ci:check` passes, or each failure is reported with its output.
- The owner's manual checklist is delivered:
    - create the 3 Stripe prices and set `STRIPE_PRICE_MAX_*`; buy Max, in test mode;
    - in `/admin`, set a test user to Max;
    - with `OPENAI_API_KEY`, the `ai` queue worker and Reverb running, open a review session and read real letters in EN and PT;
    - edit a body before the letter arrives and confirm the edit stays and "Use AI version" works;
    - regenerate; reopen a draft and confirm no new usage counts;
    - set `PLAN_MAX_AI_MONTHLY_QUOTA=1` and confirm the quota message and the disabled button;
    - check `ai_usage_records` tokens and record the real cost per 100 letters;
    - send one with `OUTREACH_INTERCEPT_TO` set and confirm the final email.

**Not in this phase.** New behaviour.
