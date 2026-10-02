> Status: ARCHIVED (implemented). Historical record only. The code is the source of truth. DO NOT use this document as a reference to implement or change code.

# public-landing — public bilingual landing page (EN/PT)

> **Kind:** frontend-first, small backend (one controller).
> **Depends on:** specs 1–9 DONE (design system, i18n, plans, regional pricing).
> Independent of `production-readiness`, but this page is what Google reviews
> as the app homepage (see Part 0 item 9).
> **New dependencies:** none (CSS + small hooks; no animation library).
> **Migrations:** none.
>
> **How to run:** `/plan-spec docs/features/public-landing/spec.md`, then
> `/execute-phases` (5 phases, one batch). Run `composer lint:check`,
> `composer types:check`, `yarn check`, `yarn types:check` at the end of
> **each** phase and commit per phase.

## Part 0 — Context and decisions (owner, final, 2026-09-30)

The public route `/` today is a placeholder (`resources/js/pages/landing.tsx`:
logo, "closed beta", login button). It becomes the real landing page: premium,
animated, built with the **real product components** in mock/demo mode,
explaining what the product does and why it is good, with pricing, in
English and Portuguese.

Decisions that bind this spec:

1. **Visual truth:** the landing in code. The mockup files were removed from
   the repo. Palette, type, radii, shadows and components are the ones of
   the logged-in app (`resources/css/app.css` tokens, `components/ui`,
   `components/patterns`). Do not invent a second look.
2. **"Ask for access" is not interactive.** Every primary call to action
   (nav, hero, plans, final banner) is a **non-interactive label reading
   `Closed beta`** (same text in EN/PT): no `href`, no `onClick`, not
   focusable, no hover/pressed state, `aria-disabled="true"` (the `Button`
   `inert` prop), styled as the existing primary pill so the layout is
   unchanged. The secondary hero
   button "See how it works" (scroll to section) and **Sign in** stay
   interactive. A config flag (B.3) turns the labels back into real links to
   `/register` when the beta opens.
3. **Brand and logo will change.** Every brand mention comes from
   `config('talent.brand')` via shared props (`app.brand`) and the `Logo`
   pattern. No hardcoded brand name in components, copy or lang files (use a
   `:brand` placeholder in strings).
4. **Bilingual:** the whole product, landing included, is EN and PT only
   (`config('talent.locales') = ['en','pt']`). There is no Spanish anywhere
   (UI, landing, dictionaries, sample content).
5. **Hero uses real components, animated in a loop** (not video or GIF), fed
   by local demo data (B.5). Fictional jobs and companies, decorative
   (`aria-hidden`). Nothing from the real pool, clients or database.
6. **Pricing is read from config, never hardcoded** (`PlanCatalog`,
   `talent.plans.prices`), per region (Brazil / Europe / Global), with the
   region segmented control. A plan whose price is 0 in the region shows
   the localized word for "Free" instead of a price. Final prices are
   owner decisions D-FREEPRICE / D-PRICES (open).
7. **No promises and no fabrication.** No interview or reply-rate claims, no
   testimonials, no customer logos, no invented numbers or "X people use
   it". Every capability claimed on the page must exist in the product today
   (verify while writing copy). Examples are labeled illustrative.
8. **Do not reveal:** job sources, how company contacts are verified, funnel
   or pool numbers, internal limits, where AI is used, provider names.
9. **Google verification:** this page is the public homepage Google
   compares with the `gmail.send` scope. It must state, in plain text
   visible without JavaScript interaction, what the app does and that Gmail
   is used only to send the applications the client chooses or allows, and
   that the inbox is never read (B.7). Links to Privacy, Terms and the
   company opt-out appear in the footer **when those routes exist**
   (`production-readiness`).
10. Hard rules from `CLAUDE.md` apply (git read-only, no tests unless asked,
    English in repo, no polling, scope, Tailwind 4 tokens only, maximum
    componentization in the three layers, no hardcoded UI strings, no
    hardcoded hex/px in components).

## Part B — Product spec

### B.1 Files to read first

`resources/css/app.css`,
`resources/js/pages/landing.tsx`, `resources/js/layouts/*`,
`resources/js/components/{ui,patterns}/*` (especially `hero-card`,
`dark-card`, `live-stepper`, `queue-row`, `countdown-bar`, `activity-row`,
`bar-chart`, `tick-meter`, `segmented`, `tabs`, `pill`, `chip`, `logo`,
`language-switcher`, `nav-pills`, `mobile-nav`, `sheet`),
`resources/js/features/plans/*`, `resources/js/i18n/*`,
`resources/js/types/{shared,contracts}.ts`, `app/Http/Middleware/{HandleInertiaRequests,SetLocale}.php`,
`app/Support/I18n/{LocaleResolver,Translations}.php`,
`app/Http/Controllers/LocaleController.php`, `app/Http/Requests/UpdateLocaleRequest.php`,
`app/Plans/{Plan,PlanCatalog}.php`, `config/talent.php`, `routes/web.php`,
`resources/views/app.blade.php`, `lang/{en,pt}.json`,
`docs/features/{client-app-foundation,regional-pricing-and-billing,production-readiness}/spec.md`.

### B.2 Locales

EN and PT only. `config('talent.locales')` = `['en','pt']` is the single
list used by `LocaleResolver`, `UpdateLocaleRequest`, the account/onboarding
requests, the Filament user form and the shared `locales` prop. The
`Locale` type is `'en' | 'pt'`. The nav `LanguageSwitcher` is the same
component used in the app. A signed-in user who opens `/` sees the landing
in their locale.

### B.3 Backend

- Replace `Route::inertia('/', 'landing')` with `LandingController`
  (invokable, `name('home')` kept). It returns the Inertia `landing` page
  with props:
    - `plans`: the three plans with `key`, `mode`, `dailyLimit`,
      `highlighted` (`PlanCatalog::isHighlighted`) and `prices` for the
      three regions (minor units + currency code), built from
      `PlanCatalog`/config (reuse what `GET /internal/plans` uses; do not
      duplicate the price rules).
    - `defaultRegion`: `br` | `eu` | `row` from `Accept-Language` region
      subtag via `RegionResolver::fromCountry` (`pt-BR`→`br`; EU/EEA country
      codes→`eu`; else `row`). No geo-IP service.
    - `betaClosed: bool` from `config('talent.landing.beta_closed')`
      (`LANDING_BETA_CLOSED`, default `true`).
    - `contactEmail`: `config('talent.contact_email')` (env
      `TALENT_CONTACT_EMAIL`); null when empty, and the footer omits it.
    - `legal`: `{ privacy, terms, optOut }`, each the URL of the named route
      `privacy`, `terms`, `opt-out`, or null when the route does not exist
      (`Route::has`); a null link is hidden.
- Page must be publicly cacheable-safe: no per-user data except the
  existing shared `auth.user` (used only to swap "Sign in" for "Open
  dashboard").
- When `betaClosed=false`, the CTA label becomes a real link to `register`
  ("Get started" strings, B.9); when true, the `Closed beta` label (Part 0
  item 2).

### B.4 Design tokens for the marketing scale

The app scale (`text-display` 56px) is smaller than the landing needs. Add
**tokens only** to `@theme` in `app.css` (no component-level values):
`--text-landing-hero` (88px, line-height .98, letter-spacing -0.055em,
weight 500), `--text-landing-section` (64px, 1.02, -0.05em, 500),
`--text-landing-cta` (72px), `--text-landing-lead` (21px, 1.5),
`--text-landing-body` (19px, 1.55), each with a `-sm` mobile variant
(hero 46px, section 38px, cta 40px). Reuse all existing colors, radii,
spacing and shadows. Keep every new value in `app.css`.

### B.5 Frontend structure

`layouts/public-layout.tsx`: canvas background (`bg-shell`), `header` and
`footer` slots around a centered `main` (`max-w-shell`), `useFlashToasts`,
syncs `<html lang>` with the resolved locale. No app shell, no auth-guard
logic.

Files under `resources/js/features/landing/` (one component per file):

- `landing-nav.tsx`: sticky header with `Logo`, pill nav (`nav-pills`, links
  scroll to section ids via `scroll-to-hash.ts`), `LanguageSwitcher`, Sign in
  (or Open dashboard when signed in; ≥lg), `BetaCta` (≥sm) and, below lg, a
  menu button opening a `Sheet` with the section links and Sign in.
- `beta-cta.tsx`: the single component behind every primary CTA. Renders the
  `Closed beta` label (`Button` `inert`, Part 0 item 2) or a real link to
  `register` when `betaClosed=false`. Used in nav, hero, live band, plan
  cards and the final banner.
- `hero.tsx` + `hero-demo.tsx` (B.6).
- `guarantees-strip.tsx` (4 `FeatureTile`s).
- `feature-matching.tsx`, `feature-languages.tsx`, `live-band.tsx`.
- `pricing.tsx` + `landing-plan-card.tsx`.
- `faq.tsx`, `cta-banner.tsx`, `landing-footer.tsx`.
- `lazy-section.tsx` (lazy mounting, B.8), `scroll-to-hash.ts`.
- Demo and motion: `demo-data.ts`, `use-demo-loop.ts`,
  `demo-loop-context.tsx`, `use-in-view.ts`, `use-reveal.ts`,
  `use-count-up.ts`.

`pages/landing.tsx` composes them: `Hero`, `GuaranteesStrip` eagerly; the
rest as `React.lazy` sections inside `LazySection`. Section ids: `product`,
`how`, `languages`, `plans`, `faq`. One `DemoLoopProvider` wraps hero,
guarantees, preferences, languages and live band so the demo numbers agree.

**Demo mode rules (hero and live band):**

- Presentational patterns only; the public page must not call any
  `/internal/*` endpoint, subscribe to any channel or read `data/fixtures`
  boot code.
- `demo-data.ts`: fictional jobs (Northwind Systems, Lumen Data, Via Cloud
  style names; no real companies), fictional candidate ("Ana Silva"), the
  sample emails (EN and PT) and the CV file name. Sample content is fixed
  and independent of the UI language.
- `use-demo-loop`: a small state machine (timeouts cleaned up on unmount)
  that cycles: queue advances, the stepper moves through the 5 real stages,
  the countdown runs, "sent today" increments (18 → 19 → …, wraps after a
  cycle), new "sent" rows appear in Recent activity.
- `prefers-reduced-motion`: one static frame, no timers. Pause when
  `document.hidden`. Runs only while the hero demo or live band is in view
  (`use-in-view`, `IntersectionObserver`).
- Demo panels are `aria-hidden` and `inert`; the section copy is their text
  alternative. Stage, "Live", "Sent" and dashboard labels reuse the app's
  existing keys.

### B.6 Sections (order, content, behavior)

1. **Nav**: see above.
2. **Hero**: badge "Closed beta · invite-only access" (lock icon); H1 (3
   parts, the middle one in the accent color); lead; `BetaCta` + secondary
   "See how it works" (smooth scroll to `#how`); three trust lines. Right
   side (demo): `HeroCard` (sent today N/50 with the week bars and
   queued / not delivered / next-send stats), `DarkCard` "Live sending"
   (current job, `LiveStepper`, queue rows), limit `StatTile` (`TickMeter`),
   Recent activity (`ActivityRow`, two rows). On tablet/mobile the text comes
   first, then the cards stacked.
3. **Guarantees strip**: 4 tiles, title and one line each (B.9 copy), with
   a screen-reader-only heading. No fake logos.
4. **Preferences** (`#product`): eyebrow, title, text, four static chips,
   and a static "New jobs" panel with three fictional `JobRow`s (first
   pre-selected), the selected-count line and a "Send 1 application" button
   that is non-interactive (same `inert` treatment as `Closed beta`).
5. **Languages** (`#languages`): text + interactive `Tabs` (English /
   Portuguese; the only interactive demo, it switches local preview state).
   Each tab shows the sample CV file row and the sample email with the
   "job link" chip.
6. **Live band** (`#how`): dark `Card` with text and `BetaCta` on one side
   and, on the other, a demo panel: current job, `LiveStepper` and three
   count-up tiles (sent today, queued, not delivered), all driven by the demo
   loop.
7. **Plans** (`#plans`): eyebrow, title, region `Segmented` control
   (default from `defaultRegion`, changing it only changes displayed prices,
   kept in component state), three cards from props. Card: plan name (the
   app's `plans.<key>.name`); "Recommended" `Pill` when `highlighted`; price
   (`useFormat().currency` with the region currency, plus the app's
   `plans.per_month`) or the localized "Free" when the amount is 0;
   "{n} applications per day" from `dailyLimit`; one-line description by
   `mode` (`random`→auto / select / review); 3 bullet features (free:
   gmail, filtered, profiles; starter: gmail, select, profiles; pro: gmail,
   review, profiles); `BetaCta`. The highlighted card gets the accent
   outline and comes first below lg. Descriptions and bullets never mention
   internal mechanisms.
8. **FAQ** (`#faq`): 5 items (B.9), accessible accordion (buttons with
   `aria-expanded`/`aria-controls`, several items can be open, the first is
   open by default, height animated with the CSS grid-rows trick). The
   opt-out item renders only when `legal.optOut` is set.
9. **CTA banner**: headline, `BetaCta`, then the Gmail-use sentence, on the
   `bg-hero` accent banner.
10. **Footer**: brand, contact email (if set), Privacy / Terms / Opt-out
    (each only if its URL exists). The language switcher lives in the nav.

### B.7 SEO, no-JS essentials and Google requirements

- `<Head>`: localized `title` and `description`, Open Graph
  (`og:title`, `og:description`, `og:type=website`, `og:locale`), canonical
  URL, `<html lang>` follows the resolved locale (already handled by the
  layout).
- `app.blade.php`: for the `landing` page only, add a `<noscript>` block
  and a visually-hidden-when-JS server-rendered block with the brand name,
  the one-paragraph description and the Gmail-use statement in the resolved
  locale (Laravel `__()` is not used for the SPA strings, so read the three
  strings from `Translations::for(locale)`). No SSR Node process
  (decision D-SSR: revisit only if SEO becomes a goal).
- No third-party scripts, fonts or trackers. Geist stays self-hosted.
- Add `public/og-landing.png` (1200×630) only if the owner supplies it;
  otherwise omit `og:image` (do not generate a fake one).

### B.8 Motion and performance

- Motion vocabulary: reveal-on-scroll (fade + 12px translate, 350 ms,
  staggered 60 ms), the demo loop, number count-ups on first view, FAQ height
  animation, hover lift on cards (transform only). CSS keyframes/transitions
  as `@theme` `--animate-*` tokens where new; no library.
- All motion off under `prefers-reduced-motion` (the global rule in
  `app.css` already neutralizes animations; reveal hooks must also skip).
- Below-the-fold sections mount lazily (`React.lazy` inside `LazySection`,
  which mounts on approach via `use-in-view` or when an anchor asks for every
  section, with a fixed min-height placeholder to avoid layout shift). Target: LCP under
  2.5 s on a mid phone with cache off, no layout shift from lazy sections
  (CLS < 0.05). Images: none required; icons are `lucide-react` as in the
  app.
- No `setInterval` fetching; timers exist only inside the demo loop and are
  cleaned up.

### B.9 Copy (EN and PT, source of truth)

All strings go to `lang/{en,pt}.json` under `landing.*` (`:brand`, `:n` are
interpolation placeholders; use the existing `translate.ts` interpolation).
Where the app already has a key for the same concept (the five stage labels,
plan names, `plans.per_month`, "Live", "Sent"), reuse it instead of
duplicating. The sample email content is not a dictionary key; it lives in
`demo-data.ts`.

| Key                                                       | EN                                                                                                                                                               | PT                                                                                                                                                                               |
| --------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `meta.title`                                              | :brand — Job applications sent from your own Gmail                                                                                                               | :brand — Candidaturas enviadas pelo seu próprio Gmail                                                                                                                            |
| `meta.description`                                        | :brand finds developer jobs, filters them by your preferences and sends each application from your own Gmail, one company at a time, in the language of the job. | O :brand encontra vagas de desenvolvimento, filtra pelas suas preferências e envia cada candidatura pelo seu próprio Gmail, uma empresa por vez, no idioma da vaga.              |
| `nav.product`                                             | Product                                                                                                                                                          | Produto                                                                                                                                                                          |
| `nav.how`                                                 | How it works                                                                                                                                                     | Como funciona                                                                                                                                                                    |
| `nav.languages`                                           | Languages                                                                                                                                                        | Idiomas                                                                                                                                                                          |
| `nav.plans`                                               | Plans                                                                                                                                                            | Planos                                                                                                                                                                           |
| `nav.faq`                                                 | FAQ                                                                                                                                                              | FAQ                                                                                                                                                                              |
| `nav.signin`                                              | Sign in                                                                                                                                                          | Entrar                                                                                                                                                                           |
| `nav.dashboard`                                           | Open dashboard                                                                                                                                                   | Abrir painel                                                                                                                                                                     |
| `cta.closed`                                              | Closed beta                                                                                                                                                      | Closed beta                                                                                                                                                                      |
| `cta.open`                                                | Get started                                                                                                                                                      | Começar                                                                                                                                                                          |
| `hero.badge`                                              | Closed beta · invite-only access                                                                                                                                 | Beta fechada · acesso por convite                                                                                                                                                |
| `hero.title.a` / `.em` / `.b`                             | Applications sent by / you / , without opening 40 tabs.                                                                                                          | Candidaturas enviadas por / você / , sem abrir 40 abas.                                                                                                                          |
| `hero.lead`                                               | We find development jobs, filter them by what you look for and send your application from your own Gmail, one company at a time, in the job's language.          | Encontramos vagas de desenvolvimento, filtramos pelo que você procura e enviamos a candidatura do seu próprio Gmail, uma empresa por vez, no idioma da vaga.                     |
| `hero.secondary`                                          | See how it works                                                                                                                                                 | Ver como funciona                                                                                                                                                                |
| `hero.trust.1` / `.2` / `.3`                              | Sent from your Gmail / One email per company / You stay in control                                                                                               | Sai do seu Gmail / 1 e-mail por empresa / Você no controle                                                                                                                       |
| `guarantee.title` (screen-reader heading)                 | Our guarantees                                                                                                                                                   | Nossas garantias                                                                                                                                                                 |
| `guarantee.1.title` / `.desc`                             | Sent from your Gmail / You appear as yourself                                                                                                                    | Sai do seu Gmail / Você aparece como você                                                                                                                                        |
| `guarantee.2.title` / `.desc`                             | One per company / Never repeats a send                                                                                                                           | Uma por empresa / Nunca repete o envio                                                                                                                                           |
| `guarantee.3.title` / `.desc`                             | Paced sending / One at a time, no bursts                                                                                                                         | Envio espaçado / Um por vez, sem rajada                                                                                                                                          |
| `guarantee.4.title` / `.desc`                             | You're in control / Pause and review whenever you want                                                                                                           | Você no controle / Pausa e revisa quando quiser                                                                                                                                  |
| `matching.eyebrow`                                        | Preferences                                                                                                                                                      | Preferências                                                                                                                                                                     |
| `matching.title`                                          | Only the jobs that match.                                                                                                                                        | Só as vagas que combinam.                                                                                                                                                        |
| `matching.text`                                           | Set role, seniority, stack and where you want to work. Any value inside a field matches; across fields, all must match. You see how many jobs fit right away.    | Defina cargo, senioridade, stack e onde quer trabalhar. Dentro de cada campo vale qualquer valor; entre os campos, todos precisam bater. Você vê na hora quantas vagas encaixam. |
| `matching.chip.1` … `.4`                                  | Backend · Senior · Laravel · Remote or Lisbon                                                                                                                    | Backend · Sênior · Laravel · Remoto ou Lisboa                                                                                                                                    |
| `matching.panel.title` / `.count` / `.selected` / `.send` | New jobs / 11 match you / 1 selected · 49 sends left today / Send 1 application                                                                                  | Novas vagas / 11 combinam com você / 1 selecionada · 49 envios restantes hoje / Enviar 1 candidatura                                                                             |
| `languages.eyebrow`                                       | Languages                                                                                                                                                        | Idiomas                                                                                                                                                                          |
| `languages.title`                                         | One profile per language.                                                                                                                                        | Um perfil para cada idioma.                                                                                                                                                      |
| `languages.text`                                          | CV, subject and cover letter in English and Portuguese. A Portuguese job gets your Portuguese profile, and you see the email ready before anything is sent.      | Currículo, assunto e carta de apresentação em inglês e português. A vaga em português recebe o seu perfil em português, e você vê o e-mail pronto antes de qualquer envio.       |
| `live.eyebrow`                                            | Live sending                                                                                                                                                     | Envio ao vivo                                                                                                                                                                    |
| `live.title`                                              | You see every step happen.                                                                                                                                       | Você vê cada passo acontecer.                                                                                                                                                    |
| `live.text`                                               | Every application shows the real stage it's in. If something fails, you see where and why, no black boxes. No refresh needed, everything updates on its own.     | Cada candidatura mostra a etapa real em que está. Se algo falha, você vê onde e por quê, sem caixa-preta. Sem F5, tudo atualiza sozinho.                                         |
| `plans.eyebrow`                                           | Plans                                                                                                                                                            | Planos                                                                                                                                                                           |
| `plans.title`                                             | Choose how much control you want.                                                                                                                                | Escolha quanto controle você quer ter.                                                                                                                                           |
| `plans.region.label`                                      | Prices for                                                                                                                                                       | Preços para                                                                                                                                                                      |
| `plans.region.br` / `.eu` / `.row`                        | Brazil / Europe / Global                                                                                                                                         | Brasil / Europa / Global                                                                                                                                                         |
| `plans.free`                                              | Free                                                                                                                                                             | Grátis                                                                                                                                                                           |
| `plans.per_day`                                           | :n applications per day                                                                                                                                          | :n candidaturas por dia                                                                                                                                                          |
| `plans.recommended`                                       | Recommended                                                                                                                                                      | Recomendado                                                                                                                                                                      |
| `plans.desc.auto`                                         | Sends within your preferences.                                                                                                                                   | Envia dentro das suas preferências.                                                                                                                                              |
| `plans.desc.select`                                       | You choose which jobs to apply to.                                                                                                                               | Você escolhe para quais vagas aplicar.                                                                                                                                           |
| `plans.desc.review`                                       | You review and edit each email before it goes.                                                                                                                   | Você revisa e edita cada e-mail antes de sair.                                                                                                                                   |
| `plans.feature.gmail`                                     | Sent from your own Gmail                                                                                                                                         | Sai do seu próprio Gmail                                                                                                                                                         |
| `plans.feature.filtered`                                  | Jobs filtered by your preferences                                                                                                                                | Vagas filtradas pelas suas preferências                                                                                                                                          |
| `plans.feature.select`                                    | Choose exactly what to send                                                                                                                                      | Escolha exatamente o que enviar                                                                                                                                                  |
| `plans.feature.review`                                    | Review and edit each email                                                                                                                                       | Revise e edite cada e-mail                                                                                                                                                       |
| `plans.feature.profiles`                                  | Profiles in EN and PT                                                                                                                                            | Perfis em EN e PT                                                                                                                                                                |
| `faq.eyebrow` / `faq.title`                               | FAQ / Frequently asked questions                                                                                                                                 | FAQ / Perguntas frequentes                                                                                                                                                       |
| `faq.1.q` / `.a`                                          | Do you read my inbox? / No. We only ask Google for permission to send emails. We never read, list or delete your messages.                                       | Vocês leem a minha caixa de entrada? / Não. Pedimos ao Google apenas a permissão de enviar e-mails. Nunca lemos, listamos ou apagamos mensagens.                                 |
| `faq.2.q` / `.a`                                          | Can I send twice to the same company? / No. Each company receives at most one application from you.                                                              | Posso enviar duas vezes para a mesma empresa? / Não. Cada empresa recebe no máximo uma candidatura sua.                                                                          |
| `faq.3.q` / `.a`                                          | What if I want to review before sending? / Choose the plan with review mode: you read and edit each email before it leaves.                                      | E se eu quiser revisar antes de enviar? / Escolha o plano com modo de revisão: você lê e edita cada e-mail antes de ele sair.                                                    |
| `faq.4.q` / `.a`                                          | Do you guarantee interviews or replies? / No. We send your application; replies depend on the company and your profile.                                          | Vocês garantem entrevistas ou respostas? / Não. Nós enviamos a sua candidatura; a resposta depende da empresa e do seu perfil.                                                   |
| `faq.5.q` / `.a` (only if `legal.optOut`)                 | How can a company ask not to receive applications? / Through the opt-out page linked in the footer. Once confirmed, the company stops receiving applications.    | Como uma empresa pede para não receber candidaturas? / Pela página de opt-out no rodapé. Depois de confirmado, a empresa deixa de receber candidaturas.                          |
| `cta.title`                                               | Start with invite-only access.                                                                                                                                   | Comece com acesso por convite.                                                                                                                                                   |
| `gmail.note`                                              | We only use your Gmail permission to send the applications you choose or allow. We never read your inbox.                                                        | Usamos a permissão do seu Gmail apenas para enviar as candidaturas que você escolhe ou autoriza. Nunca lemos a sua caixa de entrada.                                             |
| `footer.privacy` / `.terms` / `.optout`                   | Privacy / Terms / Opt-out for companies                                                                                                                          | Privacidade / Termos / Opt-out para empresas                                                                                                                                     |
| `footer.contact`                                          | Contact                                                                                                                                                          | Contato                                                                                                                                                                          |

The old `landing.status`, `landing.subtitle`, `landing.login` and
`landing.dashboard` keys no longer exist.

The "Sent"/"Live"/stage labels and plan names inside the demo components
reuse the existing app keys (EN/PT).

### B.10 Responsive rules

- ≥1280: two columns in hero and features.
- 768–1279: hero stacks (text, then cards in a 2-column grid), features
  stack, pricing 3 columns tightened or 1+2, live band stacked.
- <768: single column, `text-landing-*-sm` tokens, guarantees strip 2×2,
  pricing cards stacked with the recommended plan first, region control
  full width, nav collapses (logo, language, menu `Sheet` holding the links
  and Sign in).
- No horizontal page scroll at 360 px. Touch targets ≥ 44 px
  (`control-xs`).

### B.11 Accessibility

Semantic landmarks (`header`, `main`, `section` with `aria-labelledby`,
`footer`), one `h1`, ordered headings, visible focus (`focus-visible:focus-ring`
as in the app), FAQ buttons with `aria-expanded`/`aria-controls`, tabs with
proper roles (existing `Tabs`), region control with radiogroup semantics (existing
`Segmented`; add the roles/keyboard support there if it lacks them, without changing app behavior), the demo panels `aria-hidden="true"` with a text alternative
in the section copy (they are decorative), `Closed beta` is
`aria-disabled="true"` and skipped in tab order, contrast AA on the accent
banner (black text on accent), `lang` attributes on the
sample email blocks.

## Acceptance criteria

- **AC01** `/` renders the landing in EN and PT; the nav language switcher
  changes it without reload and persists in the `locale` cookie. The
  switcher lists only English and Portuguese.
- **AC02** Every "ask for access" button reads `Closed beta`, is not
  clickable, not focusable, has no hover state; Sign in and "See how it
  works" work. With `LANDING_BETA_CLOSED=false` the CTAs become real links
  to `/register`.
- **AC03** No hardcoded UI strings, no raw keys, no mixed-language text on
  the page in any locale (demo labels included).
- **AC04** Brand name and logo change by editing config/brand source only
  (change it once and grep the page for the old name: no occurrences).
- **AC05** Pricing values come from config, switch by region and show the
  localized "Free" for a zero price.
- **AC06** The hero and live band run on real components in demo mode with
  fictional data, loop smoothly, pause when hidden/out of view, and are
  static under `prefers-reduced-motion`. The network tab shows no
  `/internal/*` calls, no websocket, and no polling on `/`.
- **AC07** The visual result uses the app's palette, type and components at
  1440 px, and adapts per B.10 down to 360 px with no horizontal scroll.
- **AC08** The page states what the app does and how Gmail is used (B.7,
  visible with JavaScript disabled) in the resolved locale; Privacy, Terms
  and Opt-out links appear only when their routes exist.
- **AC09** No fabricated proof anywhere: no testimonials, customer logos,
  user counts, response rates; nothing from the "do not reveal" list.
- **AC10** Lighthouse (mobile) on `/`: Performance ≥ 90, Accessibility ≥ 95,
  Best Practices ≥ 95, SEO ≥ 95; CLS < 0.05.
- **AC11** In-app screens are unchanged (`composer lint:check`, `composer
types:check`, `yarn check`, `yarn types:check` pass; the app locale menu,
  onboarding and account still behave as before).
- **AC12** The `production-readiness` doc edits of Phase 1 are applied.

## Phases

1. **Foundations** — locale plumbing (B.2), `LandingController` + props
   (B.3), tokens (B.4), `PublicLayout`, `BetaCta`, nav/footer shells, page
   composition skeleton, doc edit: `production-readiness/spec.md` B.4
   ("the homepage is delivered by `public-landing`; that section then only
   owns Privacy, Terms and the opt-out page") and AC04 (landing content
   owned by `public-landing`).
2. **Hero** — demo data, `use-demo-loop`, hero with real components,
   guarantees strip, reveal/in-view hooks, pattern extractions if any
   pattern is data-coupled.
3. **Feature sections** — preferences, languages (interactive tabs), live
   band.
4. **Pricing, FAQ, CTA, footer** — pricing from props with region control,
   FAQ accordion, CTA banner with Gmail note, footer with conditional
   links, SEO/`Head`, no-JS block in the blade view (B.7).
5. **Copy, polish, verification** — final EN/PT strings (B.9), responsive pass (B.10), a11y pass
   (B.11), reduced motion, lazy loading, performance and Lighthouse (AC10),
   check every acceptance criterion.

## Verification

`composer lint:check`, `composer types:check`, `yarn check`,
`yarn types:check`, existing tests. Manual: open `/` in both locales at
1440, 768 and 360 px; sign in and confirm the app locale menu is EN/PT only
and `/` still shows the right locale; switch region on pricing; toggle
`LANDING_BETA_CLOSED`; disable JavaScript and confirm the description and
Gmail statement; enable "reduce motion" in the OS; inspect the network tab
(no `/internal`, no websocket); run Lighthouse.

## Open decisions (owner)

- **D-BRAND / D-DOMAIN:** final brand, logo and domain (nothing here blocks
  on it; all brand text is placeholder-driven).
- **D-FREEPRICE / D-PRICES / D-TAX:** Free is meant to show as "Grátis"/"Free";
  today the config default for Free is a paid amount (`PRICE_FREE_*`).
  Landing follows config; owner sets the real values.
- **D-SSR:** server rendering of the landing for SEO (adds a Node process).
  Default: no.
- **D-OG:** provide `og-landing.png` (1200×630) if wanted.
- Interactive hero (visitor picks language/plan and the panel reacts):
  not in this spec; passive demo only.

## Out of scope

Real signup flow while the beta is closed, Privacy/Terms/Opt-out pages
(`production-readiness`), blog/changelog, pricing checkout, analytics or
tracking scripts, A/B tests, testimonials/case studies, translating the
logged-in app, SSR, video or GIF assets, any data from the real pool.
