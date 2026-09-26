# preferences-clarity — clear matching rules for job preferences

> **Order:** 4 of 10. **Depends on:** `client-core-wiring` DONE.
> **Kind:** backend + wiring of screen S6 (Preferences) and onboarding step 4.
>
> **How to run:** `/plan-spec docs/features/preferences-clarity/spec.md`,
> then `/execute-phases`. No new dependencies.

## Part 0 — Context and decisions (owner, final)

Today (`App\Outreach\Queries\MatchingJobPostings::forUser`) values inside a
field are combined with OR and fields with AND, but the UI does not say so,
`keywords` searches the whole description with loose `ilike '%x%'`
(`react` matches "reactive", `java` matches "javascript"), seniority is
extracted by AI but never used, and `accepts_remote` does nothing when
`locations` is empty. The owner found the screen confusing.

Decisions:

1. **OR inside a field, AND between fields.** Shown in the UI as a rule
   banner and a live summary sentence (already built in S6).
2. **Seniority becomes a field**, matched against
   `job_posting_profiles.seniority`. Postings with `unknown` seniority
   always pass (`config('talent.matching.unknown_seniority_passes')`,
   default `true`).
3. **`keywords` is removed.** Replaced by **words to exclude**, matched
   against the job title, the normalized title and the stack.
4. **Remote mode** replaces `accepts_remote`: `remote_only`,
   `remote_or_locations` (default), `locations_only`.
5. **Whole-word matching** for titles, locations and exclude words
   (case-insensitive), not substrings.
6. Empty field = no filter on that field. Empty preferences = whole pool.
7. Migrations: edit `2026_09_24_100003_create_job_preferences_table.php`
   directly (owner runs `migrate:fresh --seed`). `CLAUDE.md` rules apply.

## Part B — Product spec

### B.1 Files to read first

`app/Outreach/Queries/MatchingJobPostings.php`,
`app/Outreach/Support/StackNormalizer.php`, `app/Models/JobPreference.php`,
the `job_preferences` create migration, `app/Filament/App/Pages/Preferences.php`
(legacy), `app/Ai/Agents/ExtractJobPostingProfile.php` (`SENIORITIES`),
`client-app-screens/spec.md` S6 and contracts `Preferences`,
`PreferencesPreview`, `resources/js/features/preferences/*`.

### B.2 Data model (`job_preferences`)

Final columns: `id`, `user_id` (unique), `titles` jsonb `[]`,
`seniorities` jsonb `[]` (values from `ExtractJobPostingProfile::SENIORITIES`
minus `unknown`), `stack` jsonb `[]`, `locations` jsonb `[]`,
`remote_mode` string default `remote_or_locations` (backed enum
`App\Enums\RemoteMode`), `exclude_words` jsonb `[]`, `saved_at` timestamp
nullable (first explicit save by the client; drives the onboarding step),
plus the CV/template columns that stay until `application-languages`
moves them (`cv_path`, `cv_original_name`, `email_subject`, `email_body`),
timestamps. **Removed:** `keywords`, `accepts_remote`, `auto_send_enabled`.
Update `JobPreference` casts/fillable/phpdoc.

### B.3 Matching semantics (`MatchingJobPostings`)

Refactor to `forUser(User $user, ?PreferenceCriteria $override = null)`
where `PreferenceCriteria` is a readonly DTO built from a `JobPreference`
or from a draft (preview). Base pool conditions stay exactly as today plus
the language rule from `client-core-wiring` B.7.

Word matching helper `WordPattern::toRegex(string $term): string`:
trim, collapse spaces, escape POSIX regex metacharacters, and wrap with
`\m`…`\M` (Postgres word boundaries) **only** on sides where the term starts
or ends with a letter or digit (so `C++`, `.NET`, `Node.js` still match).
Queries use `~*` (case-insensitive regex) with bindings, never string
interpolation.

| Field                               | Condition (applied only when the field is non-empty)                                            |
| ----------------------------------- | ----------------------------------------------------------------------------------------------- |
| `titles`                            | `(job_postings.title ~* p1 OR profile.normalized_title ~* p1) OR … pN`                          |
| `seniorities`                       | `profile.seniority IN (…)` `OR profile.seniority = 'unknown'` (when the config flag is true)    |
| `stack`                             | `profile.stack ?                                                                                | array[normalized…]`(as today,`StackNormalizer`) |
| `remote_mode = remote_only`         | `profile.is_remote = true` (locations ignored)                                                  |
| `remote_mode = remote_or_locations` | when locations non-empty: `profile.is_remote = true OR <location match>`; when empty: no filter |
| `remote_mode = locations_only`      | `<location match>` (locations required, validated)                                              |
| `exclude_words`                     | `NOT (title ~* w OR normalized_title ~* w OR profile.stack ? normalized(w))` for each word      |

`<location match>` = any location term matches `job_postings.location` or
any element of `profile.locations` (whole word, `~*`).

All fields combine with AND. Performance: the pool query must stay one SQL
statement; check `EXPLAIN` on the development database and add an index only
if a sequential scan on `job_posting_profiles` dominates (report it).

### B.4 Endpoints (wire S6 and onboarding step 4)

- `GET /internal/preferences` → `Preferences` (creates an empty row lazily,
  without setting `saved_at`).
- `PUT /internal/preferences` → validate (`UpdatePreferencesRequest`):
  arrays max 20 items, each item 1–60 chars, trimmed, unique
  case-insensitively; `seniorities` subset of allowed; `remote_mode` enum;
  `locations` required (min 1) when `remote_mode = locations_only`; `stack`
  normalized with `StackNormalizer`. Sets `saved_at` (first time and every
  save). Returns `Preferences`; dispatches `AccountStatusUpdated` (the
  onboarding step may change) and invalidation of the client's matches
  (frontend invalidates `jobs.*`, `dashboard` on success).
- `POST /internal/preferences/preview` (same validation, nothing saved,
  `throttle:60,1`) → `PreferencesPreview`: `matchCount` = pool count with the
  draft criteria (including the language rule), `byLanguage` = counts per
  language with the draft criteria **ignoring** the language rule.
- `AccountStatus.onboarding.preferences` = `saved_at IS NOT NULL`.

### B.5 Legacy Filament `/app` Preferences page

Remove its "Job preferences" section (the fields no longer exist). Keep
Gmail, CV and template sections working until they move in later specs.
Update the legacy Jobs page if it references removed fields.

### B.6 Frontend

Switch `usePreferences`, `useSavePreferences`, `usePreferencesPreview` to
real endpoints (Wayfinder). Validation errors (422) map to fields. The
summary sentence and counter already exist (S6); verify they match the
backend semantics exactly (e.g. "Remote only" ignores locations in the
sentence too).

## Acceptance criteria

- **AC01** `job_preferences` has the columns of B.2; removed columns are
  gone everywhere (grep: `keywords`, `accepts_remote`, `auto_send_enabled`
  only remain in unrelated contexts).
- **AC02** Two titles `frontend`, `backend` return postings matching either;
  adding seniority `senior` keeps only senior or unknown-level postings.
- **AC03** Title `react` does not match "Reactive Systems Engineer";
  `java` does not match "JavaScript Developer"; `C++` and `.NET` terms match
  postings that contain them.
- **AC04** `remote_only` returns only `is_remote = true` regardless of
  locations; `remote_or_locations` with empty locations applies no
  location filter; `locations_only` without locations is rejected with a
  field error.
- **AC05** An exclude word removes postings whose title, normalized title or
  stack contains it; it does not look at the description.
- **AC06** Preview returns the same count the pool returns after saving the
  same draft; `byLanguage` ignores the language rule.
- **AC07** Saving preferences completes the onboarding step and refreshes
  matches on Dashboard and Jobs without reload.
- **AC08** The legacy `/app` Preferences page still loads (without the
  removed section).

## Verification

`composer lint:check`, `composer types:check`, `yarn check`,
`yarn types:check`, existing tests. Manual: tinker checks of AC02–AC05 on
collected data, then the UI.

## Out of scope

Profiles and CV/template move (spec 5), sending modes (spec 6), AI-based
matching (never: matching stays SQL).

## Owner decisions

None open.
