# talent-labs — Application sending (spec addition)

> **Prompt to give the agent:** "Read this file end to end. New feature on
> top of already-DONE `job-collection-mvp`, `contact-discovery-mvp` and
> `verification-status` (and `more-aggregator-sources` / `aggregators-only`
> if built). Reuse the existing harness (`CLAUDE.md`, agents, skills,
> no-git rule, no-destructive-DB rule) — don't recreate it. Copy Part B into
> `docs/features/application-sending/spec.md`, then execute it with the
> `execute-feature` skill. Three new Composer dependencies are
> **pre-approved by the owner** for this feature: `laravel/ai`,
> `helgesverre/toon`, `league/oauth2-google` (match the constraints used in
> `github.com/marcusvbda/recruiter-labs` `composer.json`). Ask before any
> other new dependency."

## Part 0 — What this feature is

Until now the product collects jobs, finds a trustworthy email per company
(`outreach_status = verified`) and stops there. This round closes the loop:
**each client saves job preferences, sees only the jobs that match them,
and the system sends one application email — from the client's own Gmail,
with the client's CV attached — to each matching company, at most 10 per
day.**

### 0.1 Decisions already taken by the owner (don't reopen)

1. **Client = existing non-admin `User`.** Multi-client: every user has
   their own preferences, CV, email template and Gmail connection. No new
   `Client` model.
2. **Preferences are a saved profile, not a table filter.** The client
   edits them on a preferences page and saves. The client's job listing is
   _always_ computed from the saved preferences — there are no ad-hoc
   filters in the listing itself.
3. **Preference fields:** job title(s), description keywords, stack,
   location. (Exact semantics in B.3.)
4. **Job data needs AI extraction.** No adapter delivers a clean "stack"
   field and `location` strings are inconsistent across sources, so each
   eligible posting gets a one-time structured extraction with the Laravel
   AI SDK (`gpt-4o-mini`), following the owner's existing pattern in
   recruiter-labs — including its token-saving scheme (B.2).
5. **One CV per client**, a single fixed PDF attached to every email.
6. **Email template with variables** (company, job title, etc.), editable
   by the client.
7. **Recipient:** only `smtp_verified` contacts; if a company has several,
   pick **one** by fixed priority `careers → jobs → hr → talent →
recruiting → people`. Never CC, never multiple recipients, never
   separate emails to several aliases of the same company.
8. **Send dedup:** at most **one application per client per company**,
   ever — a new posting from a company the client already applied to does
   not trigger another email.
9. **Sending is done through the client's own Gmail** via Google OAuth
   (`gmail.send` scope), same integration pattern as recruiter-labs: a
   "Connect Gmail" button, full OAuth flow, integration active after it.
   This replaces the "own sending infra / SPF / DKIM" idea — Google signs
   and delivers, so there is no sending domain to configure.
10. **Daily limit: 10 emails per client per day** (free tier, the only tier
    for now), defined in **one constant** so it's trivial to change later.

### 0.2 Decisions this spec takes (flag in the report if you disagree with evidence)

- **Only postings of `verified` companies are extracted and shown to
  clients.** The product is outreach; a job the system can't send to is
  noise for the client, and skipping extraction for non-verified companies
  is the single biggest token saving available (most companies won't be
  `verified`).
- **Matching is deterministic, not AI.** AI runs **once per posting**
  (cost ∝ postings); matching then runs as plain SQL per client (cost ∝ 0).
  Running AI per _(client × posting)_ pair would scale badly and isn't
  needed for the four preference fields.
- **"Description" preference = keywords** searched in the posting's
  description text. It's not a free-text "about me" block (that would need
  per-pair AI matching — see above).
- **Auto-send only considers recent postings** (`first_seen_at` within the
  last 14 days, constant) so turning auto-send on doesn't blast 10/day into
  months-old jobs.
- **Contact emails become visible to the client only for their own sent
  applications** (it's in their Gmail "Sent" folder anyway). This lifts
  `contact-discovery-mvp` B.10's "never outside /admin" rule for that one
  case only.

### 0.3 Known risks to report on, not to solve

- **Google OAuth publishing status.** `gmail.send` is a _sensitive_ scope.
  While the Google Cloud OAuth app is in **Testing** mode, only listed test
  users can connect and **refresh tokens expire after 7 days** (clients
  will have to reconnect weekly — the reauthorization flow in B.5 handles
  this gracefully). Moving to production requires Google's app
  verification for the sensitive scope. Closed beta can live in Testing
  mode; this is an owner decision, not code.
- **Sender reputation is the client's.** Emails leave from the client's
  personal Gmail. 10/day + staggered sending (B.7) keeps it well under
  Gmail's own limits and away from bulk-sender patterns, but a client who
  gets reported as spam is affected personally. Worth stating in the
  preferences page copy (one sentence, B.8).
- **OpenAI cost** is platform-paid (one `OPENAI_API_KEY` in `.env`), not
  per-client.

---

## Part B — Product spec

### B.1 Pipeline overview

```
collection run ─► posting inserted ─► DiscoverContactsForPosting (existing)
                                              │
                         company outreach_status = verified ?
                                              │ yes
                                              ▼
                         ExtractJobPostingProfile (queue `ai`, once per posting)
                                              │
                                              ▼
                 posting now visible to every client whose preferences match
                                              │
         applications:dispatch (scheduler, every 15 min) / manual "Apply"
                                              │
                                              ▼
                 SendApplicationEmail (queue `outreach`) ─► Gmail API
```

### B.2 AI extraction of posting profiles

**Reference implementation (read these first, then adapt — don't copy
company-scoped/per-tenant-key parts):**
`github.com/marcusvbda/recruiter-labs`:

- `app/Ai/Agents/ExtractJobCriteria.php` — agent shape: `#[Provider(Lab::OpenAI)]`,
  `#[Model('gpt-4o-mini')]`, `HasStructuredOutput`, `CACHE_SCHEMA_VERSION`.
- `app/Ai/Concerns/BuildsCompactAgentContext.php` — TOON context,
  empty-value dropping, `plainText()` HTML stripping.
- `app/Models/AiAgentResponseCache.php` — response cache keyed by
  `sha256(model + fingerprint)`.
- `app/Jobs/AnalyzeJobCriteria.php` — job flow: build context →
  fingerprint = `CACHE_SCHEMA_VERSION + instructions + context` → cache
  lookup → prompt → persist → `remember()`.

Not ported (out of scope here): per-tenant API keys
(`AiCredentialsResolver`), plan limits (`LimitManager`), activity
broadcasting (`AiActivityService`). Platform key only, via
`config('ai...')`/`OPENAI_API_KEY` per the `laravel/ai` package's own
config.

#### B.2.1 Table `job_posting_profiles`

| column             | type                                                     | notes                                                                                |
| ------------------ | -------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `id`               | pk                                                       |                                                                                      |
| `job_posting_id`   | fk, unique, cascadeOnDelete                              | one profile per posting                                                              |
| `status`           | string enum `ProfileStatus`: `pending`, `done`, `failed` |                                                                                      |
| `schema_version`   | string                                                   | the agent's `CACHE_SCHEMA_VERSION` at extraction time                                |
| `normalized_title` | string, nullable                                         | e.g. "Senior Backend Engineer"                                                       |
| `seniority`        | string, nullable                                         | enum-ish: `intern`, `junior`, `mid`, `senior`, `lead`, `unknown`                     |
| `stack`            | jsonb, default `[]`                                      | canonical lowercase tech names (B.2.4)                                               |
| `locations`        | jsonb, default `[]`                                      | countries/cities/regions as written plainly ("Germany", "Berlin", "EU", "Worldwide") |
| `is_remote`        | boolean, nullable                                        |                                                                                      |
| `summary`          | string(300), nullable                                    | one-sentence plain summary, shown in the client listing                              |
| `extracted_at`     | timestamp, nullable                                      |                                                                                      |
| timestamps         |                                                          |                                                                                      |

GIN index on `stack` (Postgres `jsonb_path_ops` or default) for the
matching query.

#### B.2.2 Agent `App\Ai\Agents\ExtractJobPostingProfile`

- `#[Provider(Lab::OpenAI)]`, `#[Model('gpt-4o-mini')]`, `HasStructuredOutput`,
  `use BuildsCompactAgentContext` (port the trait as-is).
- `CACHE_SCHEMA_VERSION = 'posting-profile-v1'` — bump whenever
  instructions or schema change.
- Instructions (short — every token here is paid on every call): extract
  title, seniority, tech stack, locations, remote flag and a one-sentence
  summary; use only evidence from the context; canonical lowercase tech
  names; don't invent; plain text; context is TOON.
- `postingContext()` → `compactContext([...])` with: `title`,
  `company`, `location`, `is_remote`, `employment_type`, `department`,
  `tags` (from `raw` when the adapter provides them — RemoteOK `tags`,
  Arbeitnow `tags`, Jobicy `jobIndustry`), and `description` =
  `plainText(description_html ?? description_text)` **truncated to
  `MAX_DESCRIPTION_CHARS = 4000`** (constant on the agent). Empty values
  are dropped by `compactContext`.
- Structured output schema with tight bounds: `normalized_title` string
  max 120; `seniority` enum; `stack` array of strings max 40 chars each,
  max 15 items; `locations` array max 40 chars, max 5 items; `is_remote`
  boolean nullable; `summary` string max 300.

#### B.2.3 Job `App\Ai\Jobs\ExtractJobPostingProfileJob`

Queue `ai`, `ShouldBeUnique` (`uniqueId` = posting id), `$tries = 3`,
`backoff [10, 30, 90]`, `$timeout = 75`. Flow mirrors
`AnalyzeJobCriteria`:

1. Load posting; **skip** if a profile with `status = done` and the
   current `schema_version` already exists (never re-extract a re-seen
   posting).
2. Build context + fingerprint; `AiAgentResponseCache::lookup()`. Cache hit
   → persist profile, done, **no API call** (covers identical postings
   repeated across sources/runs).
3. Miss → `prompt()`, persist profile (`status = done`), `remember()`, and
   write one `ai_usage_records` row.
4. `failed()` → profile `status = failed`. No infinite retries.

#### B.2.4 Stack normalization

`App\Outreach\Support\StackNormalizer::normalize(array $items): array` —
lowercase, trim, dedupe, and a small alias map (`nodejs`/`node` →
`node.js`, `reactjs` → `react`, `vuejs` → `vue`, `js` → `javascript`,
`ts` → `typescript`, `golang` → `go`, `postgres` → `postgresql`, `k8s` →
`kubernetes`, `c sharp` → `c#`). Applied to **both** the AI output before
saving and the client's stack preference before saving — so both sides of
the match use the same vocabulary.

#### B.2.5 Tables `ai_agent_response_caches` and `ai_usage_records`

- `ai_agent_response_caches`: same shape as recruiter-labs' model
  (`agent`, `model`, `request_hash` unique with `agent`, `response` json).
- `ai_usage_records` (minimal, for cost visibility only): `agent`,
  `model`, `job_posting_id` nullable, `input_tokens`, `output_tokens`,
  `cache_hit` boolean, `duration_ms`, `status` (`completed`/`failed`),
  timestamps. No admin UI this round — reported in verification via query.

#### B.2.6 Wiring

- `DiscoverCompanyContacts` (from `verification-status` B.2): right after
  a company is set to `outreach_status = verified`, dispatch
  `ExtractJobPostingProfileJob` for each of that company's postings without
  a `done` profile.
- `DiscoverContactsForPosting`: when the posting's company is **already**
  resolved and `verified` (the discovery skip path), dispatch
  `ExtractJobPostingProfileJob` for that posting.
- Backfill command `php artisan postings:extract-profiles` — for every
  posting of a `verified` company without a `done` profile, dispatch the
  job (`chunkById(200)`). Idempotent. Accepts `--limit=N` so the owner can
  test cost on a small batch before running it for everything.
- `composer dev`'s queue worker must cover `collection,contacts,ai,outreach,default`
  and `composer dev` must also run `php artisan schedule:work` (needed by
  B.7).

### B.3 Client preferences

#### B.3.1 Table `job_preferences` (one row per user)

| column              | type                        | notes                                                                   |
| ------------------- | --------------------------- | ----------------------------------------------------------------------- |
| `id`                | pk                          |                                                                         |
| `user_id`           | fk, unique, cascadeOnDelete |                                                                         |
| `titles`            | jsonb, default `[]`         | role names/keywords, e.g. `["backend", "php developer"]`                |
| `keywords`          | jsonb, default `[]`         | the "description" preference: words that must appear in the description |
| `stack`             | jsonb, default `[]`         | normalized via `StackNormalizer`                                        |
| `locations`         | jsonb, default `[]`         | e.g. `["Portugal", "Spain", "EU"]`                                      |
| `accepts_remote`    | boolean, default true       |                                                                         |
| `cv_path`           | string, nullable            | private disk path (B.4)                                                 |
| `cv_original_name`  | string, nullable            | used as the attachment filename                                         |
| `email_subject`     | string, nullable            | template (B.6)                                                          |
| `email_body`        | text, nullable              | template (B.6)                                                          |
| `auto_send_enabled` | boolean, default false      |                                                                         |
| timestamps          |                             |                                                                         |

#### B.3.2 Matching rule — `App\Outreach\Queries\MatchingJobPostings::forUser(User $user): Builder`

**Single source of truth** — the client listing, the dispatcher and the
manual "Apply" action all use this exact query. A posting matches when:

- its company has `outreach_status = verified`, **and**
- it has a profile with `status = done`, **and**
- every preference field the client **filled in** is satisfied (empty
  field = no restriction; filled fields are ANDed; values inside one field
  are ORed):
    - **titles** — any title appears (case-insensitive, `ILIKE %x%`) in
      `job_postings.title` or `profile.normalized_title`;
    - **keywords** — any keyword appears (`ILIKE`) in
      `job_postings.description_text`;
    - **stack** — profile `stack` shares at least one item with the client's
      stack (`jsonb ?| array[...]`);
    - **location** — (`accepts_remote` and `profile.is_remote = true`) **or**
      any client location appears (`ILIKE`) in `job_postings.location` or in
      any `profile.locations` item.
      If the client has no locations and `accepts_remote = false`, the
      location criterion is ignored (no restriction).

If the client has saved **no** preference at all, the query returns
nothing (the listing shows the "configure your preferences" empty state —
we don't show the whole database).

### B.4 CV upload

- Filament `FileUpload` on the preferences page: **PDF only**, max
  **5 MB**, disk `local` (private — never `public`, never a public URL),
  directory `cvs/{user_id}`. Replacing the CV deletes the previous file.
- Client can download their own CV from the preferences page (signed/
  authorized route or Filament download action — never a public link).

### B.5 Gmail integration (per client)

**Reference implementation (read, then port adapting `company` → `user`):**
`github.com/marcusvbda/recruiter-labs`:

- `app/Contracts/OAuthIntegrationPlugin.php`
- `app/Integrations/Google/GoogleOAuthPlugin.php` (PKCE, `access_type=offline`,
  `prompt=consent`, refresh, `OAuthRefreshTokenRejected` on `invalid_grant`)
- `app/Integrations/Gmail/GmailPlugin.php` (scope check,
  `validateConnection`)
- `app/Services/OAuthConnectionStateManager.php`,
  `ConnectedIntegrationRegistry.php`, `ConnectedIntegrationTokenManager.php`
- `app/Actions/CompleteConnectedIntegration.php`,
  `DisconnectConnectedIntegration.php`
- `app/Http/Controllers/ConnectedIntegrationOAuthController.php`
- `app/Models/ConnectedIntegration.php`, `app/Data/OAuthTokenData.php`
- `config/connected-integrations.php` (only the `gmail` block; drop
  Google Calendar)

Adaptations:

- `connected_integrations` table keyed by **`user_id`** (unique
  `user_id + plugin_key`) instead of company. Tokens stored with
  `encrypted` casts. Status enum: `connected`, `reauthorization_required`,
  `disconnected`.
- Scopes: `openid`, `email`, `profile`,
  `https://www.googleapis.com/auth/gmail.send` — nothing else (no read
  access to the client's mailbox).
- Routes (authenticated, active user): `GET /integrations/{plugin}/connect`,
  `GET /integrations/{plugin}/reconnect`, `DELETE /integrations/{plugin}`,
  `GET /integrations/oauth/callback`. After callback, redirect back to the
  preferences page with a success/failure notification.
- `.env`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
  (add to `.env.example` with a comment on how to create the OAuth client
  in Google Cloud Console: Web application, redirect URI =
  `{APP_URL}/integrations/oauth/callback`, Gmail API enabled, test users
  added while in Testing mode — see Part 0.3).
- A 401 / `authError` / `insufficientPermissions` / rejected refresh token
  → integration `reauthorization_required`, client gets a database
  notification "Reconnect your Gmail to keep sending applications", and
  sending for that client stops until reconnected (B.7 eligibility).

### B.6 Email template

- Subject + body are plain text with variables in `{{ name }}` form.
  Allowed variables (constant list, shown as helper text on the form):
  `{{ company }}`, `{{ job_title }}`, `{{ job_location }}`,
  `{{ job_url }}`, `{{ client_name }}`.
- Rendering: `App\Outreach\Support\ApplicationTemplateRenderer` using
  `strtr()` over the allowed variables. **Never** compile client text with
  Blade or any template engine. Body is sent as `text/plain` plus a
  `text/html` part built as `nl2br(e($renderedText))`.
- Validation on save: subject required (max 200), body required (max
  5000), and any `{{ ... }}` token that isn't in the allowed list fails
  validation with the list of unknown variables.
- Default template pre-filled for new users (short, neutral, English),
  e.g. subject `Application — {{ job_title }}`, body greeting the
  `{{ company }}` team, referencing `{{ job_title }}` and `{{ job_url }}`,
  mentioning the attached CV, signed `{{ client_name }}`.

### B.7 Sending

#### B.7.1 Constants — `App\Outreach\OutreachLimits`

```php
final class OutreachLimits
{
    public const DAILY_SEND_LIMIT = 10;          // per client, per app-timezone day
    public const MAX_POSTING_AGE_DAYS = 14;      // auto-send ignores older postings
    public const MIN_SECONDS_BETWEEN_SENDS = 120; // stagger per client
    public const MAX_SECONDS_BETWEEN_SENDS = 480;
    public const RECIPIENT_PRIORITY = ['careers', 'jobs', 'hr', 'talent', 'recruiting', 'people'];
}
```

Nothing else in the codebase hardcodes these values.

#### B.7.2 Table `applications`

| column                  | type                                                                                | notes                                      |
| ----------------------- | ----------------------------------------------------------------------------------- | ------------------------------------------ |
| `id`                    | pk                                                                                  |                                            |
| `user_id`               | fk, cascadeOnDelete                                                                 |                                            |
| `company_id`            | fk, restrictOnDelete                                                                |                                            |
| `job_posting_id`        | fk, nullOnDelete                                                                    | the posting that triggered it              |
| `contact_id`            | fk, nullOnDelete                                                                    | chosen recipient                           |
| `recipient_email`       | string                                                                              | snapshot                                   |
| `subject` / `body`      | string / text                                                                       | rendered snapshot of what was sent         |
| `origin`                | string enum: `auto`, `manual`                                                       |                                            |
| `status`                | string enum `ApplicationStatus`: `queued`, `sending`, `sent`, `failed`, `ambiguous` |                                            |
| `attempts`              | unsigned int, default 0                                                             |                                            |
| `provider_message_id`   | string, nullable                                                                    | Gmail message id                           |
| `last_error`            | string, nullable                                                                    | exception class + short message, no tokens |
| `queued_at` / `sent_at` | timestamp, nullable                                                                 |                                            |
| timestamps              |                                                                                     |                                            |

**Unique `(user_id, company_id)`** — this _is_ the send dedup (Part 0.1
item 8), enforced by the DB, not only by application code.

#### B.7.3 `App\Outreach\Actions\SelectRecipientForCompany`

Returns the company's `smtp_verified` contact with the lowest index in
`RECIPIENT_PRIORITY`, or `null`. Never returns `catch_all`/`mx_only`.

#### B.7.4 Eligibility — `App\Outreach\Actions\CanSendApplications::check(User $user): Result`

A client can send when **all** hold: user `status = active`; Gmail
integration `connected`; CV present; template valid; daily quota not
reached. Returns the list of unmet requirements (used by the preferences
page checklist and by the dispatcher). Quota = count of the user's
`applications` with `status in (queued, sending, sent, ambiguous)` and
`queued_at` today (app timezone) `< DAILY_SEND_LIMIT`. `failed` doesn't
count (nothing left).

#### B.7.5 `App\Outreach\Actions\QueueApplication::handle(User $user, JobPosting $posting, ApplicationOrigin $origin): ?Application`

In one transaction with a per-user lock (`lockForUpdate` on the user's
`job_preferences` row) so concurrent dispatches can't exceed the quota:
re-check eligibility + quota, posting still matches
(`MatchingJobPostings::forUser` constrained to this posting id), recipient
exists, no application for `(user, company)` exists; render the template;
insert `applications` row `queued`; dispatch `SendApplicationEmail` on
queue `outreach` with a delay (B.7.6). Returns `null` (with a reason) when
any check fails — no exception for expected rejections.

#### B.7.6 Dispatcher — `php artisan applications:dispatch`

Scheduled **every 15 minutes** (`routes/console.php`,
`withoutOverlapping()`). For each active user with `auto_send_enabled`
and eligible: take matching postings with `first_seen_at >= now() -
MAX_POSTING_AGE_DAYS`, newest first, **excluding companies already in the
user's `applications`**, one posting per company, up to the remaining
quota → `QueueApplication` (`origin = auto`). Delays are cumulative per
user: each new send is scheduled `random_int(MIN, MAX)` seconds after the
user's last queued/sent one, so a client's emails never go out in a burst.

#### B.7.7 Job `App\Outreach\Jobs\SendApplicationEmail`

Queue `outreach`, `$tries = 3`, backoff `[60, 300, 900]`. Port the
delivery-attempt state machine from recruiter-labs
`app/Services/GmailRecruitmentEmailSender.php`:

- Lock the `applications` row; if `sent` or `ambiguous` → return (never
  re-send). If already `sending` (worker died mid-send) → mark
  `ambiguous`, return. Else → `sending`, `attempts + 1`.
- Build a Symfony `Email`: from `integration.account_email` with the
  user's name; to `recipient_email`; the snapshot subject/body; CV attached
  (`attachFromPath`, filename = `cv_original_name`, `application/pdf`);
  header `X-TalentLabs-Application-Id: {id}`.
- Send with the **media upload** endpoint
  `POST https://gmail.googleapis.com/upload/gmail/v1/users/me/messages/send?uploadType=media`,
  `Content-Type: message/rfc822`, body = raw MIME (handles the attachment
  size; confirm the endpoint against Google's current Gmail API docs
  before relying on it). Access token from the ported
  `ConnectedIntegrationTokenManager` (refreshes when expired).
- Success → `sent`, `provider_message_id`, `sent_at`.
- `ConnectionException` (request may or may not have reached Google) →
  `ambiguous`, log, **no automatic retry** — never risk a duplicate email
  to a company.
- HTTP error → back to `queued` for retry; 401/`authError`/
  `insufficientPermissions` → integration `reauthorization_required`
  (B.5) and mark the application `failed` without further retries.
  After the last try → `failed`.
- Right before sending, re-check that the Gmail integration is still
  `connected`; if not → `failed` with reason, no API call.
- **Never** sends if the `applications` row is missing or doesn't belong
  to a `verified` company's `smtp_verified` contact (defensive re-check).

### B.8 Client panel (`/app`)

Read the current user panel before building (the earlier specs refer to it
as the `/app` Filament panel; adapt to what's actually there). Replace the
"today's jobs" listing's data source; keep layout/nav conventions.

1. **Preferences page** (custom Filament page, one form, "Save" button):
    - Section _Job preferences_: `titles` (TagsInput), `keywords`
      (TagsInput, labeled "Description keywords"), `stack` (TagsInput,
      normalized on save), `locations` (TagsInput), `accepts_remote`
      (toggle).
    - Section _CV_: upload (B.4), current file name + download.
    - Section _Email template_: subject, body (textarea), helper text
      listing the allowed variables, a **"Preview"** action rendering the
      template against the newest matching posting (or sample data if
      none).
    - Section _Gmail_: status card — not connected → "Connect Gmail"
      button; connected → account email + "Disconnect"; reauthorization
      required → warning + "Reconnect". One line of copy: emails are sent
      from your own Gmail account, up to N per day (N from the constant).
    - Section _Automatic sending_: `auto_send_enabled` toggle — disabled
      with the list of unmet requirements from `CanSendApplications` until
      all are met; shows "X of N sent today".
2. **Jobs page** (replaces today's-jobs list): `MatchingJobPostings::forUser`,
   25/page, newest `published_at` first. Columns: title, company,
   location/remote, stack chips (from profile), summary (from profile),
   source label, published (relative), and **Application** status for this
   client: `—` (not applied), `queued`, `sent`, `failed`, `ambiguous`, or
   "Company already contacted" when an application exists for the same
   company via another posting. Row actions: "Open posting" (existing),
   **"Apply"** — visible only when eligible and the company hasn't been
   contacted; opens a confirmation modal with the rendered email preview
   (recipient, subject, body, attachment name) → `QueueApplication`
   (`origin = manual`, no stagger delay beyond the minimum). Empty state
   with a link to Preferences when no preferences are saved.
3. **Applications page** (read-only table of the client's own
   `applications`): company, job title, recipient, status badge, origin,
   queued/sent at; view modal shows the exact subject/body sent.
   Live-updating via the realtime driver (event payload carries only ids,
   never email content).

A client never sees another client's data — every query is scoped by
`auth()->id()`; add a policy for `Application` and `JobPreference`.

### B.9 Admin panel additions (`/admin`)

- **Applications** resource (read-only): user, company, job, recipient,
  status, origin, attempts, last_error, queued/sent at. Filters: user,
  status, origin, date. Realtime `->socket()`.
- **Users** resource: add columns "Gmail" (status badge) and "Sent today"
  (count). No editing of client preferences/templates from admin.

### B.10 Acceptance criteria

- **AC01** — `laravel/ai`, `helgesverre/toon`, `league/oauth2-google`
  installed; no other new dependency.
- **AC02** — A posting of a `verified` company gets exactly one `done`
  profile; a re-seen posting doesn't trigger a new API call; a posting of
  a non-verified company gets no profile and no API call.
- **AC03** — Two postings with identical context produce one API call and
  one cache hit (`ai_usage_records.cache_hit`), proven by the records.
- **AC04** — Description sent to the model is plain text, ≤ 4000 chars,
  TOON-encoded, with empty fields dropped — shown by logging one real
  context payload in the phase evidence.
- **AC05** — Preferences save and reload correctly; stack values are
  normalized (`NodeJS` → `node.js`); unknown template variables are
  rejected on save.
- **AC06** — The Jobs page shows only postings satisfying B.3.2 for the
  logged-in client; two clients with different preferences see different
  lists; no preferences → empty state.
- **AC07** — Gmail connect → OAuth → back to preferences with status
  "connected" and the account email; disconnect works; a revoked/expired
  token turns the status into "reauthorization required", notifies the
  client and stops sending for them.
- **AC08** — Manual "Apply" sends one real email from the client's Gmail
  to the selected `smtp_verified` recipient, with the CV attached and
  variables rendered; `applications` row ends `sent` with a
  `provider_message_id`.
- **AC09** — Recipient selection follows `RECIPIENT_PRIORITY`, uses only
  `smtp_verified` contacts, and never sends to more than one address per
  company.
- **AC10** — A second application to the same company by the same client
  is impossible (UI hides "Apply", `QueueApplication` returns null, and a
  direct insert fails on the unique index).
- **AC11** — The 11th application of the day for a client is refused
  (manual and auto); changing `DAILY_SEND_LIMIT` changes the behavior with
  no other code change.
- **AC12** — `applications:dispatch` with auto-send on queues at most the
  remaining quota, one per company, only postings within
  `MAX_POSTING_AGE_DAYS`, staggered delays between each; running it twice
  in a row queues nothing extra.
- **AC13** — A `ConnectionException` during send leaves the application
  `ambiguous` and it is never retried automatically; a worker killed
  mid-send never produces a second email.
- **AC14** — The CV is never reachable by a public URL; a client can't
  read another client's preferences, CV or applications.
- **AC15** — Existing ACs of `job-collection-mvp`, `contact-discovery-mvp`
  and `verification-status` still hold (spot-check).

### B.11 Verification

1. `postings:extract-profiles --limit=20`; report profiles created,
   API calls vs cache hits, total input/output tokens and the average per
   posting (from `ai_usage_records`), plus 3 sample profiles. **Stop and
   report these numbers to the owner before running the backfill without
   `--limit`.**
2. Tinker: create two test clients with different preferences; report the
   matching count for each and 3 sample titles per client.
3. Owner does the Gmail connect flow in the browser (agent can't) — the
   agent prepares everything and lists the exact steps: Google Cloud
   project, enable Gmail API, OAuth consent screen (Testing, add test
   user), Web client with the redirect URI, `.env` keys.
4. Owner-assisted end-to-end: with auto-send **off**, owner uses "Apply"
   on one job **whose recipient is swapped to the owner's own test
   address** (tinker override of `recipient_email` on the queued row
   before the job runs, or a temporary test company/contact) — **the first
   real send must never go to a real company.** Confirm the email arrives
   with the CV attached and variables rendered.
5. Quota test with a fake sender (bind a test double for the Gmail send
   client in tinker/tests): 11 attempts → 10 queued, 11th refused.
6. Dedup test: two postings of the same company → one application.
7. Report `composer dev` includes `schedule:work` and the queues listed in
   B.2.6.

### B.12 Out of scope

Paid plans/billing (the limit is a constant only); per-client OpenAI keys;
AI-written or AI-personalized email bodies and cover letters; CV per job
or AI CV adaptation; follow-up emails; reply/bounce/open/click tracking
(Gmail send scope only — no mailbox read); other providers (Outlook, SMTP,
SES); sending to `catch_all`/`mx_only` contacts; multiple recipients or CC;
named-contact discovery; automatic retry of `ambiguous` sends; admin
editing client templates/preferences; AI usage admin dashboard; anything
already out of scope in prior specs.
