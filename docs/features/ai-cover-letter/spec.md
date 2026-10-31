# AI Cover Letter and AI Plan

> Body: current behaviour. Sections 2.1-2.3 were read from the code on
> 2026-10-30 (not produced by `/update-feature-spec`; run it to reconcile 2.3);
> section 2.4 describes what was built from the owner's decisions of 2026-10-30
> and 2026-10-31 (`/plan-feature`, `/exec-phase`) and was checked against the
> code on 2026-10-31. The former open decisions O1-O8 are resolved under
> `## Decisions`.
>
> Depends on: nothing hard. Landing copy for the new plan lives in
> `landing-and-legal`.

## 1. What it is

A new plan above Pro that does everything Pro does plus an AI-written cover
letter per job, inserted in the email body. Cost goal (owner): keep AI cheap
and only where it adds value.

## 2. Current behaviour (2026-10-30)

### 2.1 Plans

- `PlanKey`: `free`, `starter`, `pro`, `max`. Catalog in `config/talent.php`
  `plans.catalog`: name, `mode` (`random` | `select` | `review`),
  `daily_limit` (env defaults 10 / 50 / 100 / 100), `ai_writing` (bool) and
  `ai_monthly_quota` (int; `false` / 0 except Max). `Plan` value object
  (`canChooseJobs()` for `select`/`review`; `aiWriting`, `aiMonthlyQuota`); `PlanCatalog` (`for`, `get`,
  `all`, `priceFor`, `stripePriceId`, `planForStripePrice`, `isHighlighted`).
- Prices per region (`br`, `eu`, `row`) in minor units (`plans.prices`, 0 =
  free, no checkout) and Stripe price ids (`plans.stripe_prices`).
- Admin changes a user's plan in `EditUser` (sets `plan_source` manual);
  invitations can preset a plan. Filament selects and filters use `PlanKey`, so
  Max is listed everywhere without further changes.

### 2.2 Email content

- `ApplicationProfile` per language (`en`, `pt`): `email_subject`,
  `email_body`, `cover_letter` (manual; max 800 characters, the same cap as an
  AI letter, for every plan; may not contain `{{ cover_letter }}`), `links`, CV.
  A stored letter over 800 characters is left as is and fails validation only
  when the profile is saved again.
- `ApplicationTemplateRenderer::ALLOWED_VARIABLES`: `company`, `job_title`,
  `job_location`, `job_url`, `client_name`, `cover_letter`, `links`. The
  default bodies put `{{ cover_letter }}` after the greeting.
- Rendering: review drafts in `ReviewDraftPresenter::forUser` / `bodyFor` (review
  mode, `ReviewController::drafts`); otherwise `QueueApplication` renders at queue
  time (or uses the client's reviewed subject/body). Limits
  `CanSendApplications::MAX_SUBJECT_LENGTH` 200, `MAX_BODY_LENGTH` 5000.

### 2.3 AI today

- Laravel AI SDK (`laravel/ai`). One agent: `ExtractJobPostingProfile`
  (OpenAI `gpt-5-nano`, reasoning effort minimal, description cut at 4000
  chars, structured output: normalized title, seniority, stack, locations,
  is_remote, language, summary). Runs once per posting, on queue `ai`, only
  for verified companies; cached in `AiAgentResponseCache` by fingerprint;
  every call recorded in `AiUsageRecord` (agent, model, `job_posting_id`,
  tokens, cache hit, duration, status; `user_id` is set only on cover letter
  generations, see 2.4).
- Match score (`MatchScore`, see `docs/features/match-ranking`) is SQL with
  weights stack 50, seniority 25, location 15, title 10. No AI per user.

### 2.4 Max plan and AI cover letter (built 2026-10-31)

**The plan.**

- Max: key `max`, name "Max", mode `review` (can choose jobs, reviews each
  email), 100 applications per day (`PLAN_MAX_DAILY_LIMIT`), `ai_writing` true,
  500 AI letters per month (`PLAN_MAX_AI_MONTHLY_QUOTA`). Prices R$ 90 /
  € 29 / US$ 30 (`PRICE_MAX_BRL|EUR|USD`, minor units 9000 / 2900 / 3000).
  Stripe ids come from `STRIPE_PRICE_MAX_BRL|EUR|USD`; until they are set, checkout
  for Max is refused as `unavailable` (the missing key is reported). The client
  plans page, the landing pricing grid and the fixtures show four plans.
- AI behaviour reads the capabilities `aiWriting` and `aiMonthlyQuota`, never a
  `PlanKey`.

**Generation.**

- Agent `App\Ai\Agents\GenerateCoverLetter` (`Promptable`,
  `HasStructuredOutput`, `BuildsCompactAgentContext`). Provider, model and
  reasoning effort come from `talent.ai.cover_letter.*` (`AI_COVER_LETTER_PROVIDER`
  `openai`, `AI_COVER_LETTER_MODEL` `gpt-5-mini`, `AI_COVER_LETTER_REASONING_EFFORT`
  `minimal`; empty effort uses the provider default). Posting extraction stays on
  `gpt-5-nano`. Each call has a 30 s timeout.
- Context: company; the posting profile (normalized title, seniority, stack,
  summary); the description (decoded to plain text, redacted with
  `ClientSafeText::redact`, cut at 4000 characters); the profile language; the
  manual cover letter (redacted; the only source of facts about the client); link
  labels; the client's preference stack (emphasis only); and the client's email
  body rendered with the slot replaced by the marker `[[COVER_LETTER]]`, the job
  link blank and `links` as labels only. No URL or email address reaches the
  model. CV text is not an input.
- Output schema: `cover_letter` (string, max 800) and `language` (`en` | `pt`).
  Instructions: write only the text for the marker, no greeting, sign-off,
  subject, URLs, emails or placeholders, in the given language, about 100 words,
  one concrete point about the job and one about the client, never invent
  experience, years, employers, degrees or numbers.
- Only the slot is AI: `ReviewDraftPresenter::bodyFor` renders the body as always
  and replaces just the `cover_letter` variable. Subject, the rest of the body,
  the job link (a `{{ job_url }}` token in drafts), links, CV line and signature
  stay deterministic. A body without `{{ cover_letter }}`, or an empty manual
  letter, gets no generation (draft `ai.state` is `off`), and the profile form
  shows the hint `profiles.ai.hint` while either is missing on an AI plan.
- Length lives in `OutreachLimits`: `COVER_LETTER_TARGET_WORDS` 100 and
  `COVER_LETTER_MAX_CHARS` 800, enforced by the schema, the prompt and the
  validator.
- `CoverLetterValidator::accepts` returns the trimmed letter, or null when it is
  empty, over 800 characters, contains `{{`, `}}` or `[[`, changes under
  `ClientSafeText::redact` (a URL or email), contains a bare domain on a common
  TLD, or its `language` differs from the profile's. It never truncates.

**When it runs and what it costs.**

- `GenerateCoverLetterJob` (queue `ai`, unique per user and posting for 90 s,
  one try, 75 s timeout) runs only on demand and never for automatic sends, which
  use the manual letter. It: stops silently if the client already applied; sends
  `manual` / `failed` if the profile is missing, incomplete or not eligible
  (eligible = plan `aiWriting`, body has `{{ cover_letter }}`, non-empty manual
  letter); serves a cached letter (recording a cache-hit usage row) unless
  regenerating; sends `manual` / `quota` when the quota is used up; otherwise
  tries the agent up to twice, validating each output and recording one
  `AiUsageRecord` per attempt (Completed when accepted, Failed otherwise, also on
  provider errors, whose messages are never logged). An accepted letter is cached
  and sent as `ready`; two rejections send `manual` / `failed`. The AI never blocks
  queueing or sending.
- Cache: `AiAgentResponseCache`, keyed by agent, the configured model and the
  fingerprint (prompt version, user, posting, language, hash of the manual letter
  and body). Editing either text produces a new letter; Regenerate overwrites the
  cached one. Cache rows are deleted with the user.
- Quota: `CoverLetterQuota::used` counts the user's Completed, non-cache-hit
  cover letter usage rows in the current calendar month (app timezone); the limit
  is the plan's `aiMonthlyQuota`. Jobs already running may overrun it slightly (no
  lock). Cache hits and Failed attempts do not count; Regenerate does.
- `ai_usage_records.user_id` and `ai_agent_response_caches.user_id` are nullable
  foreign keys (usage rows keep a null `user_id` when the user is deleted).

**Drafts, endpoint and realtime.**

- `ReviewDraft.ai` is `null` on plans without `aiWriting`; otherwise
  `{ state }` with `off`, `ready` (the cached letter is already in `body`),
  `quota` (no cached letter and the quota is used up) or `idle`. Loading drafts
  dispatches nothing.
- `POST /internal/applications/drafts/cover-letter` (`jobId`, optional
  `regenerate`; throttled 30 per minute; 403 without `aiWriting`) resolves the
  posting from the client's pool and the active complete profile and answers
  `off`, `ready` (cached, with a cache-hit usage row), `quota`, or `writing` after
  dispatching the job.
- The job reports through the private `App.Models.User.{id}` event
  `cover_letter.updated`: `jobId`, `state` (`ready` | `manual`), `reason`
  (`quota` | `failed` | null), `body` (built by `bodyFor`, so clients never see the
  real job URL, a recipient, tier or source) and `aiQuota { used, limit }`. The
  account status carries `aiWriting { used, limit }` (null without the capability)
  and is kept in sync from the event.

**Review screen.**

- For a draft with `ai` set, the screen requests a letter for the open draft and
  the next one only (once each per session), shows the state in a bar below the
  editor, and applies an arriving letter as the body when the client has not edited
  that draft. If the client edited it, the edit stays and "Use AI version" offers
  the new text (replacing the whole body). The "AI-written" chip shows while the
  body is the unedited AI letter; the sent text carries no mark.
- "Regenerate" asks for a new letter and is disabled when the monthly quota is
  used up, while writing and on queued drafts. Quota and failure messages say the
  draft uses the client's own letter. A draft still writing for 90 s becomes
  `failed` locally (no polling).
- Approve and Approve all send whatever body is displayed: a draft still being
  written goes with the manual letter, and a letter arriving for a queued draft is
  ignored. The plans page shows "N of M AI letters used this month" on AI plans.
- Plans without AI see the review screen unchanged.

**Privacy.** Prompts contain client data. Nothing logs prompt or output text; only
tokens, status and timing are recorded.

## 3. Open points

- No CV text extraction exists (CVs are stored PDFs only).

## Pending changes

- **P22 (remaining part).** The privacy policy names the AI provider
  (`landing-and-legal`).

## Out of scope

- AI-personalized CV: a later spec (D13).
- Cover letter as a PDF attachment (decided: no).
- AI rewriting the whole email body (decided: no).
- AI in the match score (decided: no).

## Decisions

- **D1.** (2026-10-30) New plan above Pro = everything Pro does + AI-written
  email content.
- **D2.** The AI cover letter goes inside the email body through
  `{{ cover_letter }}`, not as a PDF.
- **D3.** The AI generates only the `{{ cover_letter }}` slot; it is inserted
  into the client's template afterwards.
- **D4.** The cover letter has a character limit.
- **D5.** The match percentage stays rule-based (no AI per client).
- **D6.** (2026-10-31) Plan key `max`, display name "Max" (was O1).
- **D7.** (2026-10-31) Max: 100 applications per day, 500 AI letters per month,
  R$ 90 / € 29 / US$ 30; the owner creates the Stripe prices (was O2).
- **D8.** (2026-10-31) Cover letters use `gpt-5-mini`, set in config; posting
  extraction stays on `gpt-5-nano` (was O3).
- **D9.** (2026-10-31) Target 100 words, maximum 800 characters (was O4).
- **D10.** (2026-10-31) Longer manual letters get no special treatment: there
  are no clients yet (was O5).
- **D11.** (2026-10-31) No CV text extraction in v1 (was O6).
- **D12.** (2026-10-31) Automatic sends on Max use the manual letter (was O7).
- **D13.** (2026-10-31) AI-personalized CV goes to a later spec, limited to
  reordering or highlighting existing content (was O8).
- **D14.** (2026-10-31) An AI letter never contains a URL or an email address,
  because clients must not see the recipient or the job link.
- **D15.** (2026-10-31) A letter is generated when the client reaches its draft,
  plus the next one, so skipped or unopened drafts spend no quota.
- **D16.** (2026-10-31) A letter arriving after an edit keeps the edit and offers
  "Use AI version", which replaces the whole body.
- **D17.** (2026-10-31) The "AI-written" label is a chip below the editor; the
  sent text carries no mark.
