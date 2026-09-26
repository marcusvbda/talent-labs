# application-languages — application profiles per language (CV, email, cover letter)

> **Order:** 5 of 10. **Depends on:** `client-core-wiring` (job language
> detection) and `preferences-clarity` DONE.
> **Kind:** backend + wiring of screen S5 (Profiles), onboarding step 3 and
> the "locked by language" notice on Jobs.
>
> **How to run:** `/plan-spec docs/features/application-languages/spec.md`,
> then `/execute-phases`. No new dependencies.

## Part 0 — Context and decisions (owner, final)

Today each client has one CV and one email template (`job_preferences.cv_path`,
`email_subject`, `email_body`). With jobs in English, Portuguese and Spanish,
that sends the wrong-language CV. Decisions:

1. Each client has **one application profile per language** (`en`, `pt`,
   `es`), each with its own **CV (PDF)**, **email subject**, **email body**
   and **cover letter**. The client fills them in; nothing is generated.
2. **Pool rule:** a job appears (and can be sent) only if the client has an
   **active and complete** profile in the job's language. The UI shows how
   many jobs each missing language would unlock.
3. **Cover letter = text**, inserted into the email body wherever the
   template has `{{ cover_letter }}` (new template variable). No PDF
   generation, no new dependency. When the cover letter is empty, the
   renderer removes the blank lines it leaves.
4. **No AI** in this feature. A future "translate from my English profile"
   draft button was agreed as post-launch; out of scope here.
5. Each application stores which profile/language was used; the email is
   sent with that profile's CV.
6. Migrations: edit create migrations directly (development). `CLAUDE.md`
   rules apply.

## Part B — Product spec

### B.1 Files to read first

`app/Models/JobPreference.php` and its create migration,
`app/Outreach/Actions/{CanSendApplications,QueueApplication}.php`,
`app/Outreach/Jobs/SendApplicationEmail.php`,
`app/Outreach/Support/{ApplicationTemplateRenderer,ClientSafeText}.php`,
`app/Outreach/Queries/MatchingJobPostings.php`,
`app/Policies/JobPreferencePolicy.php`,
`app/Filament/App/Pages/Preferences.php` (legacy CV/template code, incl.
`ownsCvPath`), `client-app-screens/spec.md` S5, S9 step 3 and contracts
`ApplicationProfile`, `ProfilesData`, `TemplatePreview`,
`resources/js/features/profiles/*`.

### B.2 Data model

New create migration `application_profiles`:

| Column | Type |
|---|---|
| id | bigint |
| user_id | foreignId users, cascadeOnDelete |
| language | string(8) (`en`/`pt`/`es`, backed enum `App\Enums\ApplicationLanguage`) |
| is_active | boolean default true |
| cv_path | string nullable (private `local` disk, `cvs/{userId}/{language}/<random>.pdf`) |
| cv_original_name | string nullable |
| cv_size_bytes | unsignedInteger nullable |
| cv_uploaded_at | timestamp nullable |
| email_subject | string(200) |
| email_body | text |
| cover_letter | text nullable (max 6000 chars) |
| timestamps | |
| unique | (`user_id`, `language`) |

`job_preferences`: **remove** `cv_path`, `cv_original_name`,
`email_subject`, `email_body` (edit its create migration).

`applications` (edit create migration): add `application_profile_id`
foreignId nullable nullOnDelete; `language` already exists (from
`client-core-wiring`), now filled from the profile.

Model `ApplicationProfile` (casts, fillable, `user()`), `User::applicationProfiles()`,
policy `ApplicationProfilePolicy` (owner only). `complete` = CV file exists
and is owned + subject and body valid (length and no unknown variables,
same rules as `CanSendApplications` today).

### B.3 Templates

`ApplicationTemplateRenderer`:
- `ALLOWED_VARIABLES` adds `cover_letter`.
- `variablesFor(User, JobPosting, ApplicationProfile)` adds
  `cover_letter` (the profile's cover letter rendered with the other
  variables first, so a cover letter may use `{{ company }}` etc.; it may
  **not** contain `{{ cover_letter }}` itself — validation error).
- After rendering, collapse 3+ consecutive newlines to 2 and trim.
- Defaults per language (constants, used when a profile is created); fix the
  stray `)` of today's default body:

EN — subject `Application: {{ job_title }}`, body:
```
Hello {{ company }} team,

I'm writing to apply for the {{ job_title }} position ({{ job_url }}).

{{ cover_letter }}

My CV is attached. Thank you for your time.

Best regards,
{{ client_name }}
```
PT — subject `Candidatura: {{ job_title }}`, body:
```
Olá, equipe {{ company }},

Gostaria de me candidatar à vaga de {{ job_title }} ({{ job_url }}).

{{ cover_letter }}

Meu currículo está em anexo. Obrigado pelo seu tempo.

Atenciosamente,
{{ client_name }}
```
ES — subject `Candidatura: {{ job_title }}`, body:
```
Hola, equipo de {{ company }}:

Me gustaría postularme a la posición de {{ job_title }} ({{ job_url }}).

{{ cover_letter }}

Adjunto mi currículum. Gracias por su tiempo.

Saludos cordiales,
{{ client_name }}
```

### B.4 Pool, eligibility, queueing and sending

- `MatchingJobPostings::forUser`: add `profile.language IN (<active complete
  profile languages of the user>)`; if the user has none, the pool is empty.
  The preview (`preferences-clarity` B.4) keeps `byLanguage` ignoring this rule.
- `CanSendApplications`: replace the CV/template checks with "at least one
  active complete application profile" (`'Create an application profile.'`).
- `QueueApplication`: find the user's active complete profile for the
  posting's `profile.language`; none → reject `REJECT_NO_PROFILE`
  ("You have no application profile in this job's language."). Render
  subject/body with that profile; store `application_profile_id` and
  `language`.
- `SendApplicationEmail`: load the application's profile; the CV check and
  attachment use `profile.cv_path` / `cv_original_name`, with the ownership
  check updated to `cvs/{userId}/` prefix (language subfolder allowed). A
  deleted profile or missing CV → failed with the existing "CV file is
  missing." reason.
- Legacy Filament `/app` Preferences page: remove the CV and template
  sections (Gmail section stays until spec 6).

### B.5 Endpoints (wire S5, onboarding step 3, Jobs notice)

Under the `/internal` group (`client-core-wiring` B.6):

- `GET /internal/profiles` → `ProfilesData`: created profiles (ordered en,
  pt, es), `variables`, `unlockCounts` = per language, pool count using the
  saved preferences **ignoring** the language rule, limited to that language.
- `POST /internal/profiles` `{ language }` → creates with the language's
  default subject/body, empty cover letter, active → `ApplicationProfile`
  (409 if it exists).
- `PUT /internal/profiles/{language}` `{ emailSubject, emailBody,
  coverLetter, active }` → validated (subject 1–200, body 1–5000, cover
  letter ≤ 6000, only allowed variables, no `{{ cover_letter }}` inside the
  cover letter) → `ApplicationProfile`.
- `DELETE /internal/profiles/{language}` → deletes the profile and its CV
  file (applications keep their snapshot; `application_profile_id` nulls).
- `POST /internal/profiles/{language}/cv` multipart `cv`: PDF only
  (`mimetypes:application/pdf` **and** first bytes `%PDF-`), max 5 MB,
  stored under `cvs/{userId}/{language}/` with a random name, old file
  deleted after the new one is stored → `ApplicationProfile`.
  `DELETE …/cv` removes it.
- `POST /internal/profiles/{language}/preview` `{ subject, body,
  coverLetter }` → `TemplatePreview` rendered against the newest job of the
  client's pool in that language (fallback sample job "Acme Robotics",
  "Backend Engineer", localized), job URL replaced by the `{{ job_url }}`
  token (the UI shows the chip). `throttle:60,1`.
- `GET /internal/jobs` `summary.lockedByLanguage` = for each language
  without an active complete profile, the count from `unlockCounts`
  (> 0 only).
- `AccountStatus`: `onboarding.profile` = has ≥ 1 active complete profile;
  `profiles.activeLanguages` = those languages.
- Every mutation dispatches `AccountStatusUpdated`; the frontend invalidates
  `profiles`, `jobs.*`, `dashboard` on success.

### B.6 Frontend

Switch every S5 hook, onboarding step 3 and the Jobs notice to real
endpoints. CV upload shows real progress (XHR/`fetch` with upload progress
via `XMLHttpRequest` inside the mutation function) and server validation
errors.

## Acceptance criteria

- **AC01** `application_profiles` exists as B.2; `job_preferences` no longer
  has CV/template columns; the app boots and the legacy `/app` pages load.
- **AC02** A client with only an EN profile sees only EN jobs; Jobs shows
  "<n> Portuguese jobs are hidden…" with the correct count; creating and
  completing a PT profile makes those jobs appear without reload.
- **AC03** An inactive or incomplete profile does not unlock its language.
- **AC04** Queuing a PT job renders the PT subject/body, stores
  `language = pt` and `application_profile_id`, and the sent email carries
  the PT CV (verify with `OUTREACH_INTERCEPT_TO`).
- **AC05** `{{ cover_letter }}` is replaced by the rendered cover letter; an
  empty cover letter leaves no triple blank lines; a cover letter containing
  `{{ cover_letter }}` is rejected.
- **AC06** CV upload rejects non-PDF files (including renamed ones without
  the `%PDF-` header) and files over 5 MB; replacing deletes the old file;
  files are only readable by their owner.
- **AC07** Preview never contains a job URL; it shows the token.
- **AC08** Onboarding step 3 completes when the first profile becomes
  complete; `AccountStatus` reflects it live.

## Verification

`composer lint:check`, `composer types:check`, `yarn check`,
`yarn types:check`, existing tests; manual send with `OUTREACH_INTERCEPT_TO`
for EN and PT.

## Out of scope

AI translation of profiles (post-launch), PDF generation, per-job CV
adaptation (never, owner rule), sending modes (spec 6).

## Owner decisions

None open.
