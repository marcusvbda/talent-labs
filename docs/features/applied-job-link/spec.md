# applied-job-link — show the official job link after an application is sent

> **Kind:** small change on top of executed specs (`client-app-screens`,
> `client-core-wiring`). Backend + frontend + doc updates.
> **Depends on:** specs 1–9 DONE. Independent of `production-readiness`.
> **New dependencies:** none. **Migrations:** none.
>
> **How to run:** `/plan-spec docs/features/applied-job-link/spec.md`, then
> `/execute-phases` (3 phases, one batch). Run `composer lint:check`,
> `composer types:check`, `yarn check`, `yarn types:check` at the end of
> **each** phase and commit per phase.

## Part 0 — Context and decisions (owner, final, 2026-09-29)

The application email goes to the company's HR address and already carries
the job link through the `{{ job_url }}` template variable (default
templates in `application-languages`). Many companies only process
candidates through their own portal (ATS). To give the client a way to
**strengthen** an application, the client may also apply through the
official job page **by themselves**. The product does not automate that
(no form filling, no bots on third-party sites).

Decided:

1. **The job URL is shown only after the application is sent.** An
   `ApplicationItem` carries `jobUrl` only when `status = 'sent'`. Every
   other status (`queued`, `sending`, `failed`, `ambiguous`) returns
   `null`. The pool (Jobs list, Job detail, matches, review drafts,
   template previews) **still never** shows a job URL — the rule of
   `client-app-screens` Part 0 stays for everything before sending.
2. **Same for every plan** (`auto`, `select`, `review`). Not plan-gated.
3. **Shown only on the Applications screen (S4)**: table row action and the
   Application detail sheet. Dashboard activity, live sending panel and
   notifications do not render it (the field may be present in the payload;
   components there ignore it).
4. **Single source of the URL:** the exact URL that `{{ job_url }}` renders
   in the email (`ApplicationTemplateRenderer`). If the renderer resolves it
   inline today, extract that resolution into one method (e.g.
   `JobPosting::applicationUrl(): ?string`) and use it in both places. No
   second lookup rule.
5. **Safety:** return the URL only if its scheme is `http` or `https`
   (otherwise `null`); the link opens in a new tab with
   `rel="noopener noreferrer"`.
6. Recipient email addresses and company domains/websites stay hidden
   everywhere, including after sending. Only the job URL changes.
7. The body snapshot in the detail sheet keeps the "job link" chip
   (unchanged); the URL appears in its own block (B.4).

## Part B — Product spec

### B.1 Files to read first

`docs/features/client-app-screens/spec.md` (Part 0, B.2 contracts, S4, B.7
realtime, AC08), `docs/features/client-core-wiring/spec.md` (Part 0 item 6,
B.6 applications endpoints and "Resources never include", AC06),
`app/Outreach/Support/{ApplicationTemplateRenderer,ClientSafeText}.php`,
the Resource/serializer that builds `ApplicationItem` / `ApplicationDetail`
(and the `ApplicationProgressed` event payload), `app/Models/{Application,JobPosting}.php`,
`resources/js/types/contracts.ts`, `resources/js/data/fixtures/` (applications
fixtures and the dev realtime emitter), `resources/js/pages/applications.tsx`
and its feature components, lang files `lang/{en,pt,es}.json`.

### B.2 Contract change

```ts
export type ApplicationItem = {
    // …existing fields unchanged…
    jobUrl: string | null; // official job page; non-null only when status === 'sent'
};
```

`ApplicationDetail` inherits it. The realtime event
`application.progressed` carries the same `ApplicationItem`, so a row that
becomes `sent` gets its link live, without reload.

### B.3 Backend

- One place decides the field (the `ApplicationItem` serializer):
  `jobUrl = status === sent ? safeUrl(posting->applicationUrl()) : null`.
  Every endpoint and event that emits `ApplicationItem` goes through it, so
  the rule cannot diverge between list, detail, dashboard and realtime.
- `safeUrl`: parse with `parse_url`; scheme must be `http`/`https` and host
  non-empty, else `null`. Posting deleted or URL empty → `null`.
- Eager-load the posting in `GET /internal/applications` (no N+1).
- Update the "Resources never include" rule: the `ApplicationItem`
  serializer is the only exception, for `jobUrl` on sent applications.
- Account export (`GET /internal/account/export`): unchanged.

### B.4 Frontend (S4 Applications)

- **Table (desktop):** new last column, no header text (aria-label "Job
  page"): for rows with `jobUrl`, an `IconButton` with the arrow-out icon
  (same icon family, the circular arrow-out button from the design system),
  tooltip "Open job page", opens `jobUrl` in a new tab. Rows without
  `jobUrl` render an empty cell. Clicking the button must not open the
  detail sheet (stop propagation).
- **Mobile cards (`ActivityRow`):** add an optional `trailingAction` slot
  to `ActivityRow` (pattern layer, no duplicated markup); S4 passes the same
  `IconButton`. Dashboard activity does not pass it (unchanged).
- **Detail sheet:** when `jobUrl` is present, a block under the status/timeline:
  title "Strengthen your application", text "Your email was sent to the
  company. Many companies also review candidates through their own job
  page. Applying there too is optional and done by you.", secondary button
  "Open job page" (new tab). When `jobUrl` is null and status is `sent`
  (URL unavailable), the block is not rendered.
- All strings in `lang/{en,pt,es}.json`:
    - en: "Job page" · "Open job page" · "Strengthen your application" · the text above.
    - pt: "Página da vaga" · "Abrir página da vaga" · "Reforce sua candidatura" · "Seu e-mail foi enviado para a empresa. Muitas empresas também avaliam candidatos pela própria página da vaga. Aplicar por lá é opcional e feito por você."
    - es: "Página de la oferta" · "Abrir página de la oferta" · "Refuerza tu candidatura" · "Tu correo fue enviado a la empresa. Muchas empresas también revisan candidatos en su propia página de la oferta. Postularte allí es opcional y lo haces tú."
- **Fixtures:** every `sent` fixture application gets a realistic
  `jobUrl` (fake but plausible ATS-style URLs on fictional company domains,
  consistent with existing fixture companies); all other statuses `null`.
  The dev realtime emitter sets `jobUrl` at the moment it flips an item to
  `sent`.

### B.5 Doc updates (phase 1, text edits only)

Edit the existing specs so they stay the product truth:

1. `client-app-screens/spec.md` Part 0, bullet "The client never sees data
   that lets them apply outside the product": append "Exception
   (`applied-job-link`): after an application is **sent**, the Applications
   screen shows the official job page link (`ApplicationItem.jobUrl`).
   Before sending, no job URL anywhere."
2. Same file, B.2: add `jobUrl: string | null;` to `ApplicationItem` with
   the comment from B.2 above.
3. Same file, S4: add one sentence pointing to `applied-job-link` B.4.
4. Same file, AC08: "No screen shows a job URL **before the application is
   sent** (sent applications show it on the Applications screen only), a
   recipient email address or a company domain; template previews show the
   'job link' chip."
5. `client-core-wiring/spec.md` Part 0 item 6: append "Exception:
   `ApplicationItem.jobUrl` for sent applications (`applied-job-link`)."
   and the same exception on the "Resources never include" line and AC06.

## Acceptance criteria

- **AC01** A `sent` application shows the job page link in the S4 table
  (desktop), the mobile card and the detail sheet; it opens the same URL
  that `{{ job_url }}` rendered in its email, in a new tab.
- **AC02** `queued`, `sending`, `failed` and `ambiguous` applications
  return `jobUrl: null` from every endpoint and event; no link is rendered.
- **AC03** Jobs list, Job detail, matches, review drafts and template
  previews still never contain a job URL (grep the responses in a manual run).
- **AC04** Same behavior on `auto`, `select` and `review` plans.
- **AC05** When an application becomes `sent`, its row gains the link live
  (two windows, no reload).
- **AC06** A posting URL with a non-http(s) scheme or empty host yields
  `null`.
- **AC07** Dashboard activity and live panel look exactly as before.
- **AC08** EN/PT/ES strings present; no hardcoded UI text.
- **AC09** The five doc edits of B.5 are applied.

## Phases

1. **Docs** — apply B.5 (text only).
2. **Backend** — URL resolver extraction (Part 0 item 4), serializer rule
   and `safeUrl` (B.3), eager loading, event payload goes through the same
   serializer.
3. **Frontend** — contract type, fixtures + emitter, `ActivityRow`
   `trailingAction`, S4 table column, mobile card, detail sheet block, lang
   strings (B.4).

## Verification

`composer lint:check`, `composer types:check`, `yarn check`,
`yarn types:check`, existing tests. Manual: with fixtures on, sent rows show
the link and others don't; with fixtures off, send one application to the
`OUTREACH_INTERCEPT_TO` address and watch the row gain the link live; open
`/internal/jobs` and `/internal/applications` responses and confirm AC02/AC03.

## Out of scope

Automating applications on job pages (form filling, bots, browser agents),
showing URLs before sending, showing recipient emails or company domains,
tracking whether the client applied on the job page, changes to the email
templates.
