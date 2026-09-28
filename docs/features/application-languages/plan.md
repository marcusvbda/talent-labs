# Plan — application-languages: application profiles per language (CV, email, cover letter)

Source spec: `docs/features/application-languages/spec.md` · SHA-256 `dfda0bb46b0c3c53fd92240c4cd6bccf124d65bcd7f9e6cb75e54ed864f90e7e`
Product truth: the spec above (Part B + acceptance criteria). Screen contracts: `docs/features/client-app-screens/spec.md` (S5, S9 step 3) and `resources/js/types/contracts.ts`.
Run phases with `/execute-phases docs/features/application-languages/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                                            | Role             | Depends on      | Size | Status  |
| ----- | ---------------------------------------------------------------- | ---------------- | --------------- | ---- | ------- |
| 1     | `application_profiles` table, enum, model, policy, relations     | laravel-backend  | none            | M    | DONE    |
| 2     | Renderer: `cover_letter`, per-language defaults, blank lines     | laravel-backend  | 1               | S    | DONE    |
| 3     | Profiles endpoints: list, create, update, delete + unlock counts | laravel-backend  | 1, 2            | M    | DONE    |
| 4     | CV endpoints: upload (PDF-only, 5 MB) and delete                 | laravel-backend  | 3               | S    | DONE    |
| 5     | Template preview endpoint                                        | laravel-backend  | 2, 3            | S    | DONE    |
| 6     | Pool, eligibility, A6-1ccountStatus and Jobs lock use profiles   | laravel-backend  | 3               | M    | PENDING |
| 7     | Legacy `/app` Preferences: remove CV and template sections       | filament-admin   | 4               | S    | PENDING |
| 8     | Queue and send with the job-language profile                     | laravel-backend  | 2, 6, 7         | M    | PENDING |
| 9     | Remove the legacy CV/template columns from `job_preferences`     | laravel-backend  | 7, 8            | S    | PENDING |
| 10    | Account export includes application profiles                     | laravel-backend  | 9               | S    | PENDING |
| 11    | Frontend: profiles, profile mutations and preview go real        | inertia-frontend | 3, 4, 5         | M    | PENDING |
| 12    | Frontend: CV upload with real progress and server errors         | inertia-frontend | 4, 11           | S    | PENDING |
| 13    | Frontend: onboarding step 3 on real endpoints                    | inertia-frontend | 11              | S    | PENDING |
| 14    | Verification and report                                          | qa-tester        | 1–9, 11–13 (10) | S    | PENDING |

Owner-only steps (agents never run them): `php artisan migrate:fresh --seed` **after Phase 1** (the
`applications` create migration gains a column) and **again after Phase 9** (`job_preferences` loses
four columns). Phases 3–8 only need Phase 1's fresh run for runtime/manual checks; their deterministic
checks don't need it.

## Audit — 2026-09-28

| Check                                                       | Result                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| ----------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Prerequisite specs                                          | `client-core-wiring` and `preferences-clarity` are implemented (no plan files, but the code is there): `/internal/*` group in `routes/web.php`, `MatchingJobPostings::forUser(..., bool $withLanguageRule = true)`, `PreferencesPreviewPresenter` `byLanguage`, `applications.language` column (string 8, nullable), `AccountStatusPresenter` `profiles.activeLanguages` (temporary rule: legacy CV ⇒ `['en','pt']`), `JobsPayload` `lockedByLanguage: []`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| Migrations                                                  | All 17 create migrations `Ran` (batch 1). No `application_profiles` table. `job_preferences` still has `cv_path`, `cv_original_name`, `email_subject`, `email_body`. `applications` has `language` but no `application_profile_id`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| Migration ordering                                          | `applications` is `2026_09_24_100005`; `connected_integrations` is `…100004`. The new create migration must sort before `applications` → `2026_09_24_095959_create_application_profiles_table.php` (unique, after all `2026_09_23_*`).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| Enums                                                       | `App\Enums\ApplicationLanguage` does not exist.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| Legacy column readers (must all be switched before Phase 9) | `JobPreference` (model + `forUser` defaults), `CanSendApplications::hasCv/hasValidTemplate`, `QueueApplication`, `SendApplicationEmail`, `AccountStatusPresenter`, `Filament/App/Pages/Preferences.php` (CV + template sections, preview, download, `ownsCvPath`), `Filament/App/Pages/Jobs.php` (send modal subject + attachment name), `Http/Resources/Client/ApplicationDetailResource.php` (`cvFileName`), `Client/Internal/ApplicationsController::show` (eager-loads `user.jobPreference`), `Client/Internal/AccountExportController` (3 keys), `ApplicationTemplateRenderer::DEFAULT_SUBJECT/DEFAULT_BODY`. Not listed in spec B.1 but found by grep.                                                                                                                                                                                                                                                                                             |
| `ApplicationTemplateRenderer`                               | `ALLOWED_VARIABLES` lacks `cover_letter`; `variablesFor(User, JobPosting)`; no blank-line collapse; `DEFAULT_BODY` has the stray `)`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| CV storage                                                  | `local` disk root `storage/app/private`, `serve => true` (Laravel only serves it through signed temporary URLs; nothing issues them for CVs). `DeleteClientAccount` deletes `cvs/{userId}` recursively — covers the new `{language}` subfolders.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| PHP upload limits (CLI)                                     | `upload_max_filesize=2M`, `post_max_size=8M` → PDFs between 2 and 5 MB fail before Laravel validation. See D2. Web-server PHP not checked.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| JSON resources                                              | `JsonResource::withoutWrapping()` in `AppServiceProvider` — resources return bare objects.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| Frontend                                                    | S5 (`features/profiles/*`, `pages/profiles.tsx`), onboarding step 3 (`features/onboarding/step-profile.tsx`) and the Jobs notice (`pages/jobs.tsx` → `jobs.locked.*`) already exist on fixtures. Hooks `use-profiles.ts`, `use-cv.ts`, `use-template-preview.ts` have no `real` source; `endpoints.ts` has hand-written placeholders for the profile routes. `invalidateAfterProfileChange` invalidates profiles, account status, jobs — **not** dashboard (spec B.5 wants dashboard too). `FileDrop` accepts `progress?: number`; `cv-card.tsx` passes `0` while pending. `apiFetch` supports `FormData` but has no progress. `useDefaultTemplates` returns empty strings in real mode, and `step-profile.tsx` `onCreate` saves the draft right after creating → would PUT an empty subject/body (422) against the real API. Fixture defaults claim to be "copied verbatim" from the PHP constants and fixture `MAX_COVER_LETTER` is 5000 (spec: 6000). |
| Contracts                                                   | `ApplicationProfile`, `ProfilesData`, `TemplatePreview`, `TemplateVariable` (already includes `cover_letter`), `AccountStatus.profiles`, `JobsPage.summary.lockedByLanguage` exist in `resources/js/types/contracts.ts` and match the spec.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| Wayfinder                                                   | Generated by the Vite plugin (`formVariants: true`); `resources/js/actions` is git-ignored. Regenerate with `php artisan wayfinder:generate --with-form` after adding controllers.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `.env` keys                                                 | `OUTREACH_INTERCEPT_TO` is in `.env.example` but not in `.env` (needed for the AC04 manual send). `VITE_USE_FIXTURES`, `FILESYSTEM_DISK` present.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| Tests                                                       | Only `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`; nothing references the removed columns.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| Commands that exist                                         | `composer lint:check`, `composer lint`, `composer types:check`, `composer test`, `composer ci:check`, `php artisan test --compact`, `yarn check`, `yarn check:fix`, `yarn types:check`, `yarn build`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| Dependencies                                                | None needed.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |

## Owner decisions

### D1 — What does the account export contain once CV/template leave `job_preferences`? — DECIDED: A

Owner decision (2026-09-28): **A** — add an `applicationProfiles` list to the export —
`[{ language, active, cvOriginalName, cvUploadedAt, emailSubject, emailBody, coverLetter }]`, ordered
en, pt (the CV file itself stays out, as today). Phase 10's contract below reflects this.

### D2 — PHP upload limit below the 5 MB CV limit

Blocks: nothing in code; only the manual 2–5 MB upload check in Phase 14 · Options: **A (recommended)**
the owner raises `upload_max_filesize` to at least `6M` (`post_max_size` is already `8M`) in the php.ini
the local web server uses; production is handled with `production-readiness`. **B** accept a 2 MB
effective limit locally. · Why: PHP drops larger files before Laravel sees them. Phase 4 maps that
failure to the 5 MB message either way, so the UI stays correct.

### Defaults applied (not blocking — say so if any is wrong)

- **I1** Preview "newest job of the client's pool in that language" uses the pool **without** the
  language rule (like `unlockCounts`). Otherwise a language with no complete profile would always get
  the sample job, which is exactly when the client is writing that profile.
- **I2** Fallback sample job: company `Acme Robotics`; EN title `Backend Engineer`, location `Remote`;
  PT title `Desenvolvedor Backend`, location `Remoto`; URL `https://example.com/jobs/backend-engineer`
  (tokenized, never shown).
- **I3** The legacy `/app` Jobs "Send to selected" modal previews the subject and attachment name with
  the active complete profile for the first selected job's language.
- **I4** `ApplicationDetail.cvFileName` comes from the application's own profile (null once that
  profile is deleted).
- **I5** `QueueApplication` serializes concurrent queueing by locking the `users` row (a client may no
  longer have a `job_preferences` row).
- **I6** An application whose profile is later deactivated still sends; only a deleted profile or a
  missing CV fails it (spec B.4).
- **I7** Fixture mode keeps parity: fixture default templates switch to the B.3 texts and the fixture
  cover-letter max becomes 6000.

## Global constraints (every phase)

- `CLAUDE.md` hard rules: **no git writes**; no destructive DB commands (agents only run forward
  `php artisan migrate`; the owner runs `migrate:fresh --seed`); no new/removed/upgraded packages; **no
  new or modified tests**; everything in English; realtime via the driver, never `->poll()` /
  `wire:poll`; implement only the phase's scope and report extra ideas.
- Schema changes go into **create migrations** (spec Part 0.6): edit them and don't add alter migrations.
- Lang keys: EN and PT only (`lang/en.json`, `lang/pt.json`, `lang/{en,pt}/*`); never touch `lang/es.json`.
- CVs live only on the private `local` disk at `cvs/{userId}/{language}/<random>.pdf`. No route, resource
  or payload exposes a CV path or URL to the client; only `fileName`, `sizeBytes`, `uploadedAt`.
- Client-facing text never contains a job URL: rendered previews go through
  `ClientSafeText::tokenizeJobUrl()`.
- Every profile mutation (create, update, delete, CV upload/delete) dispatches `AccountStatusUpdated`
  through `DispatchesClientEvent::dispatchAccountStatusUpdated()` (model hooks, Phase 1).
- `resources/js/types/contracts.ts` is fixed: the backend matches it and the frontend doesn't change it.
- Frontend components use data hooks only; hooks pick sources with `fromSource({ real, fixture })`, and
  fixture mode (`VITE_USE_FIXTURES=true`) must keep working.
- No AI, no PDF generation, no per-job CV adaptation (spec Out of scope).
- Deterministic gate at the end of every phase: backend phases run `composer lint:check`,
  `composer types:check`, `php artisan test --compact`; frontend phases run `yarn check`,
  `yarn types:check`; phases touching both run all five.

## Acceptance-criteria coverage

| AC   | Phases           |
| ---- | ---------------- |
| AC01 | 1, 7, 9, 14      |
| AC02 | 3, 6, 11, 14     |
| AC03 | 1, 6, 14         |
| AC04 | 2, 8, 14         |
| AC05 | 2, 3, 5, 14      |
| AC06 | 4, 12, 14        |
| AC07 | 5, 14            |
| AC08 | 1, 6, 11, 13, 14 |

## Phases

### Phase 1 — `application_profiles` table, enum, model, policy, relations

Status: DONE
Evidence: `composer lint:check`, `composer types:check` (0 errors), `php artisan test --compact`
(2 tests, 2 assertions) all pass. `php artisan migrate:status` lists the new migration as `Ran`
(batch 2). Tinker verified `ApplicationProfile::activeCompleteLanguagesFor()` returns `[]` for an
incomplete profile, `missing()`/`isComplete()`/`isOwnCvPath()` behave per contract; test row deleted
afterwards. `code-reviewer`: APPROVED, no findings. Owner still needs to run
`php artisan migrate:fresh --seed` before `applications.application_profile_id` exists.
Role: laravel-backend · Depends on: none · Covers: AC01 (table), AC03 (completeness), AC08 (events) · Size: M
Spec: Part 0.1, 0.5, 0.6, B.2

**Goal.** Add the per-language profile data model without changing any behaviour yet.

**Contract.**

- New `database/migrations/2026_09_24_095959_create_application_profiles_table.php`:
  `id()`; `foreignId('user_id')->constrained()->cascadeOnDelete()`; `string('language', 8)`;
  `boolean('is_active')->default(true)`; `string('cv_path')->nullable()`;
  `string('cv_original_name')->nullable()`; `unsignedInteger('cv_size_bytes')->nullable()`;
  `timestamp('cv_uploaded_at')->nullable()`; `string('email_subject', 200)`; `text('email_body')`;
  `text('cover_letter')->nullable()`; `timestamps()`; `unique(['user_id', 'language'])`.
- Edit `2026_09_24_100005_create_applications_table.php`: add
  `foreignId('application_profile_id')->nullable()->constrained()->nullOnDelete()` (after `contact_id`).
  `language` stays as is.
- `App\Enums\ApplicationLanguage`: string-backed, cases `En = 'en'`, `Pt = 'pt'` (this order is the
  canonical en, pt order).
- `App\Models\ApplicationProfile`: `#[Fillable]` for every column except id/timestamps; casts
  `language` → `ApplicationLanguage`, `is_active` → bool, `cv_size_bytes` → int, `cv_uploaded_at` →
  `immutable_datetime`; `@property` docblock like `JobPreference`; `user(): BelongsTo`. Uses
  `DispatchesClientEvent`; `booted()` registers `saved` and `deleted` hooks that call
  `static::dispatchAccountStatusUpdated($profile->user_id)`.
    - `public static function isOwnCvPath(string $path, int $userId): bool`: same rule as
      `SendApplicationEmail::isOwnCvPath` today (prefix `cvs/{userId}/`, no `\`, no `\0`, no `..`
      segment; the language subfolder is allowed).
    - `hasOwnedCv(): bool`: `cv_path` filled, `isOwnCvPath` and `Storage::disk('local')->exists()`.
    - `missing(): list<'cv'|'subject'|'body'>`: `cv` when `! hasOwnedCv()`; `subject` when the trimmed
      subject is empty, longer than `CanSendApplications::MAX_SUBJECT_LENGTH` (200), or has
      `ApplicationTemplateRenderer::unknownVariables()`; `body` the same with `MAX_BODY_LENGTH` (5000).
    - `isComplete(): bool` → `missing() === []`.
    - `public static function activeCompleteLanguagesFor(User $user): list<string>`: the user's
      `is_active` profiles that are complete, as language values ordered en, pt. One query.
- `User::applicationProfiles(): HasMany`. `Application`: add `application_profile_id` to `#[Fillable]`
  and `@property`, add `applicationProfile(): BelongsTo` (+ `@property-read ApplicationProfile|null`).
- `App\Policies\ApplicationProfilePolicy`: `view`, `update`, `delete` → `$user->id === $profile->user_id`
  (auto-discovered like `JobPreferencePolicy`; verify discovery, register only if it isn't).

**Steps.**

1. Write the migration, then edit the `applications` create migration.
2. Enum, model, relations, policy.
3. Run `php artisan migrate` (forward only; it creates `application_profiles`). Don't run fresh.

**Done when.**

- `php artisan migrate:status` lists the new migration as `Ran`.
- `php artisan tinker --execute` can create an `ApplicationProfile` for a seed user, and
  `ApplicationProfile::activeCompleteLanguagesFor($user)` returns `[]` for a profile without a CV.
  Delete the row afterwards.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.
- Owner: run `php artisan migrate:fresh --seed` so `applications.application_profile_id` exists.

**Not in this phase.** Renderer, endpoints, any behaviour switch, removing `job_preferences` columns.

### Phase 2 — Renderer: `cover_letter`, per-language defaults, blank-line collapse

Status: DONE
Evidence: `composer lint:check`, `composer types:check` (0 errors), `php artisan test --compact`
(2/2) pass. Tinker verified EN/PT default bodies have no triple newline with an empty cover letter,
a non-empty cover letter is substituted (own variables included) and appears in the final body,
`containsCoverLetterVariable` and CRLF/blank-line collapsing behave per contract. `code-reviewer`:
APPROVED, verbatim template strings diffed char-for-char, no scope creep, 3 existing callers
unaffected (optional 3rd param).
Role: laravel-backend · Depends on: 1 · Covers: AC04 (templates), AC05 · Size: S
Spec: Part 0.3, B.3

**Goal.** The renderer knows the cover letter, the new defaults and the blank-line cleanup. Existing
callers keep working.

**Contract.** In `app/Outreach/Support/ApplicationTemplateRenderer.php`:

- `ALLOWED_VARIABLES = ['company', 'job_title', 'job_location', 'job_url', 'client_name', 'cover_letter']`
  (this order is also `ProfilesData.variables`).
- `variablesFor(User $user, JobPosting $posting, ?ApplicationProfile $profile = null): array` — builds the
  five existing variables, then
  `cover_letter = $profile === null ? '' : self::render((string) $profile->cover_letter, $base)` where
  `$base` holds the five variables (so a cover letter may use `{{ company }}` etc.). The parameter becomes
  required in Phase 8, when every caller has a profile.
- `render()`: after substitution, normalize `\r\n` to `\n`, collapse any run of 3+ newlines (lines that
  are empty or whitespace-only count as blank: `/\n(?:[ \t]*\n){2,}/` → `"\n\n"`), then `trim()`.
  An empty cover letter in the default body leaves exactly one blank line between paragraphs.
- `public static function defaultsFor(ApplicationLanguage $language): array{subject: string, body: string}`
  backed by constants. Texts **verbatim** from B.3:
    - EN subject `Application: {{ job_title }}`; EN body:
      `Hello {{ company }} team,\n\nI'm writing to apply for the {{ job_title }} position ({{ job_url }}).\n\n{{ cover_letter }}\n\nMy CV is attached. Thank you for your time.\n\nBest regards,\n{{ client_name }}`
    - PT subject `Candidatura: {{ job_title }}`; PT body:
      `Olá, equipe {{ company }},\n\nGostaria de me candidatar à vaga de {{ job_title }} ({{ job_url }}).\n\n{{ cover_letter }}\n\nMeu currículo está em anexo. Obrigado pelo seu tempo.\n\nAtenciosamente,\n{{ client_name }}`
- `public static function containsCoverLetterVariable(string $text): bool` (any inner whitespace,
  same pattern as `unknownVariables`). Phase 3 uses it for validation.
- Keep `DEFAULT_SUBJECT` / `DEFAULT_BODY` for now (`JobPreference::forUser` still uses them). Phase 9
  removes them.

**Steps.** 1. Edit the renderer. 2. Check with tinker that the default EN body, rendered with an
empty cover letter, has no `\n\n\n`.

**Done when.**

- Tinker: rendering `defaultsFor(En)['body']` with an empty cover letter gives no triple newline, and with
  cover letter `I love {{ company }}.` it shows the company name.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** Validation rules (Phase 3), making `$profile` required (Phase 8).

### Phase 3 — Profiles endpoints: list, create, update, delete + unlock counts

Status: DONE
Evidence: `composer lint:check`, `composer types:check` (0 errors), `php artisan test --compact`
(2/2) pass. `route:list --path=internal/profiles` shows all 4 routes. Verified end-to-end through
the real HTTP kernel as a seed client: POST pt → 201 with PT defaults; duplicate POST pt → 409;
POST es → 422; PUT with `{{ cover_letter }}` in coverLetter → 422; PUT with unknown vars in
subject/body → 422 on both fields; valid PUT → 200 with `active:false`, `coverLetter:""`; PUT to
invalid language segment → 404 (enum binding); GET → profiles ordered en,pt, 6 variables, numeric
unlockCounts; DELETE → 204, repeat DELETE → 404. All test rows cleaned up (0 leftover). `code-reviewer`:
APPROVED — no cross-user leak path, ownership scoped via `$user->applicationProfiles()`, no manual
event dispatch (relies on Phase 1 hooks), no scope creep.
Role: laravel-backend · Depends on: 1, 2 · Covers: AC02 (counts), AC05 (rejection) · Size: M
Spec: B.2, B.5 (`GET/POST/PUT/DELETE /internal/profiles`)

**Goal.** A client can manage their EN/PT profiles over `/internal`, and `ProfilesData` reports how many
jobs each language would unlock.

**Contract.**

- Routes inside the existing `internal.` group in `routes/web.php`; `{language}` is the
  `ApplicationLanguage` enum (implicit enum binding, invalid values give 404):
    - `GET /internal/profiles` → `ProfilesController@index` (`internal.profiles.index`)
    - `POST /internal/profiles` → `@store` (`internal.profiles.store`)
    - `PUT /internal/profiles/{language}` → `@update` (`internal.profiles.update`)
    - `DELETE /internal/profiles/{language}` → `@destroy` (`internal.profiles.destroy`)
- `App\Http\Controllers\Client\Internal\ProfilesController`. The profile is looked up as
  `$user->applicationProfiles()->where('language', $language)->firstOrFail()`, then
  `Gate::authorize('update'|'delete', $profile)`.
- `App\Client\LanguageUnlockCounts::forUser(User $user, ?PreferenceCriteria $criteria = null): array{en: int, pt: int}`:
  `count(distinct job_postings.company_id)` per `job_posting_profiles.language` over
  `MatchingJobPostings::forUser($user, $criteria, withLanguageRule: false)` limited to `en`/`pt`. Same
  query as today's `PreferencesPreviewPresenter` `byLanguage`; `null` criteria means saved preferences.
- `App\Client\ProfilesDataPresenter::forUser(User): array` → `ProfilesData`:
  `profiles` = created profiles ordered en, pt, as `ApplicationProfileResource`;
  `variables` = `ApplicationTemplateRenderer::ALLOWED_VARIABLES`; `unlockCounts` = `LanguageUnlockCounts`.
- `App\Http\Resources\Client\ApplicationProfileResource` → `ApplicationProfile`:
  `language`, `active` (`is_active`), `cv` = `hasOwnedCv()` ? `{ fileName: cv_original_name ?? 'cv.pdf', sizeBytes: cv_size_bytes ?? 0, uploadedAt: cv_uploaded_at ISO-8601 }` : `null`,
  `emailSubject`, `emailBody`, `coverLetter` (`''` when null), `complete` (`isComplete()`), `missing`.
- `store` — `StoreApplicationProfileRequest`: `language` required, `Rule::enum(ApplicationLanguage::class)`.
  If the profile exists, abort **409** with `This language already has a profile.` (also catch
  `UniqueConstraintViolationException` → 409). Otherwise create it with `defaultsFor()` subject/body,
  `cover_letter` null, `is_active` true. Returns the resource with **201**.
- `update` — `UpdateApplicationProfileRequest` (camelCase body):
    - `emailSubject`: required, string, max 200, no unknown variables
    - `emailBody`: required, string, max 5000, no unknown variables
    - `coverLetter`: nullable, string, max 6000, no unknown variables, must not contain `{{ cover_letter }}`
    - `active`: required, boolean

    Messages: `Unknown variable {{ <name> }}.` and `The cover letter cannot contain {{ cover_letter }}.`
    An empty cover letter is stored as null. Returns the resource.

- `destroy` — deletes the stored CV file (only if `isOwnCvPath`), then the row
  (`applications.application_profile_id` becomes null by FK). Returns **204**.
- Events come from the Phase 1 model hooks. Nothing here dispatches by hand.

**Steps.** 1. `LanguageUnlockCounts` + presenter + resource. 2. Requests. 3. Controller + routes. 4. `php artisan wayfinder:generate --with-form`. 5. Check with curl/tinker as a seed client.

**Done when.**

- `php artisan route:list --path=internal/profiles` shows the four routes.
- As a seed client: `POST {language:"pt"}` → 201 with the PT default subject; a second POST → 409;
  `PUT` with a `coverLetter` containing `{{ cover_letter }}` → 422 on `coverLetter`; `PUT` with
  `{{ foo }}` in the subject → 422; `GET` returns `profiles` ordered en, pt, 6 `variables` and numeric
  `unlockCounts.en/pt`; `DELETE` → 204.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** CV routes (4), preview (5), pool/eligibility changes (6), frontend (11).

### Phase 4 — CV endpoints: upload (PDF-only, 5 MB) and delete

Status: DONE
Evidence: `composer lint:check`, `composer types:check` (0 errors), `php artisan test --compact`
(2/2) pass. Verified through the real HTTP kernel: valid PDF upload → 200 with sanitized fileName
(path traversal/control chars/quotes stripped); renamed .txt and a file with a sniffed-but-wrong
header → 422 "The CV must be a PDF file."; >5MB and PHP UPLOAD*ERR_INI_SIZE → 422 "The CV must not
be larger than 5 MB." (bail + max-before-mimetypes ordering makes size win when both fail); file
lands at storage/app/private/cvs/{id}/{lang}/<random>.pdf; replace deletes the old file only after
save succeeds; DELETE removes the file and nulls all four columns, returns `cv: null`. Cleanup left
no test rows/files. `code-reviewer`: APPROVED — ownership double-scoped (query + policy), no route
serves the file, magic-byte read is bounded. Local PHP `upload_max_filesize=2M` remains the known
D2 environment gap, not addressed here.
Role: laravel-backend · Depends on: 3 · Covers: AC06 · Size: S
Spec: B.2 (`cv*\*` columns), B.5 (`POST/DELETE /internal/profiles/{language}/cv`)

**Goal.** Each profile has its own private PDF CV. It is validated by MIME type and by magic bytes.

**Contract.**

- Routes in the `internal.` group: `POST /internal/profiles/{language}/cv` →
  `ProfileCvController@store` (`internal.profiles.cv.store`); `DELETE …/cv` → `@destroy`
  (`internal.profiles.cv.destroy`). Lookup and `Gate::authorize('update', $profile)` as in Phase 3.
- `UploadCvRequest`: `cv` → `required`, `file`, `mimetypes:application/pdf`, `max:5120`, plus a closure
  that reads the first 5 bytes of the uploaded file and fails unless they are exactly `%PDF-`.
  Messages: MIME, header and `file` failures → `The CV must be a PDF file.`; `max` and `uploaded`
  (PHP limit, D2) → `The CV must not be larger than 5 MB.`
- `store`: `storeAs('cvs/{userId}/{language}', Str::random(40).'.pdf', 'local')`. Then fill
  `cv_path`, `cv_original_name` (client name → basename, control characters, quotes and backslashes
  stripped, max 255, fallback `cv.pdf`), `cv_size_bytes`, `cv_uploaded_at = now()` and save. **After**
  the save, delete the previous file if it existed, was `isOwnCvPath`, and differs from the new one.
  Returns `ApplicationProfileResource`.
- `destroy`: delete the file (if `isOwnCvPath`), null the four `cv_*` columns, save, return the resource.
- No route serves or downloads the file.

**Steps.** 1. Request, controller, routes. 2. `php artisan wayfinder:generate --with-form`. 3. Check with curl (`-F cv=@file`).

**Done when.**

- A real PDF → 200, the file exists under `storage/app/private/cvs/{id}/{lang}/` with a random name, and
  `cv` is filled in the response.
- A `.txt` renamed to `.pdf` → 422 `cv`; a file >5 MB → 422 `cv` with the 5 MB message.
- Replacing the CV removes the old file from disk; `DELETE …/cv` removes the file and returns `cv: null`.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** Upload progress UI (12), sending with the CV (8).

### Phase 5 — Template preview endpoint

Status: DONE
Evidence: `composer lint:check`, `composer types:check` (0 errors), `php artisan test --compact`
(2/2) pass. `route:list` shows the new throttled preview route. Verified through the real HTTP
kernel: EN default body → contains `{{ job_url }}`, no `http`, no `\n\n\n`; with no pool job,
`sampleJob.company = "Acme Robotics"` and PT title = "Desenvolvedor Backend"; cover letter with a
URL/email/`{{ company }}` → redacted to `[link]`/`[email]`/substituted; unknown variables in a draft
render literally (no rejection, by design); invalid language → 404; 61 requests → 429 on the 61st,
independent throttle key from `preferences-preview`. No DB rows left over. `code-reviewer`:
APPROVED — no leak path for the real job URL, fallback `$posting->company` access is safe (PHP
warning-not-error on null, pre-existing pattern, non-blocking suggestion noted for a future
cleanup unrelated to this phase).
Role: laravel-backend · Depends on: 2, 3 · Covers: AC05, AC07 · Size: S
Spec: B.5 (`POST /internal/profiles/{language}/preview`), defaults I1–I2

**Goal.** A live preview of a draft subject/body/cover letter against a real pool job (or a localized
sample). The job URL never appears in it.

**Contract.**

- Route in the `internal.` group:
  `POST /internal/profiles/{language}/preview` → `ProfilePreviewController` (invokable),
  `->middleware('throttle:60,1,profiles-preview')`, name `internal.profiles.preview`. It works **whether
  or not** a profile exists for that language (onboarding previews before creating one).
- `PreviewProfileRequest`: `subject` nullable string max 200; `body` nullable string max 5000;
  `coverLetter` nullable string max 6000.
- `App\Client\TemplatePreviewPresenter::forUser(User, ApplicationLanguage, string $subject, string $body, string $coverLetter): array` → `TemplatePreview`:
    - Job = newest posting (`first_seen_at` desc, `id` desc) of
      `MatchingJobPostings::forUser($user, withLanguageRule: false)` with
      `job_posting_profiles.language = $language` (I1), loaded with `company`.
    - Fallback = unsaved `JobPosting` (I2): `company_name` `Acme Robotics`, `is_remote` true,
      `url` `https://example.com/jobs/backend-engineer`, EN title `Backend Engineer` / location `Remote`,
      PT title `Desenvolvedor Backend` / location `Remoto`.
    - Draft profile = unsaved `ApplicationProfile` filled with the language and `cover_letter`.
      Variables = `ApplicationTemplateRenderer::variablesFor($user, $posting, $draft)`.
    - `subject` / `body` = `ClientSafeText::tokenizeJobUrl(render(...), $posting->url)`;
      `sampleJob = { company, title }`.

**Steps.** Presenter, request, controller, route, `php artisan wayfinder:generate --with-form`, curl check.

**Done when.**

- Preview with the default EN body returns a body that contains the literal `{{ job_url }}`, no `http`
  URL, and no `\n\n\n`. With no pool job it returns `sampleJob.company = "Acme Robotics"`, and the PT
  sample title is used for `pt`.
- 61 calls within a minute → 429.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** Frontend wiring (11).

### Phase 6 — Pool, eligibility, AccountStatus and Jobs lock use profiles

Status: PENDING
Role: laravel-backend · Depends on: 3 · Covers: AC02, AC03, AC08 · Size: M
Spec: Part 0.2, B.4 (pool, `CanSendApplications`), B.5 (`lockedByLanguage`, `AccountStatus`)

**Goal.** The language rule becomes "the job's language has an active complete profile", and every
status surface reflects it.

**Contract.**

- `MatchingJobPostings::forUser`: when `$withLanguageRule`, use
  `whereIn('job_posting_profiles.language', ApplicationProfile::activeCompleteLanguagesFor($user))`
  (an empty list gives an empty pool). Update the docblock sentence about languages.
- `PreferencesPreviewPresenter`: `byLanguage` comes from
  `LanguageUnlockCounts::forUser($user, $criteria)` (it still ignores the rule); `matchCount` keeps the rule.
- `CanSendApplications`: remove `hasCv()` and `hasValidTemplate()`. Where the CV and template checks
  were, add one check: `ApplicationProfile::activeCompleteLanguagesFor($user) === []` →
  unmet `'Create an application profile.'`. `MAX_SUBJECT_LENGTH` / `MAX_BODY_LENGTH` stay.
- `AccountStatusPresenter`: `$languages = ApplicationProfile::activeCompleteLanguagesFor($user)`; step
  `profile` done = `$languages !== []`; `profiles.activeLanguages = $languages`. Remove the "temporary
  rule" comment and the `CanSendApplications` dependency if it's no longer used.
- `JobsPayload`: `summary.lockedByLanguage` = for each of en, pt **not** in the active languages,
  `{ language, count: LanguageUnlockCounts::forUser($user)[language] }`, only when count > 0.
- Known transient until Phase 8: queueing still renders the legacy preference template and attaches the
  legacy CV, so a manual send in between needs both. Don't patch around it.

**Steps.** Edit the five files, then check with tinker/curl as a seed client with an EN profile only.

**Done when.**

- Client with only an active complete EN profile: `GET /internal/jobs` returns only `language: en` cards,
  and `summary.lockedByLanguage` has `pt` with the PT unlock count when it's > 0.
- Deactivating that profile or removing its CV empties the pool, sets `onboarding.steps[profile].done`
  to false and `activeLanguages` to `[]` (AC03).
- `GET /internal/account/status` changes as soon as the profile becomes complete (AC08, backend side).
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** `QueueApplication` / `SendApplicationEmail` (8).

### Phase 7 — Legacy `/app` Preferences: remove CV and template sections

Status: PENDING
Role: filament-admin · Depends on: 4 · Covers: AC01 (legacy pages load) · Size: S
Spec: B.4 (last bullet)

**Goal.** The legacy Filament page keeps only the Gmail section.

**Contract.** In `app/Filament/App/Pages/Preferences.php`, remove: the `CV` and `Email template`
sections, `mount()` form fill of those fields, the `save()` action/method, the `previewAction()`,
`downloadCv()`, `ownsCvPath()`, `templateRule()`, `variablesHelp()`, `allowedList()`, the form footer
that submits them, and the imports they leave unused. Keep the `Gmail` section and its
connect/reconnect/disconnect actions exactly as they are. If the page no longer needs a form at all,
render the Gmail section as a plain schema. Keep the slug, title, navigation label, sort and icon.

**Steps.** Edit the page, then load `/app/preferences` as a seed client.

**Done when.**

- `/app/preferences` loads with only the Gmail section; connect/disconnect still work.
- `grep -n "cv_path\|email_subject\|email_body\|ApplicationTemplateRenderer" app/Filament/App/Pages/Preferences.php` is empty.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** The legacy Jobs page modal (8), column removal (9).

### Phase 8 — Queue and send with the job-language profile

Status: PENDING
Role: laravel-backend · Depends on: 2, 6, 7 · Covers: AC04 · Size: M
Spec: Part 0.5, B.4 (`QueueApplication`, `SendApplicationEmail`), defaults I3–I6

**Goal.** Each application is rendered with the profile of the job's language, stores which profile and
language it used, and is sent with that profile's CV.

**Contract.**

- `ApplicationTemplateRenderer::variablesFor(User $user, JobPosting $posting, ApplicationProfile $profile)`
  — the profile becomes **required**.
- `QueueApplication`:
    - New `public const string REJECT_NO_PROFILE = "You have no application profile in this job's language.";`
    - Serialization lock: `User::query()->whereKey($user->id)->lockForUpdate()->first()` instead of the
      `JobPreference` lock (I5). Remove the `$preference === null` rejection and the `setRelation`.
    - Order: eligibility → already applied → recipient → **profile** → match. The profile is
      `$user->applicationProfiles()->where('language', $posting->profile?->language)->where('is_active', true)->first()`;
      null or `! isComplete()` → `REJECT_NO_PROFILE`.
    - Create the application with `application_profile_id = $profile->id`,
      `language = $profile->language->value`, and subject/body rendered from `$profile->email_subject` /
      `email_body` with `variablesFor($user, $posting, $profile)`.
    - Update the `REJECT_NOT_ELIGIBLE_PREFIX` docblock example (`Create an application profile.`).
- `SendApplicationEmail::beginAttempt`: eager-load `['company', 'contact', 'user.gmailIntegration', 'applicationProfile']`
  (drop `user.jobPreference`). CV reason (`'CV file is missing.'`) when the profile is null,
  `cv_path` is blank, `! ApplicationProfile::isOwnCvPath($path, $application->user_id)`, or the file
  doesn't exist. Attach `profile.cv_path` named `attachmentName(profile.cv_original_name)`. Delete the
  private `isOwnCvPath` (it moved to the model in Phase 1). A deactivated profile still sends (I6).
- `Filament/App/Pages/Jobs.php` send modal (I3): resolve the active complete profile for
  `$first->profile?->language`. Subject preview = rendered `email_subject` with that profile (null when
  none). `attachment` = that profile's `cv_original_name ?: 'CV'`.
- `ApplicationDetailResource`: `cvFileName = $application->applicationProfile?->cv_original_name` (I4).
  Update the docblock. `ApplicationsController::show` eager-loads `applicationProfile` instead of
  `user.jobPreference`.

**Steps.** Edit renderer, action, job, Jobs page, resource, controller. Run a local send with
`OUTREACH_INTERCEPT_TO` set (owner appends the key to `.env`; don't overwrite existing values).

**Done when.**

- Queuing a PT job for a client with EN+PT profiles stores `language = 'pt'` and the PT
  `application_profile_id`, and the subject/body are the rendered PT template. With only EN, a PT job is
  rejected with `REJECT_NO_PROFILE`.
- The intercepted email carries the PT CV file (`X-TalentLabs-Intercepted: true`).
- Deleting the profile before the worker runs → application `failed` with `CV file is missing.`
- `grep -rn "jobPreference" app/Outreach app/Http/Resources app/Filament/App/Pages/Jobs.php` shows no
  CV/template use.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** Dropping the legacy columns (9).

### Phase 9 — Remove the legacy CV/template columns from `job_preferences`

Status: PENDING
Role: laravel-backend · Depends on: 7, 8 · Covers: AC01 · Size: S
Spec: B.2 (`job_preferences` remove), Part 0.6

**Goal.** `job_preferences` holds only job preferences.

**Contract.**

- Edit `2026_09_24_100003_create_job_preferences_table.php`: remove `cv_path`, `cv_original_name`,
  `email_subject`, `email_body`.
- `JobPreference`: remove them from `@property` and `#[Fillable]`. `forUser()` becomes
  `firstOrCreate(['user_id' => $user->id])`. Drop the unused `ApplicationTemplateRenderer` import.
- `ApplicationTemplateRenderer`: delete `DEFAULT_SUBJECT` / `DEFAULT_BODY`.
- `AccountExportController`: remove `cvOriginalName`, `emailSubject`, `emailBody` from `preferences`
  (D1 decides what replaces them, Phase 10).

**Steps.** Edit the four files, then grep.

**Done when.**

- `grep -rn "cv_path\|cv_original_name\|email_subject\|email_body" app database resources/views routes`
  only matches `ApplicationProfile`-related code (model, migration, controllers, resource, Jobs page,
  `SendApplicationEmail`).
- `grep -rn "DEFAULT_SUBJECT\|DEFAULT_BODY\|hasCv\|hasValidTemplate" app` is empty.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.
- Owner: run `php artisan migrate:fresh --seed`; `/app/preferences`, `/app/jobs`, `/admin` and
  `/dashboard` load (AC01).

**Not in this phase.** Adding profiles to the export (10).

### Phase 10 — Account export includes application profiles

Status: PENDING
Role: laravel-backend · Depends on: 9 · Covers: — (data portability follow-up of B.2) · Size: S
Spec: B.2; D1 (decided: A)

**Goal.** The JSON export carries what the client wrote in their profiles.

**Contract.** `AccountExportController`: add a top-level `applicationProfiles` list, ordered en,
pt: `{ language, active, cvOriginalName, cvUploadedAt (ISO-8601|null), emailSubject, emailBody, coverLetter (''|text) }`.
No CV file content and no `cv_path`.

**Steps.** Edit the controller, then download `/internal/account/export` as a seed client.

**Done when.**

- The export shows both profiles with the fields above and no path.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** Anything else in the export.

### Phase 11 — Frontend: profiles, profile mutations and preview go real

Status: PENDING
Role: inertia-frontend · Depends on: 3, 4, 5 · Covers: AC02 (no-reload unlock), AC08 (live status) · Size: M
Spec: B.5 (last bullet), B.6; default I7

**Goal.** S5 reads and writes real data; fixture mode still works.

**Contract.**

- `resources/js/data/endpoints.ts`: replace the hand-written `profiles`, `createProfile`, `saveProfile`,
  `deleteProfile`, `uploadCv`, `deleteCv`, `templatePreview` entries with the Wayfinder helpers from
  `@/actions/App/Http/Controllers/Client/Internal/{ProfilesController,ProfileCvController,ProfilePreviewController}`.
  Keep the same signatures.
- `use-profiles.ts`: add `real` to `useProfiles` (GET → `ProfilesData`), `useCreateProfile`
  (POST `{ language }` → `ApplicationProfile`), `useSaveProfile` (PUT body
  `{ emailSubject: subject, emailBody: body, coverLetter, active }` → `ApplicationProfile`; on a 422,
  rethrow `ApiError` with `errors` keys mapped `emailSubject → subject`, `emailBody → body` so the UI keeps
  the fixture key names), `useDeleteProfile` (DELETE → `{}`).
  `invalidateAfterProfileChange` also invalidates `keys.dashboards()` (profiles, account status, jobs,
  dashboard).
- `use-template-preview.ts`: `real` = POST `{ subject, body, coverLetter }` to
  `endpoints.templatePreview(language)` → `TemplatePreview`. Keep `placeholderData: keepPreviousData`.
- `use-default-templates.ts` stays as is (the server prefills created profiles).
- Fixture parity (I7): `data/fixtures/catalog/profiles.ts` `defaultTemplates` = the B.3 EN/PT texts
  (see Phase 2), with the comment updated to point at `ApplicationTemplateRenderer::defaultsFor()`;
  `data/fixtures/handlers/profiles.ts` `MAX_COVER_LETTER = 6000`, and the fixture rejects a cover letter
  containing `{{ cover_letter }}` with the same message as Phase 3.
- The Jobs notice (`pages/jobs.tsx`, `jobs.locked.*`) needs no change. It reads
  `summary.lockedByLanguage` from the already-real `useJobs`.

**Steps.** `php artisan wayfinder:generate --with-form`, edit the files, then run the app with
`VITE_USE_FIXTURES` unset and again with it set to `true`.

**Done when.**

- Real mode: `/profiles` lists server profiles; creating PT shows the PT defaults; saving persists after a
  reload; the preview updates about 500 ms after typing and shows `{{ job_url }}` as the chip.
- After a PT profile becomes complete, `/jobs` shows PT jobs and the PT notice disappears without a page
  reload (AC02); the onboarding/AccountStatus indicator updates live (AC08).
- Fixture mode behaves as before.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** CV upload progress (12), onboarding step 3 (13).

### Phase 12 — Frontend: CV upload with real progress and server errors

Status: PENDING
Role: inertia-frontend · Depends on: 4, 11 · Covers: AC06 · Size: S
Spec: B.6

**Goal.** CV uploads show real byte progress and the server's validation message.

**Contract.**

- `resources/js/data/api.ts`: add
  `apiUpload<T>(url: string, form: FormData, onProgress: (percent: number) => void, method = 'post'): Promise<T>`
  built on `XMLHttpRequest`. Same headers as `apiFetch` (`Accept`, `X-Requested-With`, `X-XSRF-TOKEN`,
  credentials same-origin); `upload.onprogress` → `Math.round(loaded / total * 100)`. Non-2xx responses
  reject with `ApiError(status, message, errors)`, same parsing as `apiFetch`.
- `use-cv.ts`: `useUploadCv` `real` = `apiUpload(endpoints.uploadCv(language).url, form{cv: file}, setProgress)`.
  The hook returns the mutation plus `progress: number | undefined` (undefined when idle, reset on
  settle). `useDeleteCv` `real` = DELETE → `ApplicationProfile`. Fixture path unchanged (progress stays
  undefined → `0` while pending, as today).
- `features/profiles/cv-card.tsx`: `progress={upload.isPending ? (upload.progress ?? 0) : undefined}`;
  the error stays `upload.error?.errors?.cv?.[0] ?? upload.error?.message`.

**Steps.** Edit three files, then upload a real PDF, a renamed `.txt` and a >5 MB file in real mode.

**Done when.**

- The progress bar moves during a larger upload; the renamed file shows `The CV must be a PDF file.`;
  the oversize file shows `The CV must not be larger than 5 MB.`; replace/remove work and refresh the
  profile's `complete`/`missing`.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Onboarding (13).

### Phase 13 — Frontend: onboarding step 3 on real endpoints

Status: PENDING
Role: inertia-frontend · Depends on: 11 · Covers: AC08 · Size: S
Spec: B.5, B.6 (onboarding step 3); `client-app-screens` S9

**Goal.** Onboarding step 3 creates and completes the first profile against the real API.

**Contract.** In `features/onboarding/step-profile.tsx`:

- `onCreate`: `create.mutate({ language }, { onSuccess: (created) => { … } })`. The next draft keeps
  every non-empty field the user already typed and fills empty subject/body from `created.emailSubject` /
  `created.emailBody`. `setDraft(next)`, then `save.mutate({ language, ...next, active: true })`. It must
  never PUT an empty subject/body (real mode has empty `useDefaultTemplates`).
- When no profile exists yet and the draft subject/body are empty (real mode), the fields stay editable.
  After create they show the server defaults.
- Step completion stays driven by `AccountStatus.onboarding.steps[profile].done` (live via
  `account.updated` + the invalidation from Phase 11). No polling.

**Steps.** Edit the file, then walk onboarding with a fresh seed client in real mode.

**Done when.**

- Fresh client in real mode: step 3 → create EN → fields show EN defaults → upload CV → step marked
  done without a reload (AC08).
- Fixture mode behaves as before.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Any other onboarding step.

### Phase 14 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–9, 11–13 (10 if unblocked) · Covers: AC01–AC08 · Size: S
Spec: Acceptance criteria, Verification

**Goal.** Prove the ACs and hand the owner a manual checklist.

**Steps.**

1. Full gate: `composer lint:check`, `composer types:check`, `php artisan test --compact`, `yarn check`,
   `yarn types:check` (or `composer ci:check` once).
2. Forbidden-pattern greps (all must be empty):
   `grep -rn "->poll(\|wire:poll" app resources`;
   `grep -rn "cv_path\|email_subject\|email_body\|cv_original_name" app/Models/JobPreference.php database/migrations/2026_09_24_100003_create_job_preferences_table.php`;
   `grep -rn "DEFAULT_SUBJECT\|DEFAULT_BODY\|hasCv\|hasValidTemplate" app resources/js`;
   `git diff --stat -- lang/es.json` (no change); `git status --short tests composer.json composer.lock package.json yarn.lock` (no change).
3. Schema check (Boost `database-schema`): `application_profiles` matches B.2 (unique `user_id,language`),
   `applications.application_profile_id` nullable FK with `ON DELETE SET NULL`, and `job_preferences`
   without the four columns.
4. AC walkthrough, as a seed client, real mode:
    - AC01 app boots; `/app/preferences`, `/app/jobs`, `/admin`, `/dashboard`, `/profiles` load.
    - AC02 EN-only → `/jobs` only EN, notice `<n> Portuguese jobs are hidden…` with `n` =
      `unlockCounts.pt`; create and complete PT → PT jobs appear, no reload.
    - AC03 deactivate PT, or remove its CV → PT jobs gone, notice back.
    - AC04 queue a PT job with `OUTREACH_INTERCEPT_TO` set → PT subject/body, `language = pt`,
      `application_profile_id` set, intercepted email has the PT CV. Repeat for EN.
    - AC05 preview/sent body with `{{ cover_letter }}` → replaced; with an empty cover letter → no `\n\n\n`;
      saving a cover letter containing `{{ cover_letter }}` → 422.
    - AC06 renamed `.txt` → rejected; >5 MB → rejected; replace deletes the old file on disk; no route
      serves `cvs/…` (`php artisan route:list | grep -i cv` shows only the two internal routes); another
      client's `PUT/DELETE /internal/profiles/{lang}/cv` only ever reaches their own profile.
    - AC07 preview response contains no `http` job URL, only `{{ job_url }}`.
    - AC08 fresh client: onboarding step 3 completes when the first profile becomes complete;
      AccountStatus updates live (Reverb running).
5. Report: pass/fail per AC, command output summary, open decisions (D1/D2), extra ideas noticed
   (not built).

**Owner manual checklist.**

- Run `php artisan migrate:fresh --seed` after Phases 1 and 9 (agents can't).
- Append `OUTREACH_INTERCEPT_TO=<your address>` to `.env` if it's missing (don't overwrite).
- D2: raise `upload_max_filesize` to ≥ 6M locally, then upload a 3–4 MB PDF.
- Send one real intercepted email in EN and one in PT; open both attachments.
- Check the PT copy of the defaults and of the sample job (I2).

**Done when.** Every AC is marked pass (or fail with evidence), the gate is green, and the report is
delivered.

**Not in this phase.** Fixes. Failures go back to the owning phase.
