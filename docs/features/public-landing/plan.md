# Plan — public-landing — public multilingual landing page (EN/PT/ES)

Source spec: `docs/features/public-landing/spec.md` · SHA-256 `0628476fc8c9009b0ce654bfb90c7f66484723e5f181f831bf93b1eeba22062f`
Product truth: `docs/features/public-landing/spec.md` (visual truth: `reference/landing-mockup.{png,html}`, notes: `reference/landing-concept.md`)
Run phases with `/execute-phases docs/features/public-landing/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                                   | Role             | Depends on | Size | Status       |
| ----- | ------------------------------------------------------- | ---------------- | ---------- | ---- | ------------ |
| 1     | `app_locales` split on the backend (no behavior change) | laravel-backend  | none       | S    | PENDING      |
| 2     | Frontend locale types and app-scoped language switcher  | inertia-frontend | 1          | S    | PENDING      |
| 3     | Enable Spanish for guests + `lang/es.json`              | laravel-backend  | 1, 2       | M    | PENDING      |
| 4     | `LandingController`, config flags and page props        | laravel-backend  | none       | S    | PENDING      |
| 5     | Marketing tokens, `PublicLayout`, `BetaCta`             | inertia-frontend | 3, 4       | M    | PENDING      |
| 6     | Nav, footer, page skeleton, `Head`/SEO                  | inertia-frontend | 5          | M    | PENDING      |
| 7     | Hero copy, guarantees strip, reveal-on-scroll           | inertia-frontend | 6          | M    | PENDING      |
| 8     | Demo engine and hero demo cards                         | inertia-frontend | 7          | M    | PENDING      |
| 9     | Preferences section (`#product`)                        | inertia-frontend | 7          | S    | PENDING      |
| 10    | Languages section (`#languages`)                        | inertia-frontend | 7          | S    | PENDING      |
| 11    | Live band (`#how`)                                      | inertia-frontend | 8          | S    | PENDING      |
| 12    | Pricing (`#plans`)                                      | inertia-frontend | 7          | M    | PENDING      |
| 13    | FAQ and CTA banner                                      | inertia-frontend | 7          | S    | PENDING      |
| 14    | No-JS / server-rendered essentials in the Blade view    | laravel-backend  | 6          | S    | PENDING      |
| 15    | Lazy mounting, responsive and accessibility pass        | inertia-frontend | 8–13       | M    | PENDING      |
| 16    | Doc edits in sibling specs (AC12)                       | laravel-backend  | none       | S    | PENDING      |
| 17    | Verification and report                                 | qa-tester        | 1–16       | M    | PENDING      |

## Audit — 2026-09-30

| Check | Result |
| ----- | ------ |
| Stack | Laravel 13.33, PHP 8.4, Inertia Laravel 3.3 / `@inertiajs/react` 3, React 19.2 (React Compiler), Tailwind 4, `@headlessui/react` 2, `lucide-react`, Vite+ (`vp`). No animation library, none needed. |
| Verification commands that exist | `composer lint:check`, `composer types:check`, `composer test`, `composer ci:check`, `yarn check`, `yarn check:fix`, `yarn types:check`, `yarn build`, `vendor/bin/pint --dirty --format agent`. No Lighthouse CLI in the repo (AC10 is manual / Chrome DevTools). |
| Spec acceptance criteria | Present (AC01–AC12), no placeholders. |
| `routes/web.php` | `Route::inertia('/', 'landing')->name('home')`. `register`, `login`, `dashboard`, `locale.update` exist. Routes `privacy`, `terms`, `opt-out` do **not** exist → `legal` is all-off today; FAQ item 5 and footer legal links stay hidden. |
| `config/talent.php` | `locales` is `['en','pt']` (spec says "keep `['en','pt','es']`" — it has to be **added**). No `app_locales`, `landing.*`, `contact_email`. `brand.name` / `brand.wordmark` exist. Plan catalog modes are `random` / `select` / `review` (spec calls the first one "auto"). `plans.highlighted` = `starter`. |
| `.env` / `.env.example` | No `LANDING_BETA_CLOSED`, `LANDING_PRICES_ILLUSTRATIVE`, `TALENT_CONTACT_EMAIL` keys (append to `.env.example` only; defaults make `.env` optional). `PRICE_FREE_*` defaults are non-zero, so "Free" renders a price until the owner sets 0 (spec D-FREEPRICE, accepted). |
| Consumers of `talent.locales` | `LocaleResolver`, `Translations`, `HandleInertiaRequests`, `UpdateLocaleRequest`, **`UpdateOnboardingBasicsRequest`, `UpdateAccountRequest`, `Filament/Resources/Users/Schemas/UserForm`** (the last three are not listed in the spec and would accept `es` for users if left alone). |
| `lang/` | `en.json`, `pt.json` (848 lines each), no `es.json`. Old keys `landing.status/subtitle/login/dashboard` present. `locale.en`, `locale.pt` present; `locale.es` missing. |
| i18n interpolation | `translate.ts` replaces `:name`, not `{name}`. Spec placeholders `{brand}`, `{n}`, `{job_link}` are written as `:brand`, `:n`, `:job_link` in the lang files. |
| Frontend types | `Locale = 'en' \| 'pt'`, `JobLanguage = 'en' \| 'pt'`, `RegionKey`, `SendMode`, `SendStage`, `SubStep`, `PlanOffer` in `types/contracts.ts`. `SharedProps.locales` only. `INTL_LOCALES` has no `es`. |
| **Application languages in the product** | `App\Enums\ApplicationLanguage` has only `En`, `Pt`; `JobLanguage` and the profiles workspace are EN/PT only. The spec's landing copy claims Spanish profiles ("CV, subject and cover letter in English, Portuguese and Spanish", "Profiles in EN, PT and ES", a Spanish preview tab) while Part 0 item 7 says every claimed capability must exist today → **D1**. |
| Patterns the landing reuses | `hero-card`, `dark-card`, `live-stepper`, `queue-row`, `activity-row`, `stat-tile`, `job-row`, `company-logo`, `nav-pills`, `ui/{tick-meter,pill,chip,tabs,segmented,sheet,button,card}` are presentational (only `useT` / `useFormat`). Data-coupled: `language-switcher` (`usePage` + `useSetLocale` → `PUT /locale`, acceptable: not `/internal`), `logo` (`usePage` brand; link hardcoded to `/dashboard`), `mobile-nav` (logout button + app locales; landing builds its own `Sheet` content with the same item styles), `countdown-bar` (own `setInterval`, needs a controlled mode for reduced motion). `HeroCard` always renders a focusable corner arrow → demo wrappers need `inert`. |
| `Segmented` / `Tabs` | Headless UI `RadioGroup` / `TabGroup`: radiogroup and tablist roles plus keyboard support already there. No change needed. |
| `app.tsx` | `configureEcho` only stores config (socket opens lazily on first `echo()` call; landing must not call it). `bootFixtures()` loads the fixtures engine on **every** page when `VITE_USE_FIXTURES=true` (`.env.example` default) → AC06 network check must run with fixtures off. Title callback appends ` - <brand>` to every title (conflicts with `meta.title`, which already carries the brand). |
| CSS tokens | `--container-shell: 1520px` only; no `max-w-container` (1312px) token. `bg-hero`, `bg-hatched`, `focus-ring`, `--animate-fade-in`, global `prefers-reduced-motion` reset exist. `<html lang>` is set in Blade only; nothing updates it on a client-side locale change. |
| Wayfinder | `home`, `login`, `register`, `dashboard` in `@/routes`. No functions for the non-existent legal routes, so the frontend cannot build their URLs from booleans. |
| Tests | No existing test touches `/`, locales or the landing. Nothing to write (CLAUDE.md). |
| Migrations | None needed; `migrate:status` not relevant. |
| Owner memory (2026-09-27) | "Spanish dropped, never touch `lang/es.json`". The spec (owner, final, 2026-09-30) explicitly re-adds `es` for public pages only; this plan follows the spec and keeps the in-app UI EN/PT. |

## Owner decisions

### D1 — The landing claims Spanish application profiles, which the product does not have

**RESOLVED — owner, 2026-09-30: option A, English and Portuguese only** (applies to phases 10
and 12).

Why it was open: `ApplicationLanguage` is EN/PT only, but B.6.5, B.9 (`languages.text`,
`languages.sample.*` ES, `plans.feature.profiles`) and the mockup advertise
English/Portuguese/Spanish profiles, and Part 0 item 7 forbids claiming what does not exist.

What the decision means (the landing UI itself stays EN/PT/ES):

- Languages section: two preview tabs, English and Português. The Spanish sample email of B.9 is
  not used.
- Fictional rows and queue items carry only `en` / `pt` language chips (`JobLanguage`).
- These strings replace the corresponding B.9 rows (the spec text is left untouched; this
  decision overrides it until Spanish profiles ship):

| Key | EN | PT | ES |
| --- | -- | -- | -- |
| `landing.languages.text` | CV, subject and cover letter in English and Portuguese. A Portuguese job gets your Portuguese profile, and you see the email ready before anything is sent. | Currículo, assunto e carta de apresentação em inglês e português. A vaga em português recebe o seu perfil em português, e você vê o e-mail pronto antes de qualquer envio. | Currículum, asunto y carta de presentación en inglés y portugués. La oferta en portugués recibe tu perfil en portugués y ves el correo listo antes de cualquier envío. |
| `landing.plans.feature.profiles` | Profiles in EN and PT | Perfis em EN e PT | Perfiles en EN y PT |

### Open in the spec, not blocking

D-BRAND / D-DOMAIN, D-FREEPRICE / D-PRICES / D-TAX (landing follows config), D-SSR (no),
D-ES-APP (not here), D-OG (`og:image` omitted unless `public/og-landing.png` is supplied).

## Global constraints (every phase)

- **No git writes.** The spec header says "commit per phase"; `CLAUDE.md` overrides it — commit
  only when the owner asks in that message.
- No new composer/npm packages. No migrations. No tests written or modified. English in the repo.
- Tailwind 4 tokens only: no hex/px literals in components; every new value goes in `@theme` in
  `resources/css/app.css`. One component per file; repeated visuals become `components/patterns/`.
- No hardcoded UI strings and no hardcoded brand: strings in `lang/{en,pt,es}.json` under
  `landing.*` with `:brand` / `:n` placeholders (the spec's `{brand}` / `{n}`); brand from
  `app.brand` shared props and the `Logo` pattern. Copy source of truth is spec **B.9** — take the
  text from there verbatim, key = `landing.` + the table key (e.g. `landing.hero.lead`). Slash-
  separated cells map to the slash-separated keys in the same order.
- Every phase that adds `landing.*` keys adds them to **all three** files; every existing app key
  rendered on the landing (stage labels, plan names, …) is also added to `lang/es.json`.
- `lang/es.json` holds only public-page keys. The logged-in app stays EN/PT.
- Public page: no `/internal/*` call, no Echo channel, no `data/fixtures` import, no polling, no
  third-party script/font/tracker. Timers exist only inside the demo loop and are cleaned up.
- Nothing fabricated (no testimonials, logos, user counts, reply rates) and nothing from the
  "do not reveal" list (Part 0 items 7–8). Fictional data is labeled illustrative.
- In-app behavior unchanged (AC11). Pattern changes are additive props with the old default.
- React Compiler: no speculative `useMemo` / `useCallback` / `React.memo`.
- End of each phase: `composer lint:check`, `composer types:check`, `yarn check`,
  `yarn types:check` (run the ones matching the layer touched; all four in phases touching both).

## Acceptance-criteria coverage

| AC   | Phases              |
| ---- | ------------------- |
| AC01 | 1, 2, 3, 6, 17      |
| AC02 | 4, 5, 6, 9, 17      |
| AC03 | 3, 6–13, 15, 17     |
| AC04 | 5, 6, 14, 17        |
| AC05 | 4, 12, 17           |
| AC06 | 8, 11, 15, 17       |
| AC07 | 5–13, 15, 17        |
| AC08 | 4, 6, 13, 14, 17    |
| AC09 | 7–13, 17            |
| AC10 | 15, 17              |
| AC11 | 1, 2, 3, 8, 17      |
| AC12 | 16                  |

## Phases

### Phase 1 — `app_locales` split on the backend (no behavior change)

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: AC01, AC11 · Size: S
Spec: B.2

**Goal.** Introduce the "locales the logged-in app supports" list and move every app-only consumer
to it, while `locales` still equals `['en','pt']`, so nothing changes yet.

**Contract.**

- `config/talent.php`: add `'app_locales' => ['en', 'pt']` right after `locales`, with a comment:
  `locales` = everything that can be resolved/cookied (public pages); `app_locales` = locales the
  logged-in app is translated to.
- Switch to `config('talent.app_locales')`: `UpdateOnboardingBasicsRequest`,
  `UpdateAccountRequest`, `app/Filament/Resources/Users/Schemas/UserForm.php` (locale options).
- `HandleInertiaRequests::share`: add `'appLocales' => config('talent.app_locales')` next to
  `locales`.

**Steps.**

1. Edit the config, the two requests, the Filament form and the middleware.
2. `vendor/bin/pint --dirty --format agent`.

**Done when.**

- Shared props contain `appLocales: ['en','pt']`; `locales` unchanged.
- `composer lint:check` and `composer types:check` pass; `php artisan test --compact` passes.

**Not in this phase.** Adding `es`, resolver changes, any frontend change.

### Phase 2 — Frontend locale types and app-scoped language switcher

Status: PENDING
Role: inertia-frontend · Depends on: 1 · Covers: AC01, AC11 · Size: S
Spec: B.2

**Goal.** Make the frontend able to represent `es` and make the in-app switchers read
`appLocales`, before the backend starts resolving `es`.

**Contract.**

- `types/contracts.ts`: `Locale = 'en' | 'pt' | 'es'`. `JobLanguage` stays `'en' | 'pt'`.
- `types/shared.ts`: add `appLocales: Locale[]` to `SharedProps`.
- `i18n/locale.ts`: `es: 'es-ES'`.
- `components/patterns/language-switcher.tsx`:
  `useLocaleEntries(prefix = '', scope: 'app' | 'public' = 'app')` — `app` maps `appLocales`,
  `public` maps `locales`; `LanguageSwitcher({ scope = 'app' })` passes it through. `MobileNav`
  and `UserMenu` keep the default.
- Fix any `Record<Locale, …>` / exhaustive switch that `tsc` reports after widening (patterns
  taking `language: Locale` need no change).

**Steps.**

1. Apply the type changes, run `yarn types:check`, fix what it reports without changing behavior.

**Done when.**

- In-app language menu (top bar, mobile sheet, account, onboarding) still lists EN and PT only.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Landing components, `lang/es.json`.

### Phase 3 — Enable Spanish for guests + `lang/es.json`

Status: PENDING
Role: laravel-backend · Depends on: 1, 2 · Covers: AC01, AC03, AC11 · Size: M
Spec: B.2

**Goal.** Guests can resolve and set `es`; authenticated users never can.

**Contract.**

- `config/talent.php`: `'locales' => ['en', 'pt', 'es']`.
- `LocaleResolver::resolve`: `$supported` = `app_locales` when `$request->hasSession() &&
  $request->user()` is set, else `locales`. Order unchanged (user preference, cookie,
  Accept-Language primary subtag, `en`). An `es` cookie or `Accept-Language: es` for a signed-in
  user falls through to the next candidate.
- `UpdateLocaleRequest::rules`: `Rule::in($this->user() ? config('talent.app_locales') :
  config('talent.locales'))`. `LocaleController` unchanged (so `users.locale` can never be `es`).
- `lang/en.json` + `lang/pt.json`: add `"locale.es": "Español"`.
- `lang/es.json` (new, public keys only): `locale.en` "English", `locale.pt` "Português",
  `locale.es` "Español", `language_switcher.label` "Cambiar idioma".
- Known consequence (accepted by B.2): a guest with `es` sees `/login` and the other guest pages
  in English (fallback) with `<html lang="es">`.

**Steps.**

1. Config, resolver, request, lang files. 2. Pint.

**Done when.**

- `curl -s -H 'Accept-Language: es' http://<app>/` renders `<html lang="es">`; the same request
  with a signed-in session renders `en`/`pt`.
- `PUT /locale {locale: es}` → 204/redirect for a guest, 422 for a signed-in user.
- `composer lint:check`, `composer types:check`, `php artisan test --compact` pass.

**Not in this phase.** Landing strings in `es.json` (each frontend phase adds its own).

### Phase 4 — `LandingController`, config flags and page props

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: AC02, AC05, AC08 · Size: S
Spec: B.3

**Goal.** Serve `/` from an invokable controller with every prop the page needs; the current
placeholder page keeps rendering (it ignores the new props).

**Contract.**

- `app/Http/Controllers/LandingController.php` (invokable) → `Inertia::render('landing', [...])`;
  `routes/web.php`: `Route::get('/', LandingController::class)->name('home')`.
- Props:
  - `plans`: for each `PlanCatalog::all()` →
    `{ key, mode, dailyLimit, highlighted, prices: { br|eu|row: { amount: int (minor units),
currency: string } } }` using `PlanCatalog::priceFor`, `PlanCatalog::isHighlighted` and
    `Region::currency()` over `Region::cases()` (no price rule duplicated).
  - `defaultRegion`: first entry of `$request->getLanguages()` that has a region subtag
    (`pt_BR` / `pt-BR`) → `RegionResolver::fromCountry(<subtag>)->value`; none → `row`.
  - `betaClosed`: `(bool) config('talent.landing.beta_closed')`.
  - `pricesAreIllustrative`: `(bool) config('talent.landing.prices_illustrative')`.
  - `contactEmail`: non-empty string from `config('talent.contact_email')`, else `null`.
  - `legal`: `{ privacy, terms, optOut }`, each `Route::has('<privacy|terms|opt-out>') ?
route(...) : null`. **Deviation from B.3 (`bool`)**: the URL is passed instead of `true`
    because Wayfinder has no function for routes that do not exist yet; `null` = hidden.
- `config/talent.php`: `'landing' => ['beta_closed' => (bool) env('LANDING_BETA_CLOSED', true),
'prices_illustrative' => (bool) env('LANDING_PRICES_ILLUSTRATIVE', true)]`,
  `'contact_email' => env('TALENT_CONTACT_EMAIL')`.
- `.env.example`: append `LANDING_BETA_CLOSED=true`, `LANDING_PRICES_ILLUSTRATIVE=true`,
  `TALENT_CONTACT_EMAIL=`. Do not touch `.env`.
- `resources/js/features/landing/types.ts`: `LandingPlan`, `LandingLegal`, `LandingProps` matching
  the above (`mode: SendMode`, `key: PlanKey`, `Record<RegionKey, { amount: number; currency:
string }>`).
- No per-user data in the props.

**Steps.**

1. Config + env example. 2. Controller + route. 3. Types file. 4. Pint.

**Done when.**

- `/` still renders; the Inertia page payload contains the six props with three plans × three
  regions.
- `composer lint:check`, `composer types:check`, `yarn types:check` pass.

**Not in this phase.** Any UI.

### Phase 5 — Marketing tokens, `PublicLayout`, `BetaCta`

Status: PENDING
Role: inertia-frontend · Depends on: 3, 4 · Covers: AC02, AC04, AC07 · Size: M
Spec: B.4, B.5, Part 0 items 2–3

**Goal.** The foundations every section uses; the placeholder page moves onto `PublicLayout`.

**Contract.**

- `app.css` `@theme` (tokens only): `--text-landing-hero` 88px / lh .98 / ls -0.055em / weight
  500; `--text-landing-section` 64px / 1.02 / -0.05em / 500; `--text-landing-cta` 72px (same lh,
  ls, weight as section); `--text-landing-lead` 21px / 1.5; `--text-landing-body` 19px / 1.55;
  `-sm` variants: hero 46px, section 38px, cta 40px (same ratios); `--container-container:
1312px` (gives `max-w-container`).
- `layouts/public-layout.tsx`: `bg-shell text-ink`, `header` slot (nav), `main`, `footer` slot,
  content `max-w-container` centered with the shell gutters, `useFlashToasts()`, an effect that
  sets `document.documentElement.lang` to the current locale. No auth logic.
- `components/ui/button.tsx`: additive prop `inert?: boolean` — renders a `<span
aria-disabled="true">` with the same variant/size classes, without hover/active classes, focus
  ring, `href`, `onClick` or `tabIndex`. Default `false`, existing output unchanged.
- `features/landing/beta-cta.tsx`: `BetaCta({ betaClosed, variant?, size?, fullWidth? })` →
  closed: `<Button inert>` reading `t('landing.cta.closed')`; open: `<Button
href={register().url}>` reading `t('landing.cta.open')`. The only primary CTA on the page.
- `components/patterns/logo.tsx`: additive `href?: string` (default `/dashboard`, as today).
- Lang (3 files): `landing.cta.closed` = `Close beta` in all three; `landing.cta.open` (B.9).
- `pages/landing.tsx`: same placeholder content, wrapped in `PublicLayout`.

**Steps.**

1. Tokens. 2. Button + Logo props. 3. Layout + `BetaCta`. 4. Lang keys. 5. Swap the layout.

**Done when.**

- `/dev/styleguide` and app buttons look unchanged; `<Button inert>` is not focusable and has no
  hover state.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Nav, footer, sections.

### Phase 6 — Nav, footer, page skeleton, `Head`/SEO

Status: PENDING
Role: inertia-frontend · Depends on: 5 · Covers: AC01, AC02, AC03, AC04, AC08 · Size: M
Spec: B.5, B.6.1, B.6.10, B.7

**Goal.** A real page frame: sticky pill nav with the public language switcher, footer, section
anchors, localized head tags. The old placeholder is gone.

**Contract.**

- `features/landing/landing-nav.tsx`: `header`, sticky top. Desktop: `Logo href={home().url}`,
  pill group in the `NavPills` visual style with in-page anchors `#product`, `#how`, `#languages`,
  `#plans`, `#faq` (labels `landing.nav.*`; plain `<a>` with smooth scroll that becomes instant
  under `prefers-reduced-motion`), `LanguageSwitcher scope="public"`, Sign in
  (`Button variant="secondary-tile" href={login().url}`, label `landing.nav.signin`) or, when
  `auth.user` exists, `landing.nav.dashboard` → `dashboard().url`, then `BetaCta`. Below `md`:
  logo + language + Sign in + menu button opening a `Sheet side="right"` with the same anchors
  (item classes as in `mobile-nav`; no logout, no plan chip). If `NavPills` cannot render plain
  anchors, add an additive `anchor?: boolean` item flag rather than copying its classes.
- `features/landing/landing-footer.tsx`: `© <brand>` from `app.brand.name`, `contactEmail` as a
  `mailto:` (label `landing.footer.contact`) only when non-null, links `landing.footer.privacy /
terms / optout` only for non-null `legal.*`. No language switcher.
- `pages/landing.tsx`: `PublicLayout` + nav + `main` with empty-but-valid `section` anchors is
  **not** allowed; render only the sections that exist so far (none yet) plus an `h1` from
  `landing.hero.title.a/.em/.b` and the lead as the interim body, so the page is complete.
- `<Head>`: `title` = `t('landing.meta.title', { brand })`, `meta description`, `og:title`,
  `og:description`, `og:type=website`, `og:locale` (`en_US` / `pt_BR` / `es_ES`), `link
rel=canonical` = `home().url` absolute. No `og:image`.
- `app.tsx` title callback: return the title unchanged when it already contains the brand name
  (otherwise keep `title - brand`).
- Lang (3 files): `landing.meta.*`, `landing.nav.*`, `landing.footer.*`, `landing.hero.title.*`,
  `landing.hero.lead`; `es.json` also gets the app keys the nav renders (`topbar.menu`,
  `topbar.menu_title`, `common.close`). Remove `landing.status`, `landing.subtitle`,
  `landing.login`, `landing.dashboard` from `en.json` / `pt.json`.

**Steps.**

1. Nav. 2. Footer. 3. Page + Head. 4. Title callback. 5. Lang keys and removals.

**Done when.**

- `/` shows nav + headline + footer in EN, PT and ES; switching language in the nav updates the
  page without a full reload and sets the `locale` cookie; `<html lang>` follows.
- Signed in: nav shows "Open dashboard"; the in-app menu still lists EN/PT only.
- `grep -rn "landing.status\|landing.subtitle\|landing.login\|landing.dashboard" resources lang`
  returns nothing.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Hero layout, demo, sections, no-JS block.

### Phase 7 — Hero copy, guarantees strip, reveal-on-scroll

Status: PENDING
Role: inertia-frontend · Depends on: 6 · Covers: AC03, AC07, AC09 · Size: M
Spec: B.6.2, B.6.3, B.8

**Goal.** The hero's text column and the four-tile strip, with the reveal motion vocabulary.

**Contract.**

- `features/landing/use-in-view.ts`: `useInView<T extends Element>(options?) → [ref, inView]`
  (`IntersectionObserver`, disconnects on unmount).
- `features/landing/use-reveal.ts`: returns a ref + class that applies the reveal once on first
  view; skips entirely (content visible) under `prefers-reduced-motion`.
- `app.css`: `--animate-reveal` (fade + 12px translate, 350 ms ease-out) and a stagger custom
  property (60 ms steps); no values in components.
- `features/landing/hero.tsx`: two columns ≥1280. Left: badge (`Pill`, `landing.hero.badge`),
  the single `h1` (`text-landing-hero-sm md:text-landing-hero`, middle part `text-accent`), lead
  (`text-landing-lead`), `BetaCta` + secondary `Button variant="secondary-tile"` scrolling to
  `#how` (`landing.hero.secondary`), three trust lines (`landing.hero.trust.1..3`). Right column:
  a slot (`demo?: ReactNode`) left empty for now — the grid collapses to one column when empty.
- `features/landing/guarantees-strip.tsx`: four tiles (lucide icon in a `StatusDisc`-style disc,
  `landing.guarantee.N.title` / `.desc`), 4 columns desktop, 2×2 below `md`. If the tile is not an
  existing pattern, create `components/patterns/feature-tile.tsx`.
- `pages/landing.tsx`: replace the interim headline with `Hero` + `GuaranteesStrip`.
- Lang (3 files): `landing.hero.badge`, `.secondary`, `.trust.*`, `landing.guarantee.*` (B.9).

**Steps.**

1. Hooks + token. 2. Hero. 3. Strip. 4. Compose. 5. Lang.

**Done when.**

- Hero text and strip match the mockup hierarchy at 1440 px; one `h1` on the page.
- With OS "reduce motion" on, nothing animates and everything is visible.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Demo cards (right column).

### Phase 8 — Demo engine and hero demo cards

Status: PENDING
Role: inertia-frontend · Depends on: 7 · Covers: AC03, AC06, AC07, AC09, AC11 · Size: M
Spec: B.5 (demo mode rules), B.6.2

**Goal.** One shared, self-contained demo loop and the hero's right column built from the real
patterns.

**Contract.**

- `features/landing/demo-data.ts`: fictional candidate "Ana Silva"; companies in the style
  "Northwind Systems", "Lumen Data", "Via Cloud" (none real); queue/job rows typed with
  `JobLanguage` (`en` / `pt` only, see D1); daily limit 50; starting `sentToday` 18; week bars.
- `features/landing/use-demo-loop.ts`: `useReducer` + `setTimeout`s (all cleared on unmount).
  State: `{ current job, stageIndex 0–4 over ['validating_recipient','adapting_template',
'attaching_cv','sending','sent'], subStep, queue[], sentToday, activity[], countdown:
{ startsAt, endsAt } }`. Cycle: steps advance → `sent` → `sentToday + 1`, a new "sent" activity
  row, queue shifts, countdown runs, next job; after one full pass it wraps back to 18. Options:
  `{ running: boolean }`. No timers when `running` is false; under `prefers-reduced-motion` it
  returns one static frame and never starts; pauses on `document.hidden`. Sub-steps shown are
  limited to `confirming_recipient`, `filling_variables`, `opening_cv`, `attaching_file`,
  `connecting_gmail`, `delivering` (nothing that explains contact verification, Part 0 item 8).
- `features/landing/demo-loop-context.tsx`: provider owning the single loop instance plus a
  `register(inView)` so it runs while the hero **or** the live band is in view. Mounted in
  `pages/landing.tsx`.
- `features/landing/hero-demo.tsx`: wrapper `aria-hidden="true"` + `inert`. `HeroCard`
  (label `dashboard.hero.label`, value `sentToday`, suffix `/ 50`, hatched bars), `DarkCard`
  "Live sending" with `LiveStepper` + two `QueueRow`s, limit tile (`TickMeter` or `StatTile` with
  `meter`), Recent activity (`ActivityRow`, `timeFormat="clock"`). Caption under it, visible and
  muted: `landing.demo.caption`.
- `components/patterns/countdown-bar.tsx`: additive `now?: number`; when provided the component
  does not start its own interval (the loop drives it). Default behavior unchanged.
- Labels reuse app keys (`sending.stage.*`, `sending.sub.*`, `dashboard.hero.*`,
  `dashboard.live.*`, `dashboard.activity.*`, `live.next_in`, `patterns.hero.open`). Add every one
  actually rendered to `lang/es.json` in Spanish (e.g. `sending.stage.*`: "Validando
  destinatario", "Adaptando plantilla", "Adjuntando CV", "Enviando", "Enviado";
  `dashboard.live.title` "Envío en vivo"; `dashboard.live.badge` "En vivo";
  `dashboard.activity.title` "Actividad reciente"; `dashboard.activity.sent` "Candidatura
  enviada"; `live.next_in` "La próxima candidatura empieza en :time").
- No import from `@/data/*`, no `useEcho`, no TanStack Query in anything under
  `features/landing/`.

**Steps.**

1. Data + reducer + context. 2. `CountdownBar` prop. 3. Hero demo. 4. Mount in `Hero`'s slot.
5. Lang (caption ×3, demo labels in `es.json`).

**Done when.**

- The hero cards loop; numbers advance 18 → 19 → … and wrap; tab hidden or hero off-screen stops
  the timers; reduced motion shows one static frame.
- `grep -rn "@/data\|echo-react\|react-query" resources/js/features/landing` returns nothing.
- Dashboard live card countdown still ticks as before.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Live band.

### Phase 9 — Preferences section (`#product`)

Status: PENDING
Role: inertia-frontend · Depends on: 7 · Covers: AC02, AC03, AC07, AC09 · Size: S
Spec: B.6.4

**Goal.** Text + chips on the left, the static "New jobs" panel on the right.

**Contract.**

- `features/landing/feature-matching.tsx`: `section#product` with `aria-labelledby`; eyebrow,
  `h2` (`text-landing-section-sm md:text-landing-section`), text (`text-landing-body`), four
  static chips from `landing.matching.chip.1..4` (the four parts of B.9 `matching.chips`, split on
  " · ").
- Panel (`Card tone="light"`, wrapper `aria-hidden="true"` + `inert`): title
  `landing.matching.panel.title`, count `.count`, three `JobRow`s from `demo-data.ts` (first one
  `selected`, `onSelectedChange` no-op, languages `en`/`pt`), footer line `.selected` and
  `<Button inert>` with `.send`. Nothing in it is interactive.
- Lang (3 files): `landing.matching.*`.

**Done when.**

- Section matches the mockup block; Tab key skips the whole panel.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Languages section.

### Phase 10 — Languages section (`#languages`)

Status: PENDING
Role: inertia-frontend · Depends on: 7 · Covers: AC03, AC07, AC09 · Size: S
Spec: B.6.5, B.9 (`languages.*`), B.11, D1

**Goal.** Interactive preview tabs that switch the sample email language.

**Contract.**

- `features/landing/feature-languages.tsx`: `section#languages`; text column (eyebrow, `h2`,
  `landing.languages.text`) and a card with the real `Tabs` (local state only), a CV file row
  (`ana-silva-cv.pdf`, fictional) and the email preview: subject + body lines, the
  `:job_link` slot rendered as the "job link" chip (`profiles.job_link`, as in the app's template
  preview).
- Sample emails are fixed per sample language, independent of the UI language, so they live in
  `demo-data.ts` (`SAMPLE_EMAILS: Record<JobLanguage, { subject, lines[] }>`, EN and PT text
  from B.9 `languages.sample.*`) — not in the lang files. Each preview block carries
  `lang="<code>"`.
- Two tabs only, English and Português (D1). Tab labels reuse `locale.en` / `locale.pt`.
  `landing.languages.text` uses the D1 strings, not the B.9 row.
- Lang (3 files): `landing.languages.eyebrow/title/text`; `es.json` gets `profiles.job_link`.

**Done when.**

- Switching tabs swaps subject/body; keyboard arrows work; preview has the right `lang`.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Pricing bullets.

### Phase 11 — Live band (`#how`)

Status: PENDING
Role: inertia-frontend · Depends on: 8 · Covers: AC03, AC06, AC07 · Size: S
Spec: B.6.6

**Goal.** The dark full-width band driven by the same loop as the hero.

**Contract.**

- `features/landing/live-band.tsx`: `section#how`, `Card tone="dark"` full container width. Left:
  eyebrow `landing.live.eyebrow`, `h2` `landing.live.title`, `landing.live.text`, `BetaCta`
  (accent/on-dark variant that exists in `Button`). Right (wrapper `aria-hidden` + `inert`):
  current job header, `LiveStepper` (5 stages), sub-step line, `CountdownBar now={…}`, three stat
  tiles (sent today, queued, failed = 0) on the dark surface; numbers from the shared context so
  they equal the hero's. Demo caption (`landing.demo.caption`) below.
- Registers its in-view state with the demo context (`use-in-view`).
- Lang (3 files): `landing.live.*`; any extra app key rendered goes to `es.json`.

**Done when.**

- Hero and band show the same "sent today" number at all times; leaving both stops the timers.
- "See how it works" scrolls here.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Count-up polish (Phase 15).

### Phase 12 — Pricing (`#plans`)

Status: PENDING
Role: inertia-frontend · Depends on: 7 · Covers: AC03, AC05, AC07 · Size: M
Spec: B.6.7, Part 0 item 6, D1

**Goal.** Three plan cards from props with the region control.

**Contract.**

- `features/landing/pricing.tsx`: `section#plans`; eyebrow/title; `Segmented<RegionKey>` with
  `ariaLabel = t('landing.plans.region.label')`, options `landing.plans.region.br/eu/row`,
  initial value `defaultRegion`, component state only; full width below `md`. Note line
  `landing.plans.note` only when `pricesAreIllustrative`.
- `features/landing/landing-plan-card.tsx` (presentational, same visual language as
  `features/plans/plan-card.tsx`; if the shared markup is substantial, extract a
  `components/patterns/plan-card-shell.tsx` used by both without changing the app card's output):
  name `plans.<key>.name`; price = `amount === 0` → `landing.plans.free`, else
  `useFormat().currency(amount / 100, currency)` + `plans.per_month`; `landing.plans.per_day`
  with `:n = dailyLimit`; description `landing.plans.desc.<auto|select|review>` where config mode
  `random` maps to `auto`; three bullets; `BetaCta fullWidth`. `highlighted` → accent outline +
  `Pill` `landing.plans.recommended`.
- Bullets: free → `gmail`, `filtered`, `profiles`; starter → `gmail`, `select`, `profiles`;
  pro → `gmail`, `review`, `profiles` (`landing.plans.feature.*`). The `profiles` bullet uses
  the D1 strings ("Profiles in EN and PT" and equivalents), not the B.9 row.
- Below `md` the highlighted plan is ordered first (CSS `order`, DOM order unchanged).
- Lang (3 files): `landing.plans.*`; `es.json` gets `plans.free.name`, `plans.starter.name`,
  `plans.pro.name`, `plans.per_month` ("/ mes").

**Done when.**

- Switching region changes all three prices and currencies with no request; setting
  `PRICE_FREE_BRL=0` shows "Free"/"Grátis"/"Gratis" for Brazil; `LANDING_PRICES_ILLUSTRATIVE=false`
  hides the note.
- `/plans` in the app is visually unchanged.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** Checkout, real signup.

### Phase 13 — FAQ and CTA banner

Status: PENDING
Role: inertia-frontend · Depends on: 7 · Covers: AC03, AC07, AC08, AC09 · Size: S
Spec: B.6.8, B.6.9, B.11

**Goal.** The accordion and the closing banner with the Gmail-use sentence.

**Contract.**

- `features/landing/faq.tsx`: `section#faq`; items 1–4 always, item 5 only when `legal.optOut`
  is non-null. Each item: `<h3><button aria-expanded aria-controls>` + panel `role="region"`;
  first open by default; height animated with the CSS grid-rows `0fr → 1fr` transition (token in
  `app.css` if a new duration/easing is needed). Several may be open.
- `features/landing/cta-banner.tsx`: `bg-hero` rounded banner, `h2` `landing.cta.title`
  (`text-landing-cta-sm md:text-landing-cta`, ink text on accent for AA), `BetaCta`
  (`primary-ink`), and `landing.gmail.note` as visible text.
- Lang (3 files): `landing.faq.*`, `landing.cta.title`, `landing.gmail.note`.

**Done when.**

- FAQ opens/closes by mouse and keyboard with correct `aria-expanded`; four items today.
- Gmail sentence visible in the banner in the three locales.
- `yarn check`, `yarn types:check` pass.

**Not in this phase.** No-JS rendering of the sentence.

### Phase 14 — No-JS / server-rendered essentials in the Blade view

Status: PENDING
Role: laravel-backend · Depends on: 6 · Covers: AC04, AC08 · Size: S
Spec: B.7

**Goal.** What the app does and how Gmail is used is in the HTML without JavaScript.

**Contract.**

- `resources/views/app.blade.php`, only when `$page['component'] === 'landing'`: inside `<body>`
  a block with brand name (`config('talent.brand.name')`), `landing.meta.description` and
  `landing.gmail.note` read from `Translations::for(app()->getLocale())` with `:brand` replaced.
  It is server-rendered plain text, shown inside `<noscript>` and duplicated in a block that is
  visible until the app mounts and hidden once JS runs (class toggled by a one-line inline
  script or removed by the landing page on mount; use an existing `sr-only`-style utility, no
  inline styles). Also a server-side `<meta name="description">` for the landing.
- Prefer a small Blade partial `resources/views/partials/landing-essentials.blade.php`.
- No SSR process, no third-party asset.

**Done when.**

- `curl -s http://<app>/ | grep -i gmail` finds the sentence; with `Accept-Language: pt` / `es`
  it is in that language; other pages' HTML does not contain the block.
- JavaScript disabled in the browser: description and Gmail statement are readable.
- `composer lint:check`, `composer types:check` pass.

**Not in this phase.** `og-landing.png` (D-OG).

### Phase 15 — Lazy mounting, responsive and accessibility pass

Status: PENDING
Role: inertia-frontend · Depends on: 8, 9, 10, 11, 12, 13 · Covers: AC03, AC06, AC07, AC10 · Size: M
Spec: B.8, B.10, B.11

**Goal.** Finish the page as a whole.

**Contract.**

- `pages/landing.tsx`: below-the-fold sections (`feature-matching`, `feature-languages`,
  `live-band`, `pricing`, `faq`, `cta-banner`) load through `React.lazy` inside a
  `features/landing/lazy-section.tsx` that reserves a fixed min-height (token) and keeps the
  section `id` on the placeholder so nav anchors work before mount.
- Count-up of numerals on first view (small hook in `features/landing/`, skipped under reduced
  motion); hover lift on cards via transform only.
- B.10: ≥1280 two columns; 768–1279 hero stacks (text, then cards in 2 columns), features stack,
  pricing tightened, band stacked; <768 single column with `-sm` type tokens, strip 2×2, region
  control full width, collapsed nav; no horizontal scroll at 360 px; touch targets ≥
  `control-xs` (44px).
- B.11: `header` / `main` / `section[aria-labelledby]` / `footer`, one `h1`, ordered headings,
  `focus-visible:focus-ring` on every interactive element, demo wrappers `aria-hidden` + `inert`,
  `Close beta` out of the tab order.
- Sweep `features/landing/**` and `pages/landing.tsx` for string literals, hex/px literals and
  brand text; move anything found to lang files / tokens.

**Done when.**

- 1440 / 768 / 360 px in EN, PT, ES: layout per B.10, no horizontal scroll, no raw keys, no
  English leftovers in ES.
- No layout jump when lazy sections mount.
- `yarn check`, `yarn types:check`, `yarn build` pass.

**Not in this phase.** Lighthouse scoring (Phase 17).

### Phase 16 — Doc edits in sibling specs (AC12)

Status: PENDING
Role: laravel-backend · Depends on: none · Covers: AC12 · Size: S
Spec: Phases §1 (doc edits)

**Goal.** The two cross-references the spec asks for, nothing else.

**Contract.**

- `docs/features/production-readiness/spec.md` B.4, "Homepage requirement" bullet: state that the
  homepage is delivered by `public-landing`; that section then only owns Privacy, Terms and the
  opt-out page. AC04: landing content is owned by `public-landing`.
- `docs/features/client-app-foundation/spec.md` Part 0 item 6: append "Spanish is available on
  public pages only, see `public-landing`."
- These are owner-requested edits to product text, not edits to match code.

**Done when.**

- Both files carry the sentences above; no other line changed (`git diff --stat` shows two files).

**Not in this phase.** Any code.

### Phase 17 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–16 · Covers: all · Size: M
Spec: Acceptance criteria, Verification

**Goal.** Prove the ACs, report what is left to the owner. No fixes beyond trivial ones; findings
go back as a list.

**Steps.**

1. Full gate: `composer ci:check` (or `composer lint:check`, `composer types:check`,
   `yarn check`, `yarn types:check`, `php artisan test --compact`), plus `yarn build`.
2. Forbidden patterns:
   `grep -rnE "setInterval|refetchInterval|useEcho|@/data/|/internal" resources/js/features/landing resources/js/pages/landing.tsx`
   (only the demo loop's timers may match); `grep -rnE "#[0-9a-fA-F]{3,8}\b|[0-9]px" resources/js/features/landing`;
   `grep -rn "Talent Labs" resources/js lang` (brand must come from config);
   `grep -rn "poll" resources/js/features/landing`.
3. i18n parity: every `landing.*` key in `en.json` exists in `pt.json` and `es.json`; every key
   used by `features/landing/**` and the reused patterns on the page exists in `es.json`.
4. Browser smoke (Playwright MCP, `VITE_USE_FIXTURES=false`): `/` in EN/PT/ES at 1440, 768, 360;
   compare 1440 PT against `reference/landing-mockup.png`; language switch + cookie; signed-in
   user sees EN/PT menu and `/` in the app locale even with an `es` cookie; region switch;
   `Close beta` not focusable/clickable; network tab has no `/internal`, no websocket; reduced
   motion; JS disabled.
5. Toggle `LANDING_BETA_CLOSED=false` (owner's `.env`, or `config()` override in tinker-free
   manual check) → CTAs link to `/register`.
6. AC walkthrough AC01–AC12 with evidence per AC; run `code-reviewer` once on the feature diff.

**Done when.**

- Report lists each AC as pass / fail / owner-manual with evidence.

**Owner's manual checklist.** Lighthouse mobile on `/` (AC10: Perf ≥ 90, A11y ≥ 95, BP ≥ 95,
SEO ≥ 95, CLS < 0.05); visual sign-off against the mockup (AC07); change `BRAND_NAME` once and
grep the rendered page (AC04); set real prices (D-PRICES / D-FREEPRICE); supply
`og-landing.png` if wanted (D-OG).

**Not in this phase.** New features, tests, commits.
