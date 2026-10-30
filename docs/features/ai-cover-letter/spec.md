# AI Cover Letter and AI Plan

> Body: current behaviour, read from the code on 2026-10-30 (not produced by
> `/update-feature-spec`; run it first if you want it reconciled).
> `## Pending changes` holds what the owner decided on 2026-10-30. Items
> marked **O** are open decisions: `/plan-feature` asks the owner, never
> guesses.
>
> Depends on: nothing hard. Landing copy for the new plan lives in
> `landing-and-legal`.

## 1. What it is

A new plan above Pro that does everything Pro does plus an AI-written cover
letter per job, inserted in the email body. Cost goal (owner): keep AI cheap
and only where it adds value.

## 2. Current behaviour (2026-10-30)

### 2.1 Plans

- `PlanKey`: `free`, `starter`, `pro`. Catalog in `config/talent.php`
  `plans.catalog`: name, `mode` (`random` | `select` | `review`),
  `daily_limit` (env defaults 10 / 50 / 100). `Plan` value object
  (`canChooseJobs()` for `select`/`review`); `PlanCatalog` (`for`, `get`,
  `all`, `priceFor`, `stripePriceId`, `planForStripePrice`, `isHighlighted`).
- Prices per region (`br`, `eu`, `row`) in minor units (`plans.prices`, 0 =
  free, no checkout) and Stripe price ids (`plans.stripe_prices`).
- Admin changes a user's plan in `EditUser` (sets `plan_source` manual);
  invitations can preset a plan.

### 2.2 Email content

- `ApplicationProfile` per language (`en`, `pt`): `email_subject`,
  `email_body`, `cover_letter` (manual; max 6000; may not contain
  `{{ cover_letter }}`), `links`, CV.
- `ApplicationTemplateRenderer::ALLOWED_VARIABLES`: `company`, `job_title`,
  `job_location`, `job_url`, `client_name`, `cover_letter`, `links`. The
  default bodies put `{{ cover_letter }}` after the greeting.
- Rendering: review drafts in `ReviewDraftPresenter::forUser` (review mode,
  `ReviewController::drafts`); otherwise `QueueApplication` renders at queue
  time (or uses the client's reviewed subject/body). Limits
  `CanSendApplications::MAX_SUBJECT_LENGTH` 200, `MAX_BODY_LENGTH` 5000.

### 2.3 AI today

- Laravel AI SDK (`laravel/ai`). One agent: `ExtractJobPostingProfile`
  (OpenAI `gpt-5-nano`, reasoning effort minimal, description cut at 4000
  chars, structured output: normalized title, seniority, stack, locations,
  is_remote, language, summary). Runs once per posting, on queue `ai`, only
  for verified companies; cached in `AiAgentResponseCache` by fingerprint;
  every call recorded in `AiUsageRecord` (agent, model, `job_posting_id`,
  tokens, cache hit, duration, status — **no user id**).
- Match score (`MatchScore`, see `docs/features/match-ranking`) is SQL with
  weights stack 50, seniority 25, location 15, title 10. No AI per user.

## 3. Open points

- No CV text extraction exists (CVs are stored PDFs only).

## Pending changes

### The plan

- **P1.** New plan above Pro: same mode as Pro (`review`, can choose jobs)
  plus AI writing. Key, display name, daily limit and prices — **O1**, **O2**.
- **P2.** `Plan` gains capabilities read from the catalog config:
  `aiWriting` (bool) and `aiMonthlyQuota` (int). Existing plans: false / 0.
  Code checks capabilities, never a `PlanKey`.
- **P3.** Config: catalog entry, `prices` and `stripe_prices` for all three
  regions, env keys appended to `.env.example` (never overwrite existing
  values).
- **P4.** `PlanKey` gets the new case and label; admin user and invitation
  selects/filters pick it up; `EditUser` plan change works as today.
- **P5.** Client plans page (`PlansResource`) and billing checkout support the
  new plan.

### Generation

- **P6.** New agent `App\Ai\Agents\GenerateCoverLetter`, same pattern as
  `ExtractJobPostingProfile` (`Promptable`, `HasStructuredOutput`,
  `BuildsCompactAgentContext`, `CACHE_SCHEMA_VERSION`). Provider and model in
  config (`talent.ai.cover_letter.*`) — **O3**. Reasoning effort minimal if
  the model is a reasoning model.
- **P7.** Inputs (compact context): company name; posting profile
  (normalized title, seniority, stack, summary) and description cut at 4000
  chars; the client's application profile in the application's language:
  the **manual cover letter** (the only source of facts about the client),
  link labels; the client's preference stack; and the client's rendered body
  with the slot replaced by the marker `[[COVER_LETTER]]`, so the model sees
  the greeting and closing around it. CV text is not an input in v1 —
  **O6**.
- **P8.** Output schema: `cover_letter` (string, max `COVER_LETTER_MAX_CHARS`)
  and `language` (`en` | `pt`).
- **P9.** Instructions: write only the text for the marker — no greeting, no
  sign-off, no subject, no URLs, no placeholders; in the profile's language;
  aim at `COVER_LETTER_TARGET_WORDS`; one concrete point about the job and one
  concrete point about the client; use only facts present in the inputs;
  never invent experience, years, employers, degrees or numbers.
- **P10.** Only the slot is AI-generated. Subject, rest of the body,
  `job_url`, `links`, the CV line and the signature stay deterministic
  (`ApplicationTemplateRenderer`). The AI text is the value of the
  `cover_letter` variable. A body without `{{ cover_letter }}` gets no
  generation; the profile screen shows a hint for AI-plan users.

### Length

- **P11.** Constants in one place: `COVER_LETTER_TARGET_WORDS` and
  `COVER_LETTER_MAX_CHARS` — values **O4** (proposal: target ~100 words, max
  ~700–800 chars; Portuguese runs longer, test both languages before fixing
  the numbers).
- **P12.** Enforced in three layers: schema `max`, prompt target below the
  max, server validation (P14).
- **P13.** The manual cover letter uses the same max in
  `UpdateApplicationProfileRequest` (today 6000). Existing longer letters are
  not changed in the database — **O5** for how they are treated.

### Validation and fallback

- **P14.** AI output is accepted only if: not empty; ≤ max chars; no `{{`,
  `}}` or `[[`; no URL or email address that is not present in the inputs;
  `language` equals the profile language.
- **P15.** On rejection or error: one regeneration; if it fails again, the
  manual cover letter is used. Never truncate mid-sentence; never block
  queueing or sending; the failure is recorded in `AiUsageRecord`.

### When it runs, cost control

- **P16.** Generated on demand only, when review drafts are built for a user
  whose plan has `aiWriting` — never in batch for the job pool. Automatic
  (random) sends on the AI plan — **O7** (recommended for v1: manual letter).
- **P17.** Asynchronous: drafts come back immediately with the manual letter
  and a "writing…" state; one queued job per draft on queue `ai` generates the
  letter and updates the draft over the user's private realtime channel (no
  polling). If the client sends before it arrives, the manual letter is used.
- **P18.** Cache key: user, posting, language, hash of the profile content
  (cover letter + body), prompt version (`AiAgentResponseCache`). Reopening a
  draft reuses it; a "Regenerate" action forces a new one and counts toward
  the quota.
- **P19.** Monthly quota per user = `aiMonthlyQuota`: successful non-cache
  generations in the calendar month. When reached, drafts use the manual
  letter and the review screen says so; the plans/account screen shows usage.
- **P20.** `ai_usage_records.user_id` (nullable, forward migration); every
  generation is recorded with it.

### Review screen

- **P21.** The AI letter appears inside the editable body, labelled
  "AI-written", with "Regenerate" (disabled when the quota is used up). The
  client's edits are final and are sent as the reviewed body, as today.

### Privacy

- **P22.** Prompts contain client data: never log prompt or output text (only
  tokens/status, as today). The privacy policy names the AI provider
  (`landing-and-legal`).

## Out of scope

- AI-personalized CV — **O8**.
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

## Open decisions

- **O1.** Plan key and display name.
- **O2.** Daily limit and prices per region (BRL, EUR, USD) and Stripe price
  ids.
- **O3.** Model for writing. `gpt-5-nano` is fine for extraction but likely
  weak for prose; pick a stronger small model and record cost per 100
  letters.
- **O4.** Target words and max chars.
- **O5.** Existing manual letters above the new max: keep sending them, or
  ask the client to shorten before the next send.
- **O6.** Extract CV text (PDF) as a source of facts — better letters, more
  work, and more personal data sent to the AI provider.
- **O7.** AI letters for automatic/random sends on the AI plan.
- **O8.** AI-personalized CV: the owner wants it on the top plan; the
  recommendation was a later spec, limited to reordering/highlighting existing
  content, never new text.
