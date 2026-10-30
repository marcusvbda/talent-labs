# Landing and Legal

> Body: current behaviour, read from the code on 2026-10-30 (not produced by
> `/update-feature-spec`; run it first if you want it reconciled).
> `## Pending changes` holds what the owner decided on 2026-10-30. Items
> marked **O** are open decisions: `/plan-feature` asks the owner, never
> guesses.
>
> Order: run **last** — the copy must describe what actually shipped from
> `signup`, `email-providers`, `contact-quality` and `ai-cover-letter`. The
> legal pages (P10–P14) can run earlier: Google app verification needs a
> privacy policy URL (`production-hardening` P18).

## 1. What it is

The public landing page (`/`) and the legal surface it links to: privacy
policy, terms and the opt-out for companies.

## 2. Current behaviour (2026-10-30)

- `LandingController` renders Inertia `landing` with: `plans` (key, mode,
  daily limit, highlighted, prices per region), `defaultRegion`,
  `betaClosed` (`talent.landing.beta_closed`), `contactEmail`
  (`talent.contact_email`) and `legal` links for routes named `privacy`,
  `terms` and `opt-out` — **none of these routes exist**, so the footer shows
  no legal links.
- Sections (`resources/js/features/landing/`): nav, hero + demo (labelled
  sample data), guarantees strip, matching, languages, live sending band,
  pricing, FAQ, CTA, footer. Copy in `lang/en.json` and `lang/pt.json`
  (`landing.*`).
- Plan cards describe plans by `mode` only (`landing.plans.desc.auto|select|review`
  and `feature.*`).
- Copy that is wrong or about to be wrong:
  - FAQ 1: "We only ask Google for permission to send emails" (Gmail only).
  - FAQ 6: "Through the opt-out page linked in the footer" — the page does not
    exist.
  - Live band: "message adaptation to the job" — today it is template
    variables, not adaptation.
  - Meta description: "finds developer jobs" — sources also collect support
    and product roles.

## 3. Open points

- Legal texts are not legal advice; the owner provides or approves them.

## Pending changes

### Content (English in `en.json`, Portuguese kept in sync in `pt.json`)

- **P1.** Providers: "sent from your own email" names the providers that are
  actually enabled (from the plugin registry/config), so the landing never
  promises an unshipped provider.
- **P2.** FAQ "Do you read my inbox?" per provider: OAuth providers (Google,
  Microsoft) — send-only permission, nothing is read; SMTP — an app password
  is stored encrypted and used only to send.
- **P3.** Plan cards built from plan capabilities, not only `mode`: the AI
  plan shows "AI-written cover letter for each job (up to N per month)" from
  `aiMonthlyQuota`. New FAQ "Does AI write my emails?": only the cover-letter
  paragraph, only on that plan, from the client's own information, reviewed
  by the client before sending, never invents experience.
- **P4.** Matching section mentions the match percentage shown on each job
  (rule-based).
- **P5.** Live band: replace "message adaptation to the job" with what is
  true — job details filled into the client's template, plus the AI paragraph
  on the AI plan.
- **P6.** Who receives the email, described honestly: a verified address at
  the company — the email published in the job when there is one, otherwise a
  verified recruiting address. Never promise decision-makers, interviews or
  replies (FAQ 4 stays).
- **P7.** Beta badge, CTA and plans note driven by the registration mode
  (`signup` P11); in invite mode "Request access" uses `contactEmail`.
- **P8.** Meta title/description match the role families actually collected.
- **P9.** No invented numbers, testimonials or logos; demo stays labelled as
  sample data.

### Legal

- **P10.** Routes named `privacy`, `terms`, `opt-out` (the names matter:
  `LandingController` links them automatically). Inertia pages, en/pt.
- **P11.** Texts come from the owner. The agent may scaffold them with every
  section clearly marked DRAFT. The privacy policy must cover: client account
  data, CVs, email-provider tokens and SMTP credentials, the AI provider that
  processes job postings and cover letters, company contact data including
  named people (`contact-quality` Stage 2), retention, data-subject rights,
  contact address.
- **P12.** Opt-out for companies: form (company domain + an email address at
  that domain) → confirmation email to that address with a signed, expiring
  link → on confirmation the domain is suppressed. `throttle:5,1` per IP and a
  per-domain daily cap; responses never reveal whether the company was ever
  contacted.
- **P13.** Suppression list: table `outreach_suppressions` (domain or exact
  email, reason, source `opt_out` | `admin`, confirmed_at). Effects:
  - `QueueApplication` rejects suppressed recipients with a client message
    ("This company asked not to receive applications");
  - `SendApplicationEmail` re-checks before delivery and cancels;
  - contact discovery and Stage 2 lookups skip suppressed domains (no
    probing).
  Filament resource to list, add and remove suppressions.
- **P14.** Footer links appear once P10 exists (no extra code needed).

## Decisions

- **D1.** (2026-10-30) The landing content must be updated to what changed.

## Open decisions

- **O1.** Add a one-line opt-out note to every application email? Rules for
  unsolicited email differ by country; not legal advice.
- **O2.** Who writes the legal texts (owner, lawyer, or agent drafts for
  review).
