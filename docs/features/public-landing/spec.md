# public-landing — public multilingual landing page (EN/PT/ES)

> **Kind:** frontend-first, small backend (locale plumbing, one controller).
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
English, Portuguese and Spanish.

Decisions that bind this spec:

1. **Visual truth:** `reference/landing-mockup.png` and
   `reference/landing-mockup.html` (Portuguese render). Reproduce structure,
   spacing and hierarchy. Palette, type, radii, shadows and components are
   the ones of the logged-in app (`resources/css/app.css` tokens,
   `components/ui`, `components/patterns`). Do not invent a second look.
   Reference notes: `reference/landing-concept.md`.
2. **"Ask for access" is not interactive.** Every primary call to action
   (nav, hero, plans, final banner) is a **non-interactive label reading
   `Close beta`** (same text in EN/PT/ES): no `href`, no `onClick`, not
   focusable, no hover/pressed state, `aria-disabled="true"`, styled as the
   existing primary pill so the layout is unchanged. The secondary hero
   button "See how it works" (scroll to section) and **Sign in** stay
   interactive. A config flag (B.3) turns the labels back into real links to
   `/register` when the beta opens.
3. **Brand and logo will change.** Every brand mention comes from
   `config('talent.brand')` via shared props (`app.brand`) and the `Logo`
   pattern. No hardcoded brand name in components, copy or lang files (use a
   `{brand}` placeholder in strings).
4. **Multilingual:** EN, PT, ES. Spanish does **not** exist in the app UI
   today (`config('talent.locales') = ['en','pt']`). Decision: add `es`
   for **public pages only**; the logged-in app stays EN/PT until a later
   spec translates it (B.2).
5. **Hero uses real components, animated in a loop** (not video or GIF), fed
   by local demo data (B.5). Fictional jobs and companies; the page says it
   is an illustrative demo. Nothing from the real pool, clients or database.
6. **Pricing is read from config, never hardcoded** (`PlanCatalog`,
   `talent.plans.prices`), per region (Brazil / Europe / Global), with the
   region segmented control from the mockup. A plan whose price is 0 in the
   region shows the localized word for "Free" instead of a price. The
   mockup values ("Grátis", R$ 50, R$ 100) are illustrative; final prices
   are owner decisions D-FREEPRICE / D-PRICES (open).
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

`reference/*` (this feature), `resources/css/app.css`,
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

### B.2 Locales: add Spanish for public pages

- `config/talent.php`: keep `locales` = `['en','pt','es']` (everything that
  can be resolved/cookied) and add `app_locales` = `['en','pt']` (locales the
  logged-in app supports). Add a comment explaining the split.
- `LocaleResolver`: when the request has an authenticated user, only
  `app_locales` are candidates (cookie or Accept-Language `es` must not put
  the app in Spanish); for guests all `locales` are candidates.
- `UpdateLocaleRequest` / `LocaleController`: guests may set any of
  `locales`; authenticated users only `app_locales` (validation error
  otherwise). Persisting `users.locale` never stores `es`.
- Shared props: keep `locales` (all) for public pages and add `appLocales`;
  the in-app `LanguageSwitcher` / `useLocaleEntries` use `appLocales`, the
  landing switcher uses `locales`. Update `types/shared.ts` and the `Locale`
  type in `contracts.ts` to `'en' | 'pt' | 'es'`; `intlLocale` gets `es: 'es-ES'`.
- `lang/es.json`: new file with **only** the `landing.*`, `locale.es`,
  `locale.*` and switcher keys. `Translations::for` already falls back to
  English for missing keys, so the app never shows raw keys. Add `locale.es`
  ("Español") to `en.json` and `pt.json`.
- A signed-in user who opens `/` sees the landing in their app locale
  (`en`/`pt`) as today.

### B.3 Backend

- Replace `Route::inertia('/', 'landing')` with `LandingController`
  (invokable, `name('home')` kept). It returns the Inertia `landing` page
  with props:
    - `plans`: the three plans with `key`, `mode`, `dailyLimit` and `prices`
      for the three regions (minor units + currency code), built from
      `PlanCatalog`/config (reuse what `GET /internal/plans` uses; do not
      duplicate the price rules).
    - `defaultRegion`: `br` | `eu` | `row` from `Accept-Language` region
      subtag (`pt-BR`→`br`; EU/EEA country codes→`eu`; else `row`). No geo-IP
      service.
    - `betaClosed: bool` from `config('talent.landing.beta_closed')`
      (`LANDING_BETA_CLOSED`, default `true`).
    - `contactEmail`: `config('talent.contact_email')` (add the key, env
      `TALENT_CONTACT_EMAIL`, default null; the footer omits it when null).
    - `legal`: `{ privacy: bool, terms: bool, optOut: bool }` = whether the
      named routes `privacy`, `terms`, `opt-out` exist (`Route::has`).
- Page must be publicly cacheable-safe: no per-user data except the
  existing shared `auth.user` (used only to swap "Sign in" for "Open
  dashboard").
- When `betaClosed=false`, the CTA label becomes a real link to `register`
  ("Get started" strings, B.9); when true, the `Close beta` label (Part 0
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

New layout `layouts/public-layout.tsx`: canvas background (`bg-shell`),
`max-w-container` centered (mockup uses 1312px content width), top pill nav,
footer slot, `useFlashToasts`. No app shell, no auth-guard logic.

New files under `resources/js/features/landing/` (one component per file,
patterns before ad-hoc markup; if a needed visual repeats it becomes a
`patterns/` component):

- `landing-nav.tsx` (pill nav from `nav-pills`; links scroll to section ids;
  `LanguageSwitcher` in public mode; Sign in; `BetaBadgeCta`; mobile:
  logo + language + Sign in + menu `Sheet` using the `mobile-nav` pattern).
- `beta-cta.tsx` — the single component behind every primary CTA. Renders
  the `Close beta` label (non-interactive, Part 0 item 2) or a real link
  when `betaClosed=false`. Used in nav, hero, plans and final banner. Uses
  the existing `Button` visuals through a prop/variant, not copied classes.
- `hero.tsx` + `hero-demo.tsx` (B.6).
- `guarantees-strip.tsx` (4 tiles).
- `feature-matching.tsx` (text + chips + "New jobs" panel with `JobRow`-style
  rows).
- `feature-languages.tsx` (real `Tabs`: English/Portuguese/Spanish; switching
  swaps the email preview language; CV file row; the "job link" chip as in
  the app's template preview).
- `live-band.tsx` (full-width `DarkCard` band, `LiveStepper`, sub-step line,
  `CountdownBar`, three stat tiles; animated by the demo loop).
- `pricing.tsx` (`Segmented` region control; `PlanCard`-style cards from
  props; middle plan highlighted with the accent border + "Recommended"
  `Pill`; note line when `pricesAreIllustrative`).
- `faq.tsx` (accessible accordion: buttons with `aria-expanded`, first item
  open by default, animated height via CSS grid-rows trick).
- `cta-banner.tsx` (accent gradient banner using `bg-hero`).
- `landing-footer.tsx` (brand, contact, Privacy/Terms/Opt-out when `legal`
  says so).
- `use-demo-loop.ts`, `demo-data.ts`, `use-reveal.ts`, `use-in-view.ts`.

`pages/landing.tsx` composes them. Section ids: `product`, `how`,
`languages`, `plans`, `faq`.

**Demo mode rules (hero and live band):**

- Presentational patterns only. If a pattern reads data hooks, TanStack
  Query, Echo or Inertia props, extract the presentational part first (small
  refactor inside the pattern layer, app behavior unchanged). The public
  page must not call any `/internal/*` endpoint, subscribe to any channel or
  read `data/fixtures` boot code.
- `demo-data.ts`: fictional jobs (Northwind Systems, Lumen Data, Via Cloud
  style names; no real companies), fictional candidate ("Ana Silva"), typed
  with the existing contracts where they fit.
- `use-demo-loop`: a small state machine (`useReducer` + timeouts, cleaned
  up on unmount) that cycles: queue advances, the stepper moves through the
  5 real stages, the sub-step line changes, the countdown runs, "sent today"
  increments (18 → 19 → …, wraps after a cycle), new "sent" rows appear in
  Recent activity. One shared loop instance drives hero and live band so
  numbers agree.
- `prefers-reduced-motion`: render one static frame, no timers. Pause when
  `document.hidden`. Start when the component is in view, stop when it
  leaves (`use-in-view`, `IntersectionObserver`).
- A visible, small, muted caption near each demo: "Illustrative demo with
  fictional jobs and companies." (B.9).

### B.6 Sections (order, content, behavior)

1. **Nav** — see above. Sticky at top with the pill style of the mockup.
2. **Hero** — badge "Closed beta · invite-only access"; H1 (3 parts, the
   middle one accented in the accent color); lead; CTAs: `BetaCta` +
   secondary "See how it works" (smooth scroll to `#how`, respects reduced
   motion); three small trust lines. Right side: orange `HeroCard` (sent
   today N/50 with hatched `BarChart`), `DarkCard` "Live sending"
   (`LiveStepper` + queue rows), limit tile (`TickMeter`), Recent activity
   (`ActivityRow`). All demo-driven. On tablet/mobile the text comes first,
   then the cards stacked.
3. **Guarantees strip** — 4 tiles, one line each (B.9 copy). Replaces a
   logo strip; no fake logos.
4. **Preferences** (`#product`) — eyebrow, title, text, four removable-looking
   chips (static, illustrative), "New jobs" panel with three fictional rows
   (one pre-selected), "Send 1 application" button (static, not
   interactive in this page: purely visual; use the same non-interactive
   treatment as `Close beta` — no fake working buttons).
5. **Languages** (`#languages`) — text + interactive `Tabs` (this one **is**
   interactive: it only switches local preview state). Email preview
   content per language is fixed sample text (B.9), independent of the UI
   language.
6. **Live band** (`#how`) — dark full-width band, the stepper demo, the
   countdown, three stat tiles, `BetaCta` in the band.
7. **Plans** (`#plans`) — title, region segmented control (default from
   `defaultRegion`, changing it only changes displayed prices, kept in
   component state), three cards from props. Card content: plan name; price
   (`Intl.NumberFormat` with the region currency via the existing `useFormat`)
   or the localized "Free"; "{n} applications per day" from `dailyLimit`;
   one-line description by `mode` (auto / select / review); 3 bullet
   features; `BetaCta`. Descriptions and bullets never mention internal
   mechanisms.
8. **FAQ** (`#faq`) — 5 items (B.9). The opt-out item renders only when
   `legal.optOut` is true.
9. **CTA banner** — headline + `BetaCta`, plus the Gmail-use sentence.
10. **Footer** — brand, contact email (if set), Privacy / Terms / Opt-out
    (if the routes exist), language switcher is only in the nav.

### B.7 SEO, no-JS essentials and Google requirements

- `<Head>`: localized `title` and `description`, Open Graph
  (`og:title`, `og:description`, `og:type=website`, `og:locale`), canonical
  URL, `<html lang>` follows the resolved locale (already handled by the
  layout; verify `es`).
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
- Below-the-fold sections mount lazily (`React.lazy` + `use-in-view`
  placeholder with fixed height to avoid layout shift). Target: LCP under
  2.5 s on a mid phone with cache off, no layout shift from lazy sections
  (CLS < 0.05). Images: none required; icons are `lucide-react` as in the
  app.
- No `setInterval` fetching; timers exist only inside the demo loop and are
  cleaned up.

### B.9 Copy (all three languages, source of truth)

All strings go to `lang/{en,pt,es}.json` under `landing.*`
(`{brand}`, `{n}` are interpolation placeholders; use the existing
`translate.ts` interpolation). Where the app already has a key for the same
concept (the five stage labels, plan names, mode names, "Live", "Sent"), reuse
it instead of duplicating. The mockup is the visual reference; this table
overrides mockup copy.

| Key                                                       | EN                                                                                                                                                                                                                                      | PT                                                                                                                                                                                                                                                           | ES                                                                                                                                                                                                                                                                    |
| --------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `meta.title`                                              | {brand} — Job applications sent from your own Gmail                                                                                                                                                                                     | {brand} — Candidaturas enviadas pelo seu próprio Gmail                                                                                                                                                                                                       | {brand} — Candidaturas enviadas desde tu propio Gmail                                                                                                                                                                                                                 |
| `meta.description`                                        | {brand} finds developer jobs, filters them by your preferences and sends each application from your own Gmail, one company at a time, in the language of the job.                                                                       | O {brand} encontra vagas de desenvolvimento, filtra pelas suas preferências e envia cada candidatura pelo seu próprio Gmail, uma empresa por vez, no idioma da vaga.                                                                                         | {brand} encuentra ofertas de desarrollo, las filtra según tus preferencias y envía cada candidatura desde tu propio Gmail, una empresa a la vez, en el idioma de la oferta.                                                                                           |
| `nav.product`                                             | Product                                                                                                                                                                                                                                 | Produto                                                                                                                                                                                                                                                      | Producto                                                                                                                                                                                                                                                              |
| `nav.how`                                                 | How it works                                                                                                                                                                                                                            | Como funciona                                                                                                                                                                                                                                                | Cómo funciona                                                                                                                                                                                                                                                         |
| `nav.languages`                                           | Languages                                                                                                                                                                                                                               | Idiomas                                                                                                                                                                                                                                                      | Idiomas                                                                                                                                                                                                                                                               |
| `nav.plans`                                               | Plans                                                                                                                                                                                                                                   | Planos                                                                                                                                                                                                                                                       | Planes                                                                                                                                                                                                                                                                |
| `nav.faq`                                                 | FAQ                                                                                                                                                                                                                                     | FAQ                                                                                                                                                                                                                                                          | FAQ                                                                                                                                                                                                                                                                   |
| `nav.signin`                                              | Sign in                                                                                                                                                                                                                                 | Entrar                                                                                                                                                                                                                                                       | Iniciar sesión                                                                                                                                                                                                                                                        |
| `nav.dashboard`                                           | Open dashboard                                                                                                                                                                                                                          | Abrir painel                                                                                                                                                                                                                                                 | Abrir panel                                                                                                                                                                                                                                                           |
| `cta.closed`                                              | Close beta                                                                                                                                                                                                                              | Close beta                                                                                                                                                                                                                                                   | Close beta                                                                                                                                                                                                                                                            |
| `cta.open`                                                | Get started                                                                                                                                                                                                                             | Começar                                                                                                                                                                                                                                                      | Empezar                                                                                                                                                                                                                                                               |
| `hero.badge`                                              | Closed beta · invite-only access                                                                                                                                                                                                        | Beta fechada · acesso por convite                                                                                                                                                                                                                            | Beta cerrada · acceso por invitación                                                                                                                                                                                                                                  |
| `hero.title.a` / `.em` / `.b`                             | Applications sent by / you / , without opening 40 tabs.                                                                                                                                                                                 | Candidaturas enviadas por / você / , sem abrir 40 abas.                                                                                                                                                                                                      | Candidaturas enviadas por / ti / , sin abrir 40 pestañas.                                                                                                                                                                                                             |
| `hero.lead`                                               | We find development jobs, filter them by what you look for and send your application from your own Gmail, one company at a time, in the job's language.                                                                                 | Encontramos vagas de desenvolvimento, filtramos pelo que você procura e enviamos a candidatura do seu próprio Gmail, uma empresa por vez, no idioma da vaga.                                                                                                 | Encontramos ofertas de desarrollo, las filtramos según lo que buscas y enviamos tu candidatura desde tu propio Gmail, una empresa a la vez, en el idioma de la oferta.                                                                                                |
| `hero.secondary`                                          | See how it works                                                                                                                                                                                                                        | Ver como funciona                                                                                                                                                                                                                                            | Ver cómo funciona                                                                                                                                                                                                                                                     |
| `hero.trust.1` / `.2` / `.3`                              | Sent from your Gmail / One email per company / You stay in control                                                                                                                                                                      | Sai do seu Gmail / 1 e-mail por empresa / Você no controle                                                                                                                                                                                                   | Sale de tu Gmail / 1 correo por empresa / Tú tienes el control                                                                                                                                                                                                        |
| `demo.caption`                                            | Illustrative demo with fictional jobs and companies.                                                                                                                                                                                    | Demonstração ilustrativa com vagas e empresas fictícias.                                                                                                                                                                                                     | Demostración ilustrativa con ofertas y empresas ficticias.                                                                                                                                                                                                            |
| `guarantee.1.title` / `.desc`                             | Sent from your Gmail / You appear as yourself                                                                                                                                                                                           | Sai do seu Gmail / Você aparece como você                                                                                                                                                                                                                    | Sale de tu Gmail / Apareces como tú                                                                                                                                                                                                                                   |
| `guarantee.2.title` / `.desc`                             | One per company / Never repeats a send                                                                                                                                                                                                  | Uma por empresa / Nunca repete o envio                                                                                                                                                                                                                       | Una por empresa / Nunca repite el envío                                                                                                                                                                                                                               |
| `guarantee.3.title` / `.desc`                             | Paced sending / One at a time, no bursts                                                                                                                                                                                                | Envio espaçado / Um por vez, sem rajada                                                                                                                                                                                                                      | Envío espaciado / Uno a la vez, sin ráfagas                                                                                                                                                                                                                           |
| `guarantee.4.title` / `.desc`                             | You're in control / Pause and review whenever you want                                                                                                                                                                                  | Você no controle / Pausa e revisa quando quiser                                                                                                                                                                                                              | Tú al mando / Pausa y revisa cuando quieras                                                                                                                                                                                                                           |
| `matching.eyebrow`                                        | Preferences                                                                                                                                                                                                                             | Preferências                                                                                                                                                                                                                                                 | Preferencias                                                                                                                                                                                                                                                          |
| `matching.title`                                          | Only the jobs that match.                                                                                                                                                                                                               | Só as vagas que combinam.                                                                                                                                                                                                                                    | Solo las ofertas que encajan.                                                                                                                                                                                                                                         |
| `matching.text`                                           | Set role, seniority, stack and where you want to work. Any value inside a field matches; across fields, all must match. You see how many jobs fit right away.                                                                           | Defina cargo, senioridade, stack e onde quer trabalhar. Dentro de cada campo vale qualquer valor; entre os campos, todos precisam bater. Você vê na hora quantas vagas encaixam.                                                                             | Define puesto, seniority, stack y dónde quieres trabajar. Dentro de cada campo vale cualquier valor; entre campos, todos deben coincidir. Ves al instante cuántas ofertas encajan.                                                                                    |
| `matching.chips`                                          | Backend · Senior · Laravel · Remote or Lisbon                                                                                                                                                                                           | Backend · Sênior · Laravel · Remoto ou Lisboa                                                                                                                                                                                                                | Backend · Senior · Laravel · Remoto o Lisboa                                                                                                                                                                                                                          |
| `matching.panel.title` / `.count` / `.selected` / `.send` | New jobs / 11 match you / 1 selected · 49 sends left today / Send 1 application                                                                                                                                                         | Novas vagas / 11 combinam com você / 1 selecionada · 49 envios restantes hoje / Enviar 1 candidatura                                                                                                                                                         | Nuevas ofertas / 11 encajan contigo / 1 seleccionada · 49 envíos restantes hoy / Enviar 1 candidatura                                                                                                                                                                 |
| `languages.eyebrow`                                       | Languages                                                                                                                                                                                                                               | Idiomas                                                                                                                                                                                                                                                      | Idiomas                                                                                                                                                                                                                                                               |
| `languages.title`                                         | One profile per language.                                                                                                                                                                                                               | Um perfil para cada idioma.                                                                                                                                                                                                                                  | Un perfil para cada idioma.                                                                                                                                                                                                                                           |
| `languages.text`                                          | CV, subject and cover letter in English, Portuguese and Spanish. A Spanish job gets your Spanish profile, and you see the email ready before anything is sent.                                                                          | Currículo, assunto e carta de apresentação em inglês, português e espanhol. A vaga em espanhol recebe o seu perfil em espanhol, e você vê o e-mail pronto antes de qualquer envio.                                                                           | Currículum, asunto y carta de presentación en inglés, portugués y español. La oferta en español recibe tu perfil en español y ves el correo listo antes de cualquier envío.                                                                                           |
| `languages.sample.subject`                                | Application: Backend Engineer                                                                                                                                                                                                           | Candidatura: Engenheiro Backend                                                                                                                                                                                                                              | Candidatura: Ingeniero Backend                                                                                                                                                                                                                                        |
| `languages.sample.body`                                   | Hello Northwind team, / I'm writing to apply for the Backend Engineer position {job_link}. / I'm a backend-leaning engineer with six years of experience building reliable web products. My CV is attached. / Best regards, / Ana Silva | Olá, equipe Northwind, / Escrevo para me candidatar à vaga de Engenheiro Backend {job_link}. / Sou engenheira com foco em backend e seis anos de experiência construindo produtos web confiáveis. Meu currículo está em anexo. / Atenciosamente, / Ana Silva | Hola, equipo Northwind: / Les escribo para postularme a la posición de Ingeniero Backend {job_link}. / Soy ingeniera con foco en backend y seis años de experiencia construyendo productos web confiables. Mi currículum va adjunto. / Saludos cordiales, / Ana Silva |
| `live.eyebrow`                                            | Live sending                                                                                                                                                                                                                            | Envio ao vivo                                                                                                                                                                                                                                                | Envío en vivo                                                                                                                                                                                                                                                         |
| `live.title`                                              | You see every step happen.                                                                                                                                                                                                              | Você vê cada passo acontecer.                                                                                                                                                                                                                                | Ves cada paso mientras ocurre.                                                                                                                                                                                                                                        |
| `live.text`                                               | Every application shows the real stage it's in. If something fails, you see where and why, no black boxes. No refresh needed, everything updates on its own.                                                                            | Cada candidatura mostra a etapa real em que está. Se algo falha, você vê onde e por quê, sem caixa-preta. Sem F5, tudo atualiza sozinho.                                                                                                                     | Cada candidatura muestra la etapa real en que está. Si algo falla, ves dónde y por qué, sin cajas negras. Sin F5, todo se actualiza solo.                                                                                                                             |
| `plans.eyebrow`                                           | Plans                                                                                                                                                                                                                                   | Planos                                                                                                                                                                                                                                                       | Planes                                                                                                                                                                                                                                                                |
| `plans.title`                                             | Choose how much control you want.                                                                                                                                                                                                       | Escolha quanto controle você quer ter.                                                                                                                                                                                                                       | Elige cuánto control quieres tener.                                                                                                                                                                                                                                   |
| `plans.region.label`                                      | Prices for                                                                                                                                                                                                                              | Preços para                                                                                                                                                                                                                                                  | Precios para                                                                                                                                                                                                                                                          |
| `plans.region.br` / `.eu` / `.row`                        | Brazil / Europe / Global                                                                                                                                                                                                                | Brasil / Europa / Global                                                                                                                                                                                                                                     | Brasil / Europa / Global                                                                                                                                                                                                                                              |
| `plans.free`                                              | Free                                                                                                                                                                                                                                    | Grátis                                                                                                                                                                                                                                                       | Gratis                                                                                                                                                                                                                                                                |
| `plans.per_month`                                         | / month                                                                                                                                                                                                                                 | / mês                                                                                                                                                                                                                                                        | / mes                                                                                                                                                                                                                                                                 |
| `plans.per_day`                                           | {n} applications per day                                                                                                                                                                                                                | {n} candidaturas por dia                                                                                                                                                                                                                                     | {n} candidaturas por día                                                                                                                                                                                                                                              |
| `plans.recommended`                                       | Recommended                                                                                                                                                                                                                             | Recomendado                                                                                                                                                                                                                                                  | Recomendado                                                                                                                                                                                                                                                           |
| `plans.desc.auto`                                         | Sends within your preferences.                                                                                                                                                                                                          | Envia dentro das suas preferências.                                                                                                                                                                                                                          | Envía según tus preferencias.                                                                                                                                                                                                                                         |
| `plans.desc.select`                                       | You choose which jobs to apply to.                                                                                                                                                                                                      | Você escolhe para quais vagas aplicar.                                                                                                                                                                                                                       | Tú eliges a qué ofertas aplicar.                                                                                                                                                                                                                                      |
| `plans.desc.review`                                       | You review and edit each email before it goes.                                                                                                                                                                                          | Você revisa e edita cada e-mail antes de sair.                                                                                                                                                                                                               | Revisas y editas cada correo antes de que salga.                                                                                                                                                                                                                      |
| `plans.feature.gmail`                                     | Sent from your own Gmail                                                                                                                                                                                                                | Sai do seu próprio Gmail                                                                                                                                                                                                                                     | Sale de tu propio Gmail                                                                                                                                                                                                                                               |
| `plans.feature.filtered`                                  | Jobs filtered by your preferences                                                                                                                                                                                                       | Vagas filtradas pelas suas preferências                                                                                                                                                                                                                      | Ofertas filtradas según tus preferencias                                                                                                                                                                                                                              |
| `plans.feature.select`                                    | Choose exactly what to send                                                                                                                                                                                                             | Escolha exatamente o que enviar                                                                                                                                                                                                                              | Elige exactamente qué enviar                                                                                                                                                                                                                                          |
| `plans.feature.review`                                    | Review and edit each email                                                                                                                                                                                                              | Revise e edite cada e-mail                                                                                                                                                                                                                                   | Revisa y edita cada correo                                                                                                                                                                                                                                            |
| `plans.feature.profiles`                                  | Profiles in EN, PT and ES                                                                                                                                                                                                               | Perfis em EN, PT e ES                                                                                                                                                                                                                                        | Perfiles en EN, PT y ES                                                                                                                                                                                                                                               |
| `plans.note`                                              | Illustrative prices. Final prices per region are set at launch.                                                                                                                                                                         | Valores de exemplo. Os preços finais por região são definidos no lançamento.                                                                                                                                                                                 | Valores de ejemplo. Los precios finales por región se definen en el lanzamiento.                                                                                                                                                                                      |
| `faq.eyebrow` / `faq.title`                               | FAQ / Frequently asked questions                                                                                                                                                                                                        | FAQ / Perguntas frequentes                                                                                                                                                                                                                                   | FAQ / Preguntas frecuentes                                                                                                                                                                                                                                            |
| `faq.1.q` / `.a`                                          | Do you read my inbox? / No. We only ask Google for permission to send emails. We never read, list or delete your messages.                                                                                                              | Vocês leem a minha caixa de entrada? / Não. Pedimos ao Google apenas a permissão de enviar e-mails. Nunca lemos, listamos ou apagamos mensagens.                                                                                                             | ¿Leen mi bandeja de entrada? / No. Solo pedimos a Google el permiso para enviar correos. Nunca leemos, listamos ni borramos mensajes.                                                                                                                                 |
| `faq.2.q` / `.a`                                          | Can I send twice to the same company? / No. Each company receives at most one application from you.                                                                                                                                     | Posso enviar duas vezes para a mesma empresa? / Não. Cada empresa recebe no máximo uma candidatura sua.                                                                                                                                                      | ¿Puedo enviar dos veces a la misma empresa? / No. Cada empresa recibe como máximo una candidatura tuya.                                                                                                                                                               |
| `faq.3.q` / `.a`                                          | What if I want to review before sending? / Choose the plan with review mode: you read and edit each email before it leaves.                                                                                                             | E se eu quiser revisar antes de enviar? / Escolha o plano com modo de revisão: você lê e edita cada e-mail antes de ele sair.                                                                                                                                | ¿Y si quiero revisar antes de enviar? / Elige el plan con modo de revisión: lees y editas cada correo antes de que salga.                                                                                                                                             |
| `faq.4.q` / `.a`                                          | Do you guarantee interviews or replies? / No. We send your application; replies depend on the company and your profile.                                                                                                                 | Vocês garantem entrevistas ou respostas? / Não. Nós enviamos a sua candidatura; a resposta depende da empresa e do seu perfil.                                                                                                                               | ¿Garantizan entrevistas o respuestas? / No. Enviamos tu candidatura; la respuesta depende de la empresa y de tu perfil.                                                                                                                                               |
| `faq.5.q` / `.a` (only if `legal.optOut`)                 | How can a company ask not to receive applications? / Through the opt-out page linked in the footer. Once confirmed, the company stops receiving applications.                                                                           | Como uma empresa pede para não receber candidaturas? / Pela página de opt-out no rodapé. Depois de confirmado, a empresa deixa de receber candidaturas.                                                                                                      | ¿Cómo puede una empresa pedir no recibir candidaturas? / Desde la página de exclusión en el pie de página. Una vez confirmado, la empresa deja de recibirlas.                                                                                                         |
| `cta.title`                                               | Start with invite-only access.                                                                                                                                                                                                          | Comece com acesso por convite.                                                                                                                                                                                                                               | Empieza con acceso por invitación.                                                                                                                                                                                                                                    |
| `gmail.note`                                              | We only use your Gmail permission to send the applications you choose or allow. We never read your inbox.                                                                                                                               | Usamos a permissão do seu Gmail apenas para enviar as candidaturas que você escolhe ou autoriza. Nunca lemos a sua caixa de entrada.                                                                                                                         | Usamos el permiso de tu Gmail solo para enviar las candidaturas que eliges o autorizas. Nunca leemos tu bandeja de entrada.                                                                                                                                           |
| `footer.privacy` / `.terms` / `.optout`                   | Privacy / Terms / Opt-out for companies                                                                                                                                                                                                 | Privacidade / Termos / Opt-out para empresas                                                                                                                                                                                                                 | Privacidad / Términos / Exclusión para empresas                                                                                                                                                                                                                       |
| `footer.contact`                                          | Contact                                                                                                                                                                                                                                 | Contato                                                                                                                                                                                                                                                      | Contacto                                                                                                                                                                                                                                                              |

Remove the old `landing.status`, `landing.subtitle`, `landing.login`,
`landing.dashboard` keys (and their PT translations) once unused.

The "Sent"/"Live"/stage labels and plan names inside the demo components
reuse the existing app keys, so they are translated in EN/PT already; `es`
must add the ones used by the demo to `lang/es.json` (list them while
implementing; the fallback is English, but the public page must not show
mixed languages — AC03).

### B.10 Responsive rules

- ≥1280: two columns in hero and features as in the mockup.
- 768–1279: hero stacks (text, then cards in a 2-column grid), features
  stack, pricing 3 columns tightened or 1+2, live band stacked.
- <768: single column, `text-landing-*-sm` tokens, guarantees strip 2×2,
  pricing cards stacked with the recommended plan first, region control
  full width, nav collapses (logo, language, Sign in, menu `Sheet`).
- No horizontal page scroll at 360 px. Touch targets ≥ 44 px
  (`control-xs`).

### B.11 Accessibility

Semantic landmarks (`header`, `main`, `section` with `aria-labelledby`,
`footer`), one `h1`, ordered headings, visible focus (`focus-visible:focus-ring`
as in the app), FAQ buttons with `aria-expanded`/`aria-controls`, tabs with
proper roles (existing `Tabs`), region control with radiogroup semantics (existing
`Segmented`; add the roles/keyboard support there if it lacks them, without changing app behavior), the demo panels `aria-hidden="true"` with a text alternative
in the section copy (they are decorative), `Close beta` is
`aria-disabled="true"` and skipped in tab order, contrast AA on the accent
banner (black text on accent as in the mockup), `lang` attributes on the
Portuguese/Spanish sample email blocks.

## Acceptance criteria

- **AC01** `/` renders the landing in EN, PT and ES; the nav language
  switcher changes it without reload and persists in the `locale` cookie.
  A signed-in user's app UI never becomes Spanish (locale switcher inside
  the app lists only EN/PT; `es` cookie ignored for authenticated users).
- **AC02** Every "ask for access" button reads `Close beta`, is not
  clickable, not focusable, has no hover state; Sign in and "See how it
  works" work. With `LANDING_BETA_CLOSED=false` the CTAs become real links
  to `/register`.
- **AC03** No hardcoded UI strings, no raw keys, no mixed-language text on
  the page in any locale (check ES: demo labels included).
- **AC04** Brand name and logo change by editing config/brand source only
  (change it once and grep the page for the old name: no occurrences).
- **AC05** Pricing values come from config, switch by region, show the
  localized "Free" for a zero price, and the illustrative note appears only
  when the flag is on.
- **AC06** The hero and live band run on real components in demo mode with
  fictional data, loop smoothly, pause when hidden/out of view, and are
  static under `prefers-reduced-motion`. The network tab shows no
  `/internal/*` calls, no websocket, and no polling on `/`.
- **AC07** The visual result matches `reference/landing-mockup.png`
  (structure, spacing, hierarchy, palette, type) at 1440 px, and adapts per
  B.10 down to 360 px with no horizontal scroll.
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
- **AC12** The two doc edits of Phase 1 are applied.

## Phases

1. **Foundations** — locale plumbing (B.2), `LandingController` + props
   (B.3), tokens (B.4), `PublicLayout`, `BetaCta`, nav/footer shells, page
   composition skeleton, `lang/es.json` skeleton, doc edits:
   `production-readiness/spec.md` B.4 ("the homepage is delivered by
   `public-landing`; that section then only owns Privacy, Terms and the
   opt-out page") and AC04 (landing content owned by `public-landing`),
   and `client-app-foundation/spec.md` Part 0 item 6 (append: "Spanish is
   available on public pages only, see `public-landing`").
2. **Hero** — demo data, `use-demo-loop`, hero with real components,
   guarantees strip, reveal/in-view hooks, demo caption, pattern extractions
   if any pattern is data-coupled.
3. **Feature sections** — preferences, languages (interactive tabs), live
   band.
4. **Pricing, FAQ, CTA, footer** — pricing from props with region control,
   FAQ accordion, CTA banner with Gmail note, footer with conditional
   links, SEO/`Head`, no-JS block in the blade view (B.7).
5. **Copy, polish, verification** — final EN/PT/ES strings (B.9) and
   `lang/es.json` for demo labels, responsive pass (B.10), a11y pass
   (B.11), reduced motion, lazy loading, performance and Lighthouse (AC10),
   check every acceptance criterion.

## Verification

`composer lint:check`, `composer types:check`, `yarn check`,
`yarn types:check`, existing tests. Manual: open `/` in all three locales at
1440, 768 and 360 px; sign in and confirm the app locale menu is EN/PT only
and `/` still shows the right locale; switch region on pricing; toggle
`LANDING_BETA_CLOSED`; disable JavaScript and confirm the description and
Gmail statement; enable "reduce motion" in the OS; inspect the network tab
(no `/internal`, no websocket); run Lighthouse.

## Open decisions (owner)

- **D-BRAND / D-DOMAIN:** final brand, logo and domain (nothing here blocks
  on it; all brand text is placeholder-driven).
- **D-FREEPRICE / D-PRICES / D-TAX:** the mockup shows Free as "Grátis";
  today the config default for Free is a paid amount (`PRICE_FREE_*`).
  Landing follows config; owner sets the real values.
- **D-SSR:** server rendering of the landing for SEO (adds a Node process).
  Default: no.
- **D-ES-APP:** translate the logged-in app to Spanish (separate spec) —
  not part of this one.
- **D-OG:** provide `og-landing.png` (1200×630) if wanted.
- Interactive hero (visitor picks language/plan and the panel reacts):
  not in this spec; passive demo only.

## Out of scope

Real signup flow while the beta is closed, Privacy/Terms/Opt-out pages
(`production-readiness`), blog/changelog, pricing checkout, analytics or
tracking scripts, A/B tests, testimonials/case studies, translating the
logged-in app, SSR, video or GIF assets, any data from the real pool.
