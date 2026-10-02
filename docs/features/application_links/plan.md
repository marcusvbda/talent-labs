# Plan — application_links — links field on application profiles

Source spec: `docs/features/application_links/spec.md` · SHA-256 `e8b88742153c10949d82ecbabb82ab4e81646bae9732202e38441be7f3c62c20`
Product truth: `docs/features/application_links/spec.md` (Part 0 + Part B)
Run phases with `/execute-phases docs/features/application_links/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                         | Role             | Depends on | Size | Status  |
| ----- | --------------------------------------------- | ---------------- | ---------- | ---- | ------- |
| 1     | Store links and render `{{ links }}`          | laravel-backend  | none       | M    | DONE |
| 2     | Save and preview endpoints accept links       | laravel-backend  | 1          | S    | PENDING |
| 3     | Frontend contract, data hooks and fixtures    | inertia-frontend | 2          | M    | PENDING |
| 4     | Links field on the Profiles form              | inertia-frontend | 3          | M    | PENDING |
| 5     | Verification and report                       | qa-tester        | 1–4        | S    | PENDING |

## Audit — 2026-10-02

| Check                     | Result                                                                                                                                                                                                                                                                                                         |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Spec completeness         | Spec had no acceptance criteria and left validation, empty output, onboarding, preview/export and copy open. Owner delegated all of these to the recommendation on 2026-10-02 (see Owner decisions). ACs below are derived from Part 0/B plus those decisions.                                               |
| Table                     | `application_profiles` created by `2026_09_24_095959_create_application_profiles_table.php` (Ran). Has `cover_letter text nullable`; no links column.                                                                                                                                                          |
| Model                     | `app/Models/ApplicationProfile.php` — `#[Fillable([...])]` attribute, `casts()`, `@property` docblock.                                                                                                                                                                                                         |
| Renderer                  | `app/Outreach/Support/ApplicationTemplateRenderer.php` — `ALLOWED_VARIABLES` (`company, job_title, job_location, job_url, client_name, cover_letter`), `variablesFor()`, `render()` (collapses blank-line runs, trims), `html()` (`nl2br(e())`). Callers of `variablesFor()`: `QueueApplication` (send), `ReviewDraftPresenter` (review drafts), `TemplatePreviewPresenter` (preview). |
| Write path                | `PUT internal/profiles/{language}` → `ProfilesController::update` + `UpdateApplicationProfileRequest` (camelCase keys). Used by Profiles workspace **and** onboarding `step-profile.tsx`.                                                                                                                      |
| Preview path              | `POST internal/profiles/{language}/preview` → `ProfilePreviewController` + `PreviewProfileRequest` → `TemplatePreviewPresenter::forUser(..., string $coverLetter)`.                                                                                                                                           |
| Read contracts            | `ApplicationProfileResource` (`coverLetter`), `ProfilesDataPresenter` (`variables` = `ALLOWED_VARIABLES`), `AccountExportController` (`coverLetter`). TS: `resources/js/types/contracts.ts` (`ApplicationProfile`, `TemplateVariable`).                                                                         |
| Frontend                  | `features/profiles/profile-form.tsx` (cover letter `Field` + `Textarea`), `profiles-workspace.tsx` (debounced preview, save → toast on error), `variable-bar.tsx` (chips from `variables`). Hooks `data/hooks/use-profiles.ts`, `use-template-preview.ts`. UI kit: `components/ui/{field,input,button,icon-button}.tsx`, lucide icons. |
| Fixtures                  | Dev-only fixture source (`data/fixtures/*`, `fromSource({ real, fixture })`) mirrors the API: `handlers/profiles.ts` (`SaveProfileInput`, `saveProfile`, `previewTemplate`, `createProfile`), `catalog/profiles.ts` (`TEMPLATE_VARIABLES`, `PROFILES`), `handlers/drafts.ts` (draft variables). Must stay type-correct. |
| i18n                      | `lang/en.json`, `lang/pt.json` (EN/PT only). Existing keys `profiles.cover_letter.title/help`.                                                                                                                                                                                                                 |
| Tests                     | No test references `cover_letter`, `coverLetter` or `ApplicationTemplateRenderer` → nothing existing breaks from new variable. Test DB `talent_labs_testing` (pgsql) with `RefreshDatabase`, so an edited create migration is picked up by tests.                                                             |
| Commands that exist       | PHP: `vendor/bin/pint --dirty --format agent`, `composer lint:check`, `composer types:check`, `php artisan test --compact --filter=…`, `composer test`. Frontend: `yarn check`, `yarn check:fix`, `yarn types:check`, `yarn build`. Final: `composer ci:check`.                                                |
| Dependencies              | None needed (Laravel `url:http,https` rule, lucide `Plus`/`X` already available).                                                                                                                                                                                                                             |

## Owner decisions

All resolved — owner delegated to the recommendation on 2026-10-02.

### D1 — Link row validation — RESOLVED

Chosen: label and url both **required** per row (after trim); url must be `http`/`https` (`url:http,https`), max 2048 chars; label max 60 chars; at most **10** rows per profile; neither may contain `{{` or `}}`. Rows with both inputs blank are dropped silently on save. · Why: keeps the rendered line well-formed and blocks template injection.

### D2 — `{{ links }}` with no links — RESOLVED

Chosen: renders as an empty string; existing `render()` blank-line collapsing removes the gap. · Why: same behavior as an empty cover letter.

### D3 — Line format — RESOLVED

Chosen: exactly `<label> : <url>` (space, colon, space) per spec Part 0.3, one per line joined with `\n`, in stored order. HTML email gets line breaks via existing `html()` (`nl2br`).

### D4 — Onboarding — RESOLVED

Chosen: out of scope. Onboarding keeps no Links field and does **not** send `links` on save; the backend leaves stored links unchanged when the key is absent. · Why: spec B.1 names the Profiles screen only.

### D5 — Preview and export — RESOLVED

Chosen: live template preview renders the **unsaved draft** links (spec B.2). Account data export includes `links`. · Why: preview is in the spec; export must contain all stored user data.

### D6 — Default templates — RESOLVED

Chosen: built-in EN/PT default bodies are **not** changed; clients add `{{ links }}` themselves (help text explains, variable chip inserts it). · Why: spec Part 0.3 "Adding `{{ links }}` to the email body".

### D7 — Where `{{ links }}` is allowed — RESOLVED

Chosen: everywhere `{{ cover_letter }}` is allowed (subject, body, and inside the cover letter text itself). · Why: spec B.2 "available wherever `{{ cover_letter }}` is available".

### D8 — Migration style — RESOLVED

Chosen: add the column to the existing create migration (owner rule: create-only migrations during development). The executor never runs `migrate:fresh`; the **owner** runs it locally after Phase 1. Tests pick it up via `RefreshDatabase`.

## Global constraints (every phase)

- Git is read-only for subagents; only `/execute-phases` commits (one per phase, explicit paths, never push).
- Never run `migrate:fresh/refresh/reset/rollback`, `db:wipe`, DROP or TRUNCATE. The owner runs `migrate:fresh` for D8.
- No new composer/npm packages.
- Do not write or modify tests.
- Everything in English in code/comments; UI copy in `lang/en.json` and `lang/pt.json` only — no hardcoded UI text. No Spanish.
- No `->poll()` / `wire:poll` / polling anywhere.
- Do not change the built-in default templates (D6) or onboarding (D4).
- Do not edit `spec.md`.
- Match surrounding code style (final classes, static presenters, camelCase API keys, React Compiler — no speculative `useMemo`/`useCallback`).

## Acceptance-criteria coverage

ACs derived from spec Part 0/B and decisions D1–D8 (spec has no AC section).

- **AC01** The Profiles form shows a **Links** field directly below **Cover letter**, built with the same UI kit (field label/hint styles, `Input`, `Button`/`IconButton`, spacing); each row has a label input and a URL input; rows can be added and removed. (B.1)
- **AC02** Links are stored on the application profile, per language, in entry order; after save and reload the same rows appear in the same order; EN and PT profiles are independent. (B.1, Part 0.1–0.2)
- **AC03** Validation per D1: API returns 422 for a row missing label or url, a non-http(s) url, label > 60, url > 2048, > 10 rows, or `{{`/`}}` in either input; fully blank rows are dropped; the UI blocks Save while a row is incomplete or invalid and shows an inline message. (D1)
- **AC04** `links` is an allowed template variable: it appears in the variable bar and is accepted in subject, body and cover letter without an "Unknown variable" error. (B.2, D7)
- **AC05** `{{ links }}` renders one line per link as `label : url`, joined by line breaks, in stored order; with no links it renders empty and leaves no extra blank lines. (Part 0.3–0.4, B.2, D2, D3)
- **AC06** The rendered links appear in sent emails, review drafts and the live template preview (preview uses unsaved draft links). (B.2, D5)
- **AC07** Saving a profile without a `links` key (onboarding) leaves its stored links unchanged. (D4)
- **AC08** The account data export includes each profile's `links`. (D5)
- **AC09** All new UI strings exist in EN and PT; no hardcoded UI text. (CLAUDE.md)
- **AC10** Default templates are unchanged and profiles without links keep working (`links` is `[]` in the API). (D6)

| AC   | Phases  |
| ---- | ------- |
| AC01 | 4, 5    |
| AC02 | 1, 2, 4, 5 |
| AC03 | 2, 4, 5 |
| AC04 | 1, 3, 5 |
| AC05 | 1, 5    |
| AC06 | 1, 2, 3, 4, 5 |
| AC07 | 2, 3, 5 |
| AC08 | 1, 5    |
| AC09 | 4, 5    |
| AC10 | 1, 5    |

## Phases

### Phase 1 — Store links and render `{{ links }}`

Status: DONE
Role: laravel-backend · Depends on: none · Covers: AC02, AC04, AC05, AC06, AC08, AC10 · Size: M
Spec: Part 0, B.1, B.2 · Decisions D2, D3, D6, D7, D8

**Goal.** Profiles can hold an ordered list of links, `{{ links }}` is an allowed variable, and every renderer caller (send, review drafts, preview) substitutes it.

**Contract.**

- Migration `database/migrations/2026_09_24_095959_create_application_profiles_table.php`: add `$table->json('links')->nullable();` right after `cover_letter`. Do not create a new migration and do not run any migrate command (D8).
- Stored shape: `null` or a JSON list of `{"label": string, "url": string}` in entry order. Empty list is stored as `null`.
- `App\Models\ApplicationProfile`: add `links` to `#[Fillable]`; cast `'links' => 'array'`; docblock `@property list<array{label: string, url: string}>|null $links`.
- `App\Outreach\Support\ApplicationTemplateRenderer`:
  - `ALLOWED_VARIABLES` gains `'links'` (append after `'cover_letter'`).
  - New `public static function linksText(?array $links): string` — for each row, trim `label` and `url`; skip rows where either is empty; emit `"{$label} : {$url}"`; join with `"\n"`; return `''` when nothing remains. Docblock types `@param list<array{label?: mixed, url?: mixed}>|null $links`.
  - `variablesFor()`: compute `'links' => self::linksText($profile->links)` and include it in `$base` **before** rendering the cover letter, so `{{ links }}` also works inside the cover letter (D7). Return shape stays `array<string, string>`.
  - Default templates untouched (D6).
- `App\Http\Resources\Client\ApplicationProfileResource`: add `'links' => array_values($profile->links ?? [])` right after `coverLetter`, each item `['label' => string, 'url' => string]`.
- `App\Http\Controllers\Client\Internal\AccountExportController`: add `'links' => array_values($profile->links ?? [])` after `'coverLetter'` in each exported profile.
- `ProfilesDataPresenter` needs no edit (it returns `ALLOWED_VARIABLES`).

**Steps.**

1. Edit the create migration (column only).
2. Update model fillable/cast/docblock.
3. Add `linksText()` and wire `links` into `ALLOWED_VARIABLES` and `variablesFor()`.
4. Expose `links` in the resource and the account export.
5. `vendor/bin/pint --dirty --format agent`, `composer types:check`, `php artisan test --compact`.

**Done when.**

- `ApplicationTemplateRenderer::render("A\n\n{{ links }}\n\nB", ['links' => ApplicationTemplateRenderer::linksText([['label' => 'Linkedin', 'url' => 'https://x.test'], ['label' => 'Portfolio', 'url' => 'https://y.test']])])` returns `"A\n\nLinkedin : https://x.test\nPortfolio : https://y.test\n\nB"`, and with `linksText(null)` returns `"A\n\nB"` (check via `php artisan tinker --execute`).
- `ApplicationTemplateRenderer::unknownVariables('{{ links }}')` returns `[]`.
- `GET internal/profiles` returns `links: []` for existing profiles and `variables` includes `links`.
- `composer lint:check`, `composer types:check` and `php artisan test --compact` pass.

**Not in this phase.** Accepting links on save/preview (Phase 2); any frontend change (Phases 3–4).

**Evidence.** `vendor/bin/pint --dirty --format agent` → passed. `composer types:check` → `{"tool":"phpstan","result":"passed","errors":0}`. `composer lint:check` → `{"tool":"pint","result":"passed"}`. `php artisan test --compact` → 33 tests, 1017 assertions, all passing. Tinker: `linksText([...])` embedded in `render()` returns the exact expected two-line output; `linksText(null)` collapses to no blank gap; `unknownVariables('{{ links }}')` → `[]`. Deviation from literal contract: Resource/Export use `$profile->links ?? []` instead of `array_values($profile->links ?? [])` (PHPStan flagged the call as redundant on an already-list type) — reviewed and confirmed sound, cast `'array'` always decodes a JSON array literal to a zero-indexed list. `code-reviewer`: APPROVED, no findings.

### Phase 2 — Save and preview endpoints accept links

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: AC02, AC03 (API), AC06 (preview), AC07 · Size: S
Spec: B.1, B.2 · Decisions D1, D4, D5

**Goal.** The client can save links on a profile with D1 validation, onboarding saves without `links` leave them intact, and the preview renders draft links.

**Contract.**

- `App\Http\Requests\Client\UpdateApplicationProfileRequest::rules()` adds:
  - `'links' => ['sometimes', 'array', 'max:10']`
  - `'links.*' => ['array:label,url']`
  - `'links.*.label' => ['required', 'string', 'max:60', 'not_regex:/\{\{|\}\}/']`
  - `'links.*.url' => ['required', 'string', 'max:2048', 'url:http,https', 'not_regex:/\{\{|\}\}/']`
  (`TrimStrings`/`ConvertEmptyStringsToNull` already trim and null blank strings, so blank inputs fail `required`.)
- `ProfilesController::update`: only when `$request->exists('links')`, set `links` to the validated rows mapped to `['label' => $row['label'], 'url' => $row['url']]` with `array_values()`; store `null` when the list is empty. When the key is absent, do not touch `links` (AC07).
- `App\Http\Requests\Client\PreviewProfileRequest::rules()` adds (preview stays lenient, nothing rejected for being incomplete):
  - `'links' => ['nullable', 'array', 'max:10']`
  - `'links.*' => ['array:label,url']`
  - `'links.*.label' => ['nullable', 'string', 'max:60']`
  - `'links.*.url' => ['nullable', 'string', 'max:2048']`
- `App\Client\TemplatePreviewPresenter::forUser(User $user, ApplicationLanguage $language, string $subject, string $body, string $coverLetter, array $links = [])` — sets `$draftProfile->links = $links` (incomplete rows are skipped by `linksText()`). Docblock `@param list<array{label?: string|null, url?: string|null}> $links`.
- `ProfilePreviewController`: pass `(array) ($request->validated('links') ?? [])` as the new argument.

**Steps.**

1. Add the rules to both requests.
2. Update `ProfilesController::update` and the preview controller/presenter.
3. Pint, PHPStan, tests.

**Done when.**

- `PUT internal/profiles/en` with `links: [{label: "Linkedin", url: "https://x.test"}]` stores it; `GET internal/profiles` returns it in the same order.
- Same request with `url: "ftp://x"`, a missing label, 11 rows, a 61-char label or `label: "{{ company }}"` returns 422 with a `links.*` error key.
- `PUT` without `links` leaves stored links unchanged; `links: []` clears them (column `null`).
- `POST internal/profiles/en/preview` with body `{{ links }}` and draft links returns the `label : url` lines; a half-filled row is skipped, not rejected.
- `composer lint:check`, `composer types:check` and `php artisan test --compact` pass.

**Not in this phase.** Frontend types, hooks and UI.

### Phase 3 — Frontend contract, data hooks and fixtures

Status: PENDING
Role: inertia-frontend · Depends on: 2 · Covers: AC04 (types), AC06, AC07 · Size: M
Spec: B.1, B.2 · Decisions D4, D5

**Goal.** The TypeScript contract, API hooks and fixture source know about links, without any visible UI change yet; onboarding keeps compiling and never sends `links`.

**Contract.**

- `resources/js/types/contracts.ts`:
  - `export type ProfileLink = { label: string; url: string };`
  - `ApplicationProfile` gains `links: ProfileLink[];` after `coverLetter`.
  - `TemplateVariable` gains `| 'links'` after `'cover_letter'`.
- `resources/js/data/fixtures/handlers/profiles.ts`:
  - `SaveProfileInput` gains `links?: ProfileLink[]` (optional — onboarding omits it).
  - `saveProfile`: when `input.links` is defined, store it (drop rows where both label and url are blank after trim); otherwise keep the existing links.
  - `createProfile`: `links: []`.
  - `previewTemplate` input gains `links?: ProfileLink[]`; variables gain `links` rendered as rows with both fields non-empty, `label : url` joined by `\n` (same as backend `linksText`).
- `resources/js/data/fixtures/catalog/profiles.ts`: `TEMPLATE_VARIABLES` gains `'links'`; each `PROFILES` entry gains `links` (e.g. one fixture with `[{ label: 'LinkedIn', url: 'https://www.linkedin.com/in/example' }]`, others `[]`).
- `resources/js/data/fixtures/handlers/drafts.ts`: draft variables gain `links` from the profile, same formatting.
- `resources/js/data/hooks/use-profiles.ts` `useSaveProfile` real call: include `links: input.links` in the body only when `input.links !== undefined`.
- `resources/js/data/hooks/use-template-preview.ts`: `Input` gains `links?: ProfileLink[]`; real call sends `links: input.links ?? []`.
- `features/onboarding/step-profile.tsx` must compile unchanged (it passes no `links`). Do not edit it.

**Steps.**

1. Update contracts.
2. Update fixtures (handlers + catalog + drafts).
3. Update the two hooks.
4. `yarn check` and `yarn types:check`.

**Done when.**

- `yarn check` and `yarn types:check` pass.
- In the Profiles screen, the variable bar shows a `{{ links }}` chip (comes from the API/fixtures).
- Saving from onboarding sends no `links` key (inspect the hook body).

**Not in this phase.** The Links editor UI and copy (Phase 4).

### Phase 4 — Links field on the Profiles form

Status: PENDING
Role: inertia-frontend · Depends on: 3 · Covers: AC01, AC02, AC03 (UI), AC06 (preview), AC09 · Size: M
Spec: B.1, B.2 · Decisions D1, D5

**Goal.** Clients add, edit and remove link rows right below the cover letter, see them in the live preview, and save them.

**Contract.**

- New `resources/js/features/profiles/links-field.tsx` exporting `LinksField({ links, onChange }: { links: ProfileLink[]; onChange: (next: ProfileLink[]) => void })`:
  - Header: label `t('profiles.links.title')` with the same classes/structure as `Field`'s label, an "optional" marker like the cover letter (`Field` `optional` look), and hint `t('profiles.links.help')` styled like `Field`'s hint.
  - One row per link: `Input` for label (`aria-label={t('profiles.links.label')}`, `placeholder={t('profiles.links.label_placeholder')}`, `maxLength={60}`) and `Input` for url (`type="url"`, `inputMode="url"`, `aria-label={t('profiles.links.url')}`, `placeholder="https://"`, `maxLength={2048}`), plus an `IconButton` (lucide `X`, `label={t('profiles.links.remove')}`) that removes the row. Rows stack on mobile, label+url side by side from `sm:`.
  - An "Add link" `Button` (existing secondary/ghost variant used in the form, lucide `Plus`) appending `{ label: '', url: '' }`; disabled at 10 rows.
  - Row validity helper exported from the same file: `linkRowState(row): 'blank' | 'valid' | 'invalid'` — blank when both trimmed empty; valid when both non-empty, label ≤ 60, url ≤ 2048, url matches `/^https?:\/\/\S+$/i`, neither contains `{{` or `}}`; otherwise invalid. Invalid rows get `aria-invalid` on the offending input(s), and one message `t('profiles.links.invalid')` under the list (danger text style used elsewhere in the form/kit).
- `profile-form.tsx`:
  - `ProfileDraft` gains `links: ProfileLink[]`; `draftFrom` copies `profile.links`.
  - Render `<LinksField>` immediately after the cover letter `Field`, inside the same `gap-8` column.
  - Save button: `disabled={!dirty || draft.links.some((row) => linkRowState(row) === 'invalid')}`.
- `profiles-workspace.tsx`:
  - Debounce `draft.links` the same way as `coverLetter` (500 ms) and pass `links` to `useTemplatePreview`.
  - `onSave` sends `links: draft.links.filter((row) => linkRowState(row) !== 'blank')`.
- Copy — add to `lang/en.json` and `lang/pt.json` next to `profiles.cover_letter.*`:

  | Key                               | EN                                                                                                                | PT                                                                                                                         |
  | --------------------------------- | ----------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
  | `profiles.links.title`            | `Links`                                                                                                           | `Links`                                                                                                                    |
  | `profiles.links.help`             | `Inserted into the email where {{ links }} appears, one per line as label : url. Add that variable to the body to use it.` | `Inseridos no e-mail onde {{ links }} aparecer, um por linha no formato rótulo : url. Adicione essa variável ao corpo para usá-la.` |
  | `profiles.links.label`            | `Label`                                                                                                           | `Rótulo`                                                                                                                   |
  | `profiles.links.label_placeholder`| `e.g. LinkedIn`                                                                                                   | `ex.: LinkedIn`                                                                                                            |
  | `profiles.links.url`              | `URL`                                                                                                             | `URL`                                                                                                                      |
  | `profiles.links.add`              | `Add link`                                                                                                        | `Adicionar link`                                                                                                           |
  | `profiles.links.remove`           | `Remove link`                                                                                                     | `Remover link`                                                                                                             |
  | `profiles.links.invalid`          | `Each link needs a label and a URL starting with http:// or https://.`                                            | `Cada link precisa de um rótulo e de uma URL começando com http:// ou https://.`                                           |

**Steps.**

1. Build `links-field.tsx` from the existing UI kit (read `field.tsx`, `input.tsx`, `button.tsx`, `icon-button.tsx` first).
2. Wire it into `profile-form.tsx` and `profiles-workspace.tsx`.
3. Add the EN/PT strings.
4. `yarn check`, `yarn types:check`.

**Done when.**

- Links appears directly below Cover letter, visually consistent with the form; add/remove works; Add is disabled at 10 rows.
- A half-filled or non-http(s) row disables Save and shows the inline message; blank rows are dropped on save.
- With `{{ links }}` in the body, the preview shows `label : url` lines within ~500 ms of typing.
- After save and reload, rows come back in the same order; EN and PT tabs keep their own links.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Onboarding changes (D4); default template changes (D6).

### Phase 5 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–4 · Covers: AC01–AC10 · Size: S
Spec: all

**Goal.** Prove every AC holds and the full gate is green.

**Steps.**

1. Full gate: `composer ci:check` (plus `yarn build` since the bundle changed).
2. Smoke tests (tinker / HTTP against the dev app, never destructive):
   - Renderer: two links → two `label : url` lines in order; `null` → no blank gap (AC05).
   - `PUT internal/profiles/{en}` valid/invalid payloads from Phase 2 (AC02, AC03); without `links` key → unchanged (AC07).
   - Preview with draft links (AC06); `GET internal/profiles` → `links` + `variables` contains `links` (AC04, AC10).
   - Account export JSON includes `links` (AC08).
   - Review drafts / queued application body for a profile with links and `{{ links }}` in the body contains the lines (AC06).
3. Greps: no `->poll(` / `wire:poll` added; no hardcoded English UI strings in `links-field.tsx` (all via `t(...)`); every new `profiles.links.*` key exists in both `lang/en.json` and `lang/pt.json`; `defaultsFor()` bodies unchanged (`git diff`).
4. AC walkthrough AC01–AC10 with evidence per AC.
5. Owner manual checklist:
   - Run `php artisan migrate:fresh --seed` locally (D8 — owner only).
   - Profiles → EN: add two links, add `{{ links }}` to the body, check preview, save, reload.
   - Try a `ftp://` url and a row with only a label → Save disabled with the message.
   - PT tab: confirm its links are independent.
   - Send or review one application and confirm the email shows the lines.

**Done when.**

- `composer ci:check` and `yarn build` pass.
- Every AC has evidence; the report lists anything not verified.
