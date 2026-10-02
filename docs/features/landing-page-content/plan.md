# Plan — landing-page-content — landing page content changes

Source spec: `docs/features/landing-page-content/spec.md` · SHA-256 `95378fe9f6e86f5e7bedae8ff92cbf56789c509304484ec52af09ceae7f01433`
Product truth: same file (Part 0 global rules + Part 1 items 1–11)
Run phases with `/execute-phases docs/features/landing-page-content/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                              | Role             | Depends on | Size | Status  |
| ----- | -------------------------------------------------- | ---------------- | ---------- | ---- | ------- |
| 1     | Copy-only replacements (items 1, 3, 4, 5, 6, 8–10) | inertia-frontend | none       | S    | PENDING |
| 2     | Caption under the matching chips (item 7)          | inertia-frontend | none       | S    | PENDING |
| 3     | Gmail → email across the landing (item 11)         | inertia-frontend | none       | M    | PENDING |
| 4     | Hero demo framed as a product preview (item 2)     | inertia-frontend | none       | S    | PENDING |
| 5     | Verification and report                            | qa-tester        | 1–4        | S    | PENDING |

Phases 1–4 are independent of each other and can run in any order. They all
edit `lang/en.json` + `lang/pt.json`, so run them one at a time, not in
parallel.

## Audit — 2026-10-03

| Check | Result |
| ----- | ------ |
| Dictionaries | `lang/en.json` and `lang/pt.json`, flat key → string, 932 keys each, identical key sets (parity holds today). 4-space indent. The **last** key in both files is `landing.gmail.note` (no trailing comma), so take care with commas when renaming it. |
| Server-side use of dictionaries | `App\Support\I18n\Translations::for()` reads the same JSON files. `resources/views/app.blade.php` reads `landing.meta.description` and `landing.gmail.note` (→ `$landingEssentials['gmailNote']`), and `resources/views/partials/landing-essentials.blade.php` renders `$gmailNote` twice (noscript + sr-only). |
| `landing.meta.*` | Used in `resources/js/pages/landing.tsx:64-65` (client `<Head>`) and in the Blade root view (description). |
| Hero headline | `hero.tsx` renders `title.a` + `" "` + `<em class="text-accent not-italic">{title.em}</em>` + `title.b`. With D1 (accent on "automatically") the 3-key structure stays as is, and no key is added or removed. |
| Hero demo | `hero-demo.tsx` `HeroDemo()` wraps the grid in `<div ref aria-hidden="true" inert className="grid … xl:demo-compact">`. Copy comes from shared `dashboard.hero.*`, `dashboard.live.*`, `dashboard.stat.*`, `dashboard.activity.*` and `sending.stage.*` keys, which the authenticated app also uses, so they are not touched. Mounted via `<Hero demo={<HeroDemo />} />` in `pages/landing.tsx:90`. |
| Demo sub-step label (spec item 11 heads-up) | **Not rendered on the landing.** `LiveStepper` shows a sub-step only when given the `subStep` prop, and neither `hero-demo.tsx` nor `live-band.tsx` passes it (both only destructure it). `connecting_gmail` in `demo-data.ts:83` is a `SubStep` contract id, not copy. See D4. |
| Plan-card feature ids | `landing-plan-card.tsx:11-15` `FEATURES` uses `'gmail'` for all three plans, giving `t(\`landing.plans.feature.${feature}\`)`. The app's own `features/plans/plan-card.tsx` also has a `'gmail'` id but uses `plans.*` keys, so it is out of scope. |
| `landing.gmail.note` | `cta-banner.tsx:22` + Blade (above). |
| Matching chips | `feature-matching.tsx`: `<Reveal index={3}><ul className="flex flex-wrap gap-2.5">…chips…</ul></Reveal>`. |
| UI building blocks | `Pill` (`components/ui/pill.tsx`, tones `white-on-accent`, `tile`, `dark`, optional lucide `icon`). Theme tokens: `--radius-shell/card/panel`, `--color-card`, `--color-tile`, `--color-hairline`, `--color-canvas`. `lucide-react` is already a dependency. |
| Tests touching these keys | None (`tests/` has no reference to `landing.*` or the essentials partial). |
| Verification commands that exist | `yarn check` (vp check: format + lint), `yarn types:check` (tsc), `composer test` (config:clear, pint --test, phpstan, `php artisan test`), `composer ci:check` (all of the above). `node` is available for a JSON key-parity one-liner. |
| Working tree | Uncommitted, unrelated changes in `resources/js/features/landing/demo-data.ts` (quote-style only), fixtures and other docs. Phases do not need `demo-data.ts`; leave it alone. |
| Acceptance criteria | The spec has no separate AC section. Each item's before/after table and notes are taken as its AC (listed below as AC-0.x and AC-n). |

## Owner decisions

### D1 — Which word keeps the accent color in the new hero headline? — RESOLVED

Blocks: — · Chosen: **A, "automatically" / "automaticamente"** (owner, 2026-10-03). Keeps
the `title.a / .em / .b` split. Only the copy changes, no key is added or removed.

### D2 — How does the hero demo show that it is a product preview? — RESOLVED

Blocks: — · Chosen: **A, framed + labeled** (owner, 2026-10-03). Wrap the demo in a soft
framed panel with a small pill label above the cards. EN "Product preview ·
sample data", PT "Prévia do produto · dados de exemplo". The cards themselves are
unchanged.

### D3 — Copy confirmations in items 6, 7, 9, 10 — RESOLVED

Blocks: — · Chosen: **all as written in the spec** (owner, 2026-10-03). Item 6 "você vê
na hora…", item 7 PT "Exemplo de tag filter" (English term kept), item 9 keeps
the trailing period, item 10 "dispare".

### D4 — Provider-neutral label for the demo's `connecting_gmail` sub-step — RESOLVED (no change)

Blocks: — · The owner chose a landing-only neutral key ("Connecting to your
email" / "Conectando ao seu email"). The audit then found that the landing
**never renders** the sub-step label (see Audit), so the key would be unused,
which breaks Part 0 rule 2 (no orphaned copy). **Plan: no change.** If the owner
later wants the demo to show sub-steps, that is a new spec item (it changes
what the demo renders, not just copy).

## Global constraints (every phase)

- **Both languages** (spec Part 0.1): every new or changed string goes into
  both `lang/en.json` and `lang/pt.json` in the same phase. Key parity must
  hold at the end of every phase.
- **No orphaned keys** (spec Part 0.2): every key a phase renames or stops
  using is removed from both dictionaries in that phase. Never leave an old key
  "just in case".
- Edit dictionary entries **in place**: same position, renamed keys stay where
  the old key was. Keep valid JSON (watch the last-line comma on
  `landing.gmail.note`). Use the exact strings from the contracts below,
  including punctuation, the `·` separator and `:brand` placeholders.
- Landing only. Do not touch `dashboard.*`, `sending.*`, `plans.*`,
  `onboarding.*`, `account.*` or any authenticated-app file. Those keep saying
  Gmail (spec item 11, out of scope).
- Item 8's "agent" wording is landing copy only. No AI feature, no code
  behavior change.
- English only in code and comments. No new dependencies. No tests written or
  modified (CLAUDE.md). No git writes except the per-phase commit made by
  `/execute-phases`.
- No `->poll()` / `wire:poll` / timers introduced (not expected here at all).
- Don't touch the owner's uncommitted changes in `demo-data.ts`, fixtures or
  other docs.

## Acceptance-criteria coverage

| AC | Text (derived from the spec item) | Phases |
| -- | --------------------------------- | ------ |
| AC-0.1 | Every new or changed landing string exists in EN and PT | 1, 2, 3, 4, 5 |
| AC-0.2 | No orphaned landing keys remain in any dictionary | 3, 5 |
| AC-1 | Hero `<h1>` reads the new EN/PT headline, with "automatically"/"automaticamente" in the accent color | 1 |
| AC-2 | The hero demo is clearly a product preview that belongs to the hero (framed + labeled, D2), in both languages | 4 |
| AC-3 | `landing.hero.lead` has the new EN/PT copy | 1 |
| AC-4 | `landing.hero.trust.1` and `.2` have the new copy; `.3` unchanged | 1 |
| AC-5 | `landing.guarantee.1.title` has the new copy; its desc and cards 2–4 unchanged | 1 |
| AC-6 | `landing.matching.text` has the new copy | 1 |
| AC-7 | A secondary caption "Example of tag filter" / "Exemplo de tag filter" appears directly below the chips; chips unchanged | 2 |
| AC-8 | `landing.languages.text` has the new copy | 1 |
| AC-9 | `landing.live.title` has the new copy (trailing period kept); eyebrow and text keys untouched by this item | 1 |
| AC-10 | `landing.live.text` has the new copy | 1 |
| AC-11 | No landing copy names Gmail. `meta.title`, `meta.description`, plan feature and note say email. `plans.feature.gmail` → `plans.feature.email`, `gmail.note` → `email.note` (code + both dictionaries, old keys gone). App copy untouched. | 3 |

## Phases

### Phase 1 — Copy-only replacements (items 1, 3, 4, 5, 6, 8, 9, 10)

Status: PENDING
Role: inertia-frontend · Depends on: none · Covers: AC-0.1, AC-1, AC-3, AC-4, AC-5, AC-6, AC-8, AC-9, AC-10 · Size: S
Spec: Part 0, Part 1 items 1, 3, 4, 5, 6, 8, 9, 10 · Decisions: D1, D3

**Goal.** Swap the values of existing landing keys for the new copy. Only
dictionary values change. No key is added, renamed or removed, and no TSX
changes.

**Contract.** Set exactly these values (key: EN | PT):

- `landing.hero.title.a`: `Applications sent` | `Candidaturas enviadas`
- `landing.hero.title.em`: `automatically` | `automaticamente`
- `landing.hero.title.b`: `, without opening thousands of tabs.` | `, sem abrir milhares de abas.`
  (`hero.tsx` joins them as `a + " " + <em>em</em> + b`, which gives
  "Applications sent **automatically**, without opening thousands of tabs.")
- `landing.hero.lead`: `Optimize the process! We find tech jobs, filter them by what you look for and send your application from your own email.` | `Otimize o processo! Encontramos vagas em tech, filtramos pelo que você procura e enviamos a candidatura do seu próprio Email.`
  (PT capital "Email" is the owner's text, keep it.)
- `landing.hero.trust.1`: `Sent from your email` | `Enviado pelo seu email`
- `landing.hero.trust.2`: `One to one` | `1 a 1`
- `landing.hero.trust.3`: unchanged (`You stay in control` | `Você no controle`)
- `landing.guarantee.1.title`: `Sent from your email` | `Sai do seu email`
  (`landing.guarantee.1.desc` and cards 2–4 unchanged)
- `landing.matching.text`: `Set role, seniority, stack and where you want to work. Filter however you prefer; you instantly see the jobs that fit and send customized applications in bulk.` | `Defina cargo, senioridade, stack e onde quer trabalhar. Filtre como preferir; você vê na hora as vagas que se encaixam e dispare aplicações customizadas em massa.`
- `landing.languages.text`: `CV, subject, cover letter and links in English and Portuguese. Our agent automatically decides the best way to make the application.` | `Currículo, assunto, carta de apresentação e links em inglês e português. Nosso agente define automaticamente a melhor forma de fazer a aplicação.`
- `landing.live.title`: `Follow it step by step.` | `Acompanhe passo-a-passo.`
- `landing.live.text`: `Follow your sends in real time, or just dispatch and relax: we do everything for you, no worrying needed.` | `Acompanhe em tempo real seus envios ou apenas dispare e relaxe, fazemos tudo para você sem precisar se preocupar.`

**Steps.**

1. In `lang/en.json` and `lang/pt.json`, replace the values above in place.
2. Check that `feature-languages.tsx` still renders `landing.languages.text`
   correctly. It splits the text on `JOB_LINK_TOKEN`; the new copy has no
   token, so it must render as one plain text part. Read the component to
   confirm, without changing it.

**Done when.**

- Each key above has exactly the contract value in both files. No other key
  changed.
- Key parity holds: `node -e "const a=require('./lang/en.json'),b=require('./lang/pt.json');const d=Object.keys(a).filter(k=>!(k in b)).concat(Object.keys(b).filter(k=>!(k in a)));if(d.length){console.log(d);process.exit(1)}"`
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Item 7 caption (Phase 2). Any Gmail key outside items
3/4/5 (Phase 3). Hero demo (Phase 4).

### Phase 2 — Caption under the matching chips (item 7)

Status: PENDING
Role: inertia-frontend · Depends on: none · Covers: AC-0.1, AC-7 · Size: S
Spec: Part 0, Part 1 item 7 · Decisions: D3

**Goal.** Show a small secondary caption directly below the four filter chips
in the "Preferences" section.

**Contract.**

- New key `landing.matching.chips.caption`: EN `Example of tag filter` | PT
  `Exemplo de tag filter`. Insert it in both dictionaries right after
  `landing.matching.chip.4`.
- `resources/js/features/landing/feature-matching.tsx`: inside the existing
  `<Reveal index={3}>`, render the `<ul>` (unchanged) and then
  `<p>{t("landing.matching.chips.caption")}</p>` directly below it, e.g.
  wrapped in `<div className="flex flex-col gap-2">`. Caption style is
  secondary text consistent with the section: `text-label-sm text-muted`.
- Chips (`landing.matching.chip.1..4`), the panel and the reveal indexes stay
  as they are.

**Steps.**

1. Add the key to both dictionaries.
2. Add the caption in `feature-matching.tsx`.

**Done when.**

- The caption renders directly under the chips in EN and PT, smaller and
  muted, and doesn't wrap oddly at mobile width.
- Key parity one-liner (Phase 1) passes.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Changing `landing.matching.text` (Phase 1) or the chips.

### Phase 3 — Gmail → email across the landing (item 11)

Status: PENDING
Role: inertia-frontend (also edits two Blade views) · Depends on: none · Covers: AC-0.1, AC-0.2, AC-11 · Size: M
Spec: Part 0, Part 1 item 11 · Decisions: D4

**Goal.** No landing copy names Gmail. Rename the two keys that carry the old
name in both dictionaries and in every place that reads them, and remove the
old keys.

**Contract.**

- Value changes (key: EN | PT):
  - `landing.meta.title`: `:brand — Job applications sent from your own email` | `:brand — Candidaturas enviadas pelo seu próprio email`
  - `landing.meta.description`: `:brand finds developer jobs, filters them by your preferences and sends each application from your own email, one company at a time, in the language of the job.` | `O :brand encontra vagas de desenvolvimento, filtra pelas suas preferências e envia cada candidatura pelo seu próprio email, uma empresa por vez, no idioma da vaga.`
- Renames (old key removed, new key in the same position):
  - `landing.plans.feature.gmail` → `landing.plans.feature.email`: `Sent from your own email` | `Sai do seu próprio email`
  - `landing.gmail.note` → `landing.email.note`: `We only use your email permission to send the applications you choose or allow. We never read your inbox.` | `Usamos a permissão do seu email apenas para enviar as candidaturas que você escolhe ou autoriza. Nunca lemos a sua caixa de entrada.`
    (This is the last key in each file, so no trailing comma.)
- Code that reads them:
  - `resources/js/features/landing/landing-plan-card.tsx`: in `FEATURES`, the
    `'gmail'` id becomes `'email'` for `free`, `starter` and `pro`.
  - `resources/js/features/landing/cta-banner.tsx:22`:
    `t('landing.email.note')`.
  - `resources/views/app.blade.php`: read
    `$landingStrings['landing.email.note']` and rename the essentials entry
    `'gmailNote'` → `'emailNote'`.
  - `resources/views/partials/landing-essentials.blade.php`: `$gmailNote` →
    `$emailNote` (both occurrences).
- Out of scope, do not touch: `features/plans/plan-card.tsx` (its `'gmail'`
  id uses `plans.*`), `sending.sub.connecting_gmail`, the `connecting_gmail`
  id in `demo-data.ts`, and all non-`landing.*` keys. D4: no new demo sub-step
  key.

**Steps.**

1. Update the values and rename the keys in both dictionaries.
2. Update the four code files.
3. Grep for leftovers (see Done when).

**Done when.**

- `grep -n '"landing\.[^"]*": "[^"]*Gmail' lang/en.json lang/pt.json` returns nothing.
- `grep -rnE "landing\.(plans\.feature\.gmail|gmail\.note)|gmailNote" resources app` returns nothing.
- In `landing-plan-card.tsx`, `FEATURES` has no `'gmail'`.
- Key parity one-liner (Phase 1) passes, and both JSON files parse.
- `/` (landing) renders the plan cards' first feature, the CTA note and the
  no-JS essentials block (view source) with the new email copy, and the page
  `<title>` / meta description say "email".
- `yarn check`, `yarn types:check` and `composer test` pass (the Blade views
  are rendered by the existing feature tests, if any).

**Not in this phase.** Hero lead, trust and guarantee Gmail strings (Phase 1).
App-side Gmail copy (out of scope).

### Phase 4 — Hero demo framed as a product preview (item 2)

Status: PENDING
Role: inertia-frontend · Depends on: none · Covers: AC-0.1, AC-2 · Size: S
Spec: Part 0, Part 1 item 2 · Decisions: D2

**Goal.** Make it obvious at a glance that the block under or beside the hero
headline is an illustrative product preview that belongs to the hero, not a
live or broken feature.

**Contract.**

- New key `landing.hero.demo.label`: EN `Product preview · sample data` | PT
  `Prévia do produto · dados de exemplo`. Insert it after
  `landing.hero.trust.3` in both dictionaries.
- `resources/js/features/landing/hero-demo.tsx`, `HeroDemo()` only:
  - Turn the outer `<div>` into a soft framed panel around the cards, like an
    app window or screenshot frame. Use existing theme tokens only, e.g.
    `rounded-shell` (or `rounded-card`), `border border-hairline`, a subtle
    `bg-tile` (or `bg-card/…`) surface, and modest padding (`p-3 md:p-4`).
    No new CSS tokens or utilities unless an existing one can't do it.
  - Above the cards, inside the frame, show
    `<Pill tone="…" icon={Eye}>{t("landing.hero.demo.label")}</Pill>` (lucide
    `Eye` or a similar "preview" icon; pick the tone that contrasts with the
    frame surface).
  - The label sits **outside** the `aria-hidden="true" inert` grid, so screen
    readers announce it while the cards stay hidden and inert. Keep the `ref`,
    `useRegisterDemoInView` wiring and the grid classes
    (`grid grid-cols-1 gap-gap md:grid-cols-5 xl:demo-compact`) on the inner
    grid unchanged.
  - The frame must not look clickable: no hover state, no pointer cursor, no
    focus ring.
- The cards keep their current content and shared `dashboard.*` /
  `sending.stage.*` keys. Nothing is renamed, so there is no key cleanup.
- Layout must still fit the hero grid `xl:grid-cols-[1fr_1.5fr]` and stack
  cleanly on mobile, with no horizontal scroll.

**Steps.**

1. Add the key to both dictionaries.
2. Wrap and label the demo in `hero-demo.tsx`.
3. Check visually at mobile, md and xl widths in EN and PT (the PT label is
   longer).

**Done when.**

- The hero demo shows a framed panel with the "Product preview · sample data"
  pill (PT "Prévia do produto · dados de exemplo") above the cards, in both
  locales.
- The pill text is in the accessibility tree; the cards stay `aria-hidden` +
  `inert`.
- The demo loop still animates only while in view (in-view registration
  unchanged).
- Key parity one-liner (Phase 1) passes.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** The live band (`live-band.tsx`) demo. Rendering
sub-step labels (D4). Any change to `dashboard.*` copy.

### Phase 5 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1, 2, 3, 4 · Covers: all ACs · Size: S
Spec: whole spec

**Goal.** Prove the feature is complete against every AC and hand the owner a
short manual checklist.

**Steps.**

1. Full gate: `composer ci:check` (yarn check, yarn types:check, composer
   test).
2. Dictionary checks:
   - Key parity one-liner (Phase 1).
   - `grep -n '"landing\.[^"]*": "[^"]*Gmail' lang/en.json lang/pt.json`, which must be empty.
   - Orphan check for landing keys: for every `landing.*` key in
     `lang/en.json`, confirm it is referenced in `resources/` or `app/`,
     either literally or through a template prefix
     (`landing.hero.trust.${n}`, `landing.guarantee.${n}.*`,
     `landing.matching.chip.${n}`, `landing.plans.feature.${feature}`,
     `landing.plans.desc.*`, `landing.plans.region.*`, `landing.faq.${n}.*`,
     `landing.nav.*`, `landing.footer.*`, etc.). Report any key that is not
     used.
   - `grep -rnE "landing\.(plans\.feature\.gmail|gmail\.note)|gmailNote" resources app`, which must be empty.
3. Forbidden patterns in the diff: no `->poll(` / `wire:poll`, no hardcoded
   user-facing strings in TSX/Blade (all copy via `t()` / dictionaries), no
   changes outside the landing files and dictionaries listed in Phases 1–4,
   no changes to non-`landing.*` keys.
4. AC walkthrough: tick AC-0.1 … AC-11 against the running landing in EN and
   PT.
5. Report: commands run and results, AC table with pass/fail, and anything
   left over.

**Owner's manual checklist.**

- [ ] `/` in EN and PT: hero headline with "automatically"/"automaticamente"
      in accent, new lead, trust items "Sent from your email · One to one ·
      You stay in control".
- [ ] Hero demo reads as a framed "Product preview · sample data" block that
      belongs to the hero, on mobile and desktop.
- [ ] Guarantees strip, first card: "Sent from your email" / "Sai do seu
      email".
- [ ] Preferences section: new text, plus the "Example of tag filter" caption
      under the chips.
- [ ] Languages section and live band: new copy.
- [ ] Plans cards: "Sent from your own email". CTA note says "email
      permission".
- [ ] Browser tab title and link preview (meta description) say email.
      View source shows the no-JS essentials block with the email note.
- [ ] Authenticated app still says Gmail where it did before.

**Done when.**

- `composer ci:check` passes.
- All dictionary and forbidden-pattern checks are clean.
- Every AC is marked pass in the report.
