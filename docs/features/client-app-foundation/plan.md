# Plan — client-app-foundation (Inertia client app: design system, shell, data layer, i18n, login)

Source spec: `docs/features/client-app-foundation/spec.md` · SHA-256 `5e44748db28a0faef120227ae3f1cafe01bb43b1aa33d53d98590fadb3b87593`
Product truth: the spec above (Part B + Acceptance criteria). Visual truth:
`docs/features/client-app-foundation/reference/dashboard-mockup.html` (+ `.png`, `logo-t-dot.png`).
Run phases with `/execute-phases docs/features/client-app-foundation/plan.md <phases>` — one or a few per
session. Phase status is updated in place in this file.

## Status board

| Phase | Title                                                                 | Role             | Depends on        | Size | Status      |
| ----- | --------------------------------------------------------------------- | ---------------- | ----------------- | ---- | ----------- |
| 1     | New dependencies, self-hosted Geist, design tokens                    | inertia-frontend | none              | M    | DONE ¹      |
| 2     | Brand/fixtures/locales config, env keys, SSR off                      | laravel-backend  | none              | S    | DONE        |
| 3     | `users.locale` + `users.timezone`                                     | laravel-backend  | none              | S    | DONE ²      |
| 4     | Translation files + `SharedProps` contract                            | laravel-backend  | 2, 3              | M    | DONE        |
| 5     | Frontend i18n runtime, `lib/format`, root providers, doc title        | inertia-frontend | 4                 | S    | DONE        |
| 6     | `SetLocale` middleware + `PUT /locale`                                | laravel-backend  | 3, 4              | M    | DONE        |
| 7     | `BareLayout` + `/dev/styleguide` scaffold (tokens, type scale)        | inertia-frontend | 1, 5              | S    | DONE        |
| 8     | Primitives I: Spinner, Button, IconButton, LiveDot, Kbd               | inertia-frontend | 7                 | M    | DONE        |
| 9     | Primitives II: Pill, Chip, Avatar, StatusDisc, ProgressBar, TickMeter | inertia-frontend | 8                 | M    | DONE        |
| 10    | Primitives III: Card, Skeleton, EmptyState, ErrorState                | inertia-frontend | 8                 | S    | DONE        |
| 11    | Form I: Field, Input, Textarea, TagsInput, Checkbox, Switch           | inertia-frontend | 8                 | M    | PENDING     |
| 12    | Form II: Select, MultiSelect, RadioGroup, Segmented, Tabs             | inertia-frontend | 9, 11             | M    | PENDING     |
| 13    | Overlays: Modal, Sheet, Popover, Menu, Tooltip                        | inertia-frontend | 8                 | M    | PENDING     |
| 14    | FileDrop + Toast (toaster, flash bridge)                              | inertia-frontend | 9, 10             | M    | PENDING     |
| 15    | Logo, logo mark, favicons, brand in Blade                             | inertia-frontend | 7                 | S    | PENDING     |
| 16    | Data layer core: query client, `apiFetch`, keys, source switch        | inertia-frontend | 5                 | S    | PENDING     |
| 17    | Fixtures runtime + dev state + realtime (`useUserChannel`, emitter)   | inertia-frontend | 16                | M    | PENDING     |
| 18    | `DevToolbar` + `useSetLocale`                                         | inertia-frontend | 6, 12, 13, 14, 17 | M    | PENDING     |
| 19    | NavPills, LanguageSwitcher, UserMenu + `POST /logout`                 | inertia-frontend | 9, 13, 18         | M    | PENDING     |
| 20    | TopBar, MobileNav, PageHeader                                         | inertia-frontend | 15, 19            | M    | PENDING     |
| 21    | `AppLayout`, `client` middleware, `/dashboard` route (skeleton)       | inertia-frontend | 20                | M    | PENDING     |
| 22    | Login: controller, request, throttle, `GuestLayout`, login page       | laravel-backend  | 11, 15, 21        | M    | PENDING     |
| 23    | Landing page; remove `welcome.tsx`                                    | inertia-frontend | 15, 22            | S    | PENDING     |
| 24    | Branded Inertia error pages                                           | laravel-backend  | 6, 15, 21         | S    | PENDING     |
| 25    | Card patterns: HeroCard, StatTile, DataCard, DarkCard, SectionHeader  | inertia-frontend | 9, 10             | M    | PENDING     |
| 26    | Live patterns: LiveStepper, CountdownBar, ActivityRow                 | inertia-frontend | 9                 | S    | PENDING     |
| 27    | Row patterns: CompanyLogo, QueueRow, JobRow                           | inertia-frontend | 9, 11             | S    | PENDING     |
| 28    | BarChart (hand-rolled SVG)                                            | inertia-frontend | 10, 13            | S    | PENDING     |
| 29    | PlanGate, StickyActionBar, FilterBar                                  | inertia-frontend | 8, 12, 17         | S    | PENDING     |
| 30    | Demo dashboard composition + visual gate                              | inertia-frontend | 21, 25–29         | M    | PENDING     |
| 31    | PT/ES Laravel validation messages                                     | laravel-backend  | 4                 | S    | PENDING     |
| 32    | Verification and report                                               | qa-tester        | 1–31              | S    | PENDING     |

¹ Phase 1 installs packages. `CLAUDE.md` requires the owner's approval in the
same message, so the `/execute-phases` message for Phase 1 must say it
explicitly, e.g. "I approve installing the B.2 dependencies".

² Phase 3 ends with an owner action: the owner runs
`php artisan migrate:fresh --seed` (D1 = A). Phases 4 and 6 start only after
the owner confirms it ran.

## Audit — 2026-09-26

| Check                                    | Result                                                                                                                                                                                                                                                                                                                                                                                             |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Spec completeness                        | Complete: goal, rules, B.1–B.13, AC01–AC17, verification, out of scope. No placeholders. `client-app-screens` owns `contracts.ts`, `realtime.ts`, the event names/payloads, the `data/hooks/*` and `data/endpoints.ts`. This plan builds none of them.                                                                                                                                             |
| Backend versions                         | laravel/framework 13.33.0, inertiajs/inertia-laravel 3.3.4, filament/filament 5.8.4, laravel/wayfinder 0.1.21, PHP ^8.3                                                                                                                                                                                                                                                                            |
| Frontend versions                        | @inertiajs/react 3.7.1, tailwindcss 4.3.3, @laravel/echo-react 2.5.0, react ^19.2, vite 8 via `vite-plus` 0.3.0 (`vp`), Yarn 1.22.19, Node 24.2                                                                                                                                                                                                                                                    |
| New packages (npm registry, read-only)   | `@headlessui/react` 2.2.10 (peer `react ^18 \|\| ^19`: React 19 supported); `@tanstack/react-query` / `-devtools` 5.104.0 (peer `^18 \|\| ^19`); `lucide-react` 1.48.0 (peer includes `^19`). None installed yet.                                                                                                                                                                                  |
| Lockfiles                                | Both `yarn.lock` and `package-lock.json` exist. The project uses Yarn Classic, so `package-lock.json` will go stale after `yarn add`. Leave it and report it; do not delete it.                                                                                                                                                                                                                    |
| Geist font                               | `raw.githubusercontent.com/vercel/geist-font/main/packages/next/dist/fonts/geist-sans/Geist-Variable.woff2` → 200 (69,760 B); `…/main/OFL.txt` → 200. `curl`/`wget` are blocked by the context-mode hook, so download with a `node -e "fetch(…)"` script.                                                                                                                                          |
| External fonts today                     | `vite.config.ts` uses `bunny('Instrument Sans')` from `laravel-vite-plugin/fonts`. `app.blade.php` uses `@fonts` and `app.css` sets `--font-sans: 'Instrument Sans'`. All three must go for AC02.                                                                                                                                                                                                  |
| Lint scope                               | `vite.config.ts` `lint.ignorePatterns` **and** `fmt.ignorePatterns` both exclude `resources/js/components/ui/*`, a starter-kit leftover. Every B.4 primitive lives there, so none of them would be linted or formatted (see D4).                                                                                                                                                                   |
| Inertia SSR                              | `config/inertia.php` `ssr.enabled => true` (literal). In v3, Vite dev mode SSR-renders pages automatically. Spec decision 5 says no SSR, so Phase 2 turns it off.                                                                                                                                                                                                                                  |
| Inertia 3 APIs used                      | `createInertiaApp({ withApp(app, { page }) })` wraps root providers and receives the initial `page`. `Inertia::handleExceptionsUsing(fn (ExceptionResponse $r) => $r->render(...)->withSharedData())` (vendor `src/ExceptionResponse.php`) renders error pages with shared data, even when the web group did not run.                                                                              |
| Frontend files                           | `app.tsx` (only `configureEcho` + `createInertiaApp` with a `VITE_APP_NAME` title), `pages/welcome.tsx` (357 lines, uses only `Head`), `lib/utils.ts` (`cn()`), `types/{auth,global.d,index,vite-env.d}.ts`. `global.d.ts` types `sharedPageProps` as `{ name, auth: { user: User }, sidebarOpen }`. There is no `components/`, `layouts/`, `data/` or `i18n/` yet.                                |
| Blade root                               | `app.blade.php` links `/favicon.ico`, `/favicon.svg` and `/apple-touch-icon.png` (the Laravel defaults, present in `public/`), `<title>{{ config('app.name') }}`, `@vite([... "resources/js/pages/{$page['component']}.tsx"])`. Nested components like `auth/login` and `errors/error` resolve fine.                                                                                               |
| Shared props today                       | `HandleInertiaRequests::share` returns `name` plus `auth.user` = **the full `User` model** (AC09 replaces it). No `flash`, `locale` or `translations`.                                                                                                                                                                                                                                             |
| Routes                                   | `GET / → welcome` (`home`) and the `integrations.oauth.*` routes (`web`,`auth`). No `login`, `logout`, `dashboard` or `locale.*` names exist, so there is no collision. Filament owns `/admin/*` and `/app/*`, each with its own `->login()`.                                                                                                                                                      |
| Guest redirect                           | `bootstrap/app.php`: `redirectGuestsTo(fn () => route('filament.app.auth.login'))`. Phase 22 changes it to `route('login')`. Filament's own `Authenticate` keeps redirecting to each panel's login.                                                                                                                                                                                                |
| Filament middleware                      | Both panels use their own `->middleware([...])` stack (EncryptCookies…DispatchServingFilamentEvent), not the `web` group. `SetLocale`/`HandleInertiaRequests` in `web` do not touch Filament (AC17).                                                                                                                                                                                               |
| Users table                              | `0001_01_01_000000_create_users_table` has run (batch 1). Its columns are id, name, email, email_verified_at, password, remember_token, is_admin, status, timestamps. There is no `locale` or `timezone`. `User` has `#[Fillable(['name','email','password','is_admin','status'])]` and `isActive()`. `UserStatus` = `active` \| `blocked`.                                                        |
| Data at risk                             | Current dev DB: users 2, job_postings 1,892, companies 485, contacts 1,532, applications 9, connected_integrations 1 (Gmail), collection_runs 1. `migrate:fresh` deletes all of it (see D1).                                                                                                                                                                                                       |
| Tests DB                                 | `phpunit.xml` uses sqlite `:memory:`, so `php artisan test` is safe. `tests/Feature/ExampleTest` asserts `GET route('home')` → 200. It must keep passing after `/` becomes `landing`.                                                                                                                                                                                                              |
| `lang/`                                  | Does not exist. `php artisan lang:publish` exists, but it publishes only Laravel's **English** `lang/en/*.php`. Laravel core ships no PT/ES files (see D2). JSON dotted keys work with `__('auth.failed')` because the JSON lookup runs first.                                                                                                                                                     |
| Seeder                                   | `UserSeeder` is idempotent and reads `config('talent.seed.*')`. `.env` has the `SEED_*` keys.                                                                                                                                                                                                                                                                                                      |
| `.env` / `.env.example`                  | `BRAND_NAME`, `BRAND_WORDMARK_REGULAR`, `BRAND_WORDMARK_BOLD` and `VITE_USE_FIXTURES` are missing from both. `APP_NAME`, `VITE_APP_NAME`, `APP_DEBUG=true` and `APP_ENV=local` are present.                                                                                                                                                                                                        |
| Local image tools                        | No `rsvg-convert`, ImageMagick or Inkscape. macOS `qlmanage` and `sips` exist, and Playwright MCP is available in-session. Either can rasterize the 180 px apple-touch-icon.                                                                                                                                                                                                                       |
| Browser automation                       | Playwright MCP tools are available in-session (screenshots for B.13 / AC13). This is not an npm dependency.                                                                                                                                                                                                                                                                                        |
| Verification commands (exist)            | `composer lint:check`, `composer types:check`, `composer test`, `composer ci:check`, `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `yarn check`, `yarn check:fix`, `yarn types:check`, `yarn build`.                                                                                                                                                                     |
| Baseline gate (today, before any change) | `yarn types:check` ✅ · `yarn check` ✅ · `php artisan test --compact` ✅ (2 passed) · `composer lint:check` ❌ **pre-existing** (`app/Outreach/Support/ApplicationTemplateRenderer.php`, `function_declaration`) · `composer types:check` ❌ **pre-existing crash** (PHPStan hit the 128M memory limit). The same analysis passes with `vendor/bin/phpstan analyse --memory-limit=1G` (0 errors). |

## Owner decisions

**Resolved 2026-09-26: the owner accepted the recommended option for all four
(D1 = A, D2 = A, D3 = A, D4 = A).** That includes the `migrate:fresh` data
wipe in D1. The options below are kept for traceability.

### D1 — Add `users.locale`/`users.timezone` by editing the `create_users_table` migration (needs `migrate:fresh`) or with a forward migration?

Blocks: Phase 3 (and every phase depending on it: 4, 5, 6 …) · Options:
**A (recommended, the spec's decision 8)** edit
`0001_01_01_000000_create_users_table.php` directly. The executor never runs
it. **The owner runs `php artisan migrate:fresh --seed`** right after Phase 3.
That wipes 1,892 postings, 485 companies, 1,532 contacts, 9 applications and
the Gmail connection; re-collect and reconnect afterwards. **B** add a forward
migration `add_locale_and_timezone_to_users_table` that the executor runs with
`php artisan migrate`. No data loss, but it deviates from decision 8. · Why:
decision 8 is final in the spec, but CLAUDE.md forbids the agent from running
`migrate:fresh`, and the data loss is real. The owner should confirm A with
that in mind. Answering in the Phase 1–2 message avoids a stall.

### D2 — PT/ES Laravel validation messages: hand-translate or keep English?

Blocks: Phase 31 · Options: **A (recommended)** run
`php artisan lang:publish` (no package; it writes `lang/en/*.php`), then write
`lang/pt/validation.php` and `lang/es/validation.php` by hand, translated from
the published English file. **B** keep English validation messages and report
it. · Why: B.8 says to add them "only if `lang:publish` is available without
new packages". The command is available but ships English only, so the PT/ES
content has to be written by hand, which is a product-copy choice.

### D3 — Top navigation items in this spec

Blocks: Phase 30 · Options: **A (recommended)** render the mockup's six items
in `client-app-screens` B.5 order: Dashboard, Jobs, Applications, Profiles,
Preferences, Plans. Only Dashboard links (Wayfinder `dashboard()`). The other
five render as non-link pills with `aria-disabled="true"` and no `href` until
`client-app-screens` adds their routes. **B** show only Dashboard. · Why: B.13
requires the top bar to match the mockup, but no routes exist for five of the
six items, and the spec forbids string-literal URLs.

### D4 — Lint and format `resources/js/components/ui/*`?

Blocks: nothing (Phase 1 applies it if answered) · Options: **A
(recommended)** remove `'resources/js/components/ui/*'` from both
`lint.ignorePatterns` and `fmt.ignorePatterns` in `vite.config.ts`, so every
primitive is linted and formatted by `yarn check`. **B** keep the starter-kit
exclusion. · Why: the exclusion is a shadcn leftover, and the whole B.4 kit
lives in that folder. If D4 has no answer when Phase 1 runs, the executor
leaves the config unchanged (B) and reports it.

### D5 — Text contrast vs. the locked mockup palette (raised in Phases 9–10)

**Resolved 2026-09-26: A (owner).** Darken only the text tokens that fail
4.5:1 (`accent-deep` as chip text on `accent-soft`, `success-text` on
`success-bg`), keeping hue; if `accent-deep` is also used as a fill, add a new
text token instead of changing it. `hero-accent` cards keep `text-ink` instead
of the mockup's white (white on the orange gradient is ~3:1). The deviation is
recorded here, not in `spec.md`. Option B (follow the mockup exactly and
accept the contrast failure) was declined.

## Global constraints (every phase)

- **Git is read-only.** No git writes by anyone, including subagents. No commit unless the owner says "commit this" in that message.
- **No destructive DB operations.** The executor runs only forward `php artisan migrate` / `php artisan db:seed`. `migrate:fresh --seed` (D1 = A) is run **by the owner only**.
- **Dependencies:** only the four B.2 packages, installed only in Phase 1 with explicit approval (`yarn add @tanstack/react-query lucide-react @headlessui/react` + `yarn add -D @tanstack/react-query-devtools`). Nothing else is allowed: no chart, animation, `cva`, i18n or Playwright npm package, and no font package. Use Yarn, never `npm install`.
- **No tests** are written or modified. Running the existing ones is fine.
- **English** for all code, comments and docs. UI copy lives in `lang/*.json`.
- **Visual identity is locked** to the mockup (spec decision 1). Reproduce, don't reinterpret. Read CSS values from `reference/dashboard-mockup.html`.
- **Tokens only (B.3):** components, features, pages and layouts contain no raw hex and no arbitrary px outside the B.3 list. Inline `style` is allowed only for computed geometry (bar heights, progress widths). The only hex exception is the logo dot fallback in `logo-mark.tsx` (AC01).
- **Variants:** use a typed variant map + `cn()` from `@/lib/utils`. No `cva`.
- **i18n:** every user-visible string goes through `t()`/`plural()`, including aria-labels, DevToolbar and styleguide copy. Every phase that adds keys adds them to `lang/en.json`, `lang/pt.json` and `lang/es.json` with the **same key set**, in natural Brazilian Portuguese and neutral Spanish, calm and direct. Keys are dotted (`"nav.dashboard"`).
- **URLs** come from Wayfinder helpers (`@/routes`, `@/actions`), never string literals. The only exceptions are the `#` placeholders B.10 and D3 allow. Only dev-only code imports the `dev.styleguide` helper, because that route exists only when `app()->isLocal()`.
- **No polling:** no `refetchInterval`, no `setInterval`/`setTimeout` that fetches, no `->poll()`/`wire:poll`. A display-only timer (countdown text) is fine.
- **Mutations** use TanStack `useMutation`. The only exceptions are auth pages, which use Inertia `<Form>`.
- **Accessibility (B.4 rule 7):** 2 px ink focus ring with a 2 px offset (`focus-visible`), full keyboard support, `aria-*` on custom controls, text contrast ≥ 4.5:1, and status always shown as icon + label, never color alone.
- **Motion** is CSS only. Every animation is off under `prefers-reduced-motion`.
- **Icons:** `lucide-react`, `strokeWidth={1.8}`, round caps and joins, 20 px default. The paper plane is an action icon only, never the logo.
- **States:** every data-showing component supports `loading` / `empty` / `error` where it makes sense (B.4 rule 5).
- **Reuse (B.4 rules 1 and 6):** extract on the second occurrence, and extend with a variant rather than forking.
- **Styleguide:** every phase that adds a component adds it to `/dev/styleguide` in all its variants and states, through a `features/styleguide/<group>-section.tsx` file.
- **Responsive (B.5):** breakpoints are `md` 768 (tablet), `lg` 1024 (laptop), `xl` 1280 (search collapses below it) and a custom `desk` = 1440 (`--breakpoint-desk: 90rem`). There must be no horizontal scroll at ≥ 360 px. Test widths: 360, 390, 768, 1024, 1280, 1440, 1600.
- **File naming:** kebab-case file names exporting PascalCase components (`components/ui/button.tsx` → `Button`), matching the spec's `logo-mark.tsx` / `dev-toolbar.tsx`. B.6's `patterns/Logo.tsx` is therefore `patterns/logo.tsx`. Pages are lowercase (`pages/auth/login.tsx`).
- **Folders (B.4):** `components/ui` (no domain knowledge), `components/patterns`, `features/<screen>` (built only from ui + patterns), `layouts`, `pages`, `data`, `i18n`, `lib`, `types`.
- **Filament** `/admin` and `/app` must keep working unchanged (AC17). No new client route under `/app/*`.
- **PHP gate per phase:** `vendor/bin/pint --dirty --format agent`, then `composer lint:check`. The only allowed failure is the pre-existing `ApplicationTemplateRenderer.php` one. Then `vendor/bin/phpstan analyse --memory-limit=1G`, because `composer types:check` crashes at 128M before any change. Then `php artisan test --compact`.
- **Frontend gate per phase:** `yarn check` and `yarn types:check`. Add `yarn build` whenever `app.tsx`, `vite.config.ts`, CSS or a lazy/dev-only import changes.

## Acceptance-criteria coverage

| AC                                                                                        | Phases                                                            |
| ----------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| AC01 tokens in `@theme`, no raw hex                                                       | 1 (tokens), 8–15, 18–30 (no-hex rule), 32 (grep)                  |
| AC02 self-hosted Geist, no external font request                                          | 1, 32                                                             |
| AC03 every primitive/pattern exists, typed, token-only, on styleguide in all states       | 8–15, 18–21, 25–29, 32                                            |
| AC04 `/dev/styleguide` 404 outside local                                                  | 7, 32                                                             |
| AC05 Logo SVG in 3 tones; brand from env everywhere                                       | 2, 5 (doc title), 15, 20 (top bar), 23 (landing), 24 (errors), 32 |
| AC06 landing content                                                                      | 23                                                                |
| AC07 login, translated error, throttle, logout → `/`, guest redirect                      | 19 (logout), 21 (auth + client), 22 (login + redirect), 32        |
| AC08 locale resolution, persistence, same key set                                         | 4, 5, 6, 18, 19, 32                                               |
| AC09 `SharedProps` exact, no full user model                                              | 4, 32                                                             |
| AC10 query provider, `apiFetch` + CSRF, keys, source switch, `useUserChannel`, no polling | 16, 17, 32                                                        |
| AC11 DevToolbar dev-only, switches plan/state/locale/Gmail/onboarding, absent from build  | 17, 18, 32                                                        |
| AC12 PlanGate behavior                                                                    | 29                                                                |
| AC13 responsive at 7 widths, no horizontal scroll                                         | 7–30 (per component), 30 (dashboard), 32                          |
| AC14 demo dashboard matches mockup at 1600                                                | 30                                                                |
| AC15 branded Inertia error pages when `APP_DEBUG=false`                                   | 24                                                                |
| AC16 `.env.example` keys appended                                                         | 2                                                                 |
| AC17 Filament `/admin` and `/app` unchanged                                               | 6, 21, 22, 24, 32                                                 |

## Phases

### Phase 1 — New dependencies, self-hosted Geist, design tokens

Status: DONE
Evidence: owner approved the B.2 dependencies (2026-09-26); the four packages are installed (devtools under devDependencies) and `yarn.lock` is updated; Geist woff2 (69,760 B) + OFL.txt are in `public/fonts/geist/`; `grep bunny|Instrument Sans|@fonts` over `vite.config.ts` and `resources/` is empty; all B.3 tokens, utilities and the reduced-motion block are in `app.css`; D4 applied; `yarn build` ✅, `yarn types:check` ✅; `yarn run check` fails only on 37 md/json files outside this phase (`.claude/**`, `docs/**`, `AGENTS.md`, …), none in `resources/` or `vite.config.ts`; code-reviewer APPROVED. `package-lock.json` is now stale (left untouched).
Role: inertia-frontend · Depends on: none · Covers: AC01, AC02 · Size: M
Spec: Part 0 decisions 1, 3 · B.2 · B.3 · B.5 (breakpoints)

**Goal.** Install the four approved packages, self-host Geist, and define
every B.3 token in Tailwind 4 `@theme` so later phases use only tokens.

**Contract.**

- Packages: `yarn add @tanstack/react-query@^5 lucide-react @headlessui/react@^2` and `yarn add -D @tanstack/react-query-devtools@^5`. Nothing else.
- Font: `public/fonts/geist/Geist-Variable.woff2` and `public/fonts/geist/OFL.txt`, downloaded with `node -e` + `fetch` (curl is blocked) from `raw.githubusercontent.com/vercel/geist-font/main/...`. Add `@font-face { font-family: "Geist"; src: url('/fonts/geist/Geist-Variable.woff2') format('woff2'); font-weight: 100 900; font-display: swap; }`.
- Remove every external font source: the `bunny(...)` block and its `laravel-vite-plugin/fonts` import in `vite.config.ts`, `@fonts` in `app.blade.php`, and 'Instrument Sans' in `app.css`.
- `resources/css/app.css` `@theme` defines exactly these names:
    - Colors: `--color-canvas #D6D2CB`, `--color-shell #EEECE8`, `--color-card #FFFFFF`, `--color-tile #F5F4F1`, `--color-ink #121212`, `--color-muted #6E6B65`, `--color-faint #A29E96`, `--color-hairline #E6E3DD`, `--color-accent #F26A1B`, `--color-accent-deep #E4520A`, `--color-accent-soft #FFF1E6`, `--color-accent-line #F9B98F`, `--color-dark #1A1917`, `--color-dark-2 #24231F`, `--color-dark-line #33322D`, `--color-dark-muted #A19D93`.
    - Status: `--color-success #1E9E5A`, `--color-success-bg #DDEFE4`, `--color-success-text #177A45`, `--color-danger #D64545`, `--color-danger-bg #F7DEDA`, `--color-danger-text #B23A2A`.
    - Disc tints: `--color-disc-orange #FFE4D0`, `--color-disc-neutral #E3E0D8`, `--color-disc-green #DDEFE4`, `--color-disc-red #F7DEDA`. Also `--color-scrim rgba(238,236,232,.78)`.
    - Radii: `--radius-shell 44px`, `--radius-card 36px`, `--radius-card-sm 28px` (mobile card), `--radius-panel 26px`, `--radius-tile 28px`, `--radius-row 24px`, `--radius-logo 16px`, `--radius-logo-lg 18px`, `--radius-logo-sm 14px`, `--radius-checkbox 8px`. Use Tailwind's `rounded-full` for pill.
    - Shadow: `--shadow-shell: 0 40px 90px rgba(40,30,15,.10)`.
    - Font: `--font-sans: "Geist", system-ui, sans-serif`. On `body`: `font-feature-settings: "tnum" 1`, antialiased, background `shell`.
    - Type scale as `--text-<name>` with `--text-<name>--line-height`, `--letter-spacing` and `--font-weight` sub-tokens: `display` 56/500/-0.045em + `display-sm` 34. `hero-numeral` 176/450/-0.065em lh 1 + `hero-numeral-sm` 112. `hero-suffix` 44/400/-0.03em + `hero-suffix-sm` 28. `numeral-lg` 64/450/-0.055em lh 1 + `numeral-lg-sm` 44. `card-title` 26/500/-0.03em + `card-title-sm` 22. `row-title` 17.5/550/-0.02em + `row-title-sm` 16. `body` 15/400. `label` 16/400 + `label-sm` 14. `chip` 13/500.
    - Breakpoint: `--breakpoint-desk: 90rem`.
    - Animations: `--animate-live-pulse` (box-shadow ring, 1.6 s infinite), `--animate-spin-ring` (0.9 s linear infinite), `--animate-shimmer` (skeleton), with their `@keyframes`.
- Custom utilities (`@utility`) for the values B.3 gives as literals, kept in CSS only: `bg-hero` (`linear-gradient(155deg, #FF7C33 0%, #F26A1B 45%, #DF4E08 100%)`), `bg-hatched` (`repeating-linear-gradient(135deg, rgba(255,255,255,.55) 0 2.5px, transparent 2.5px 7px), rgba(255,255,255,.14)`), `hero-circle` (flat decorative circle `rgba(255,255,255,.07)`), `focus-ring` (2 px ink ring, 2 px offset). Spacing literals from B.3 (shell `26 40 44`, gap 24, card 28 / 20 on mobile, tile `22 20 20`, row `14 20 14 16`, row gap 10, control heights 60/54/46/44) go into `--spacing-*` tokens where Tailwind's 4 px scale doesn't hit them exactly.
- `@media (prefers-reduced-motion: reduce)` disables all animations and transitions.
- D4 = A (decided): drop `'resources/js/components/ui/*'` from `lint.ignorePatterns` and `fmt.ignorePatterns`.

**Steps.**

1. Confirm the owner's approval text is in the message, then run the two `yarn add` commands.
2. Download the woff2 and OFL.txt into `public/fonts/geist/`.
3. Remove the bunny, `@fonts` and Instrument Sans references.
4. Write the tokens, utilities, keyframes and reduced-motion rules in `app.css`.
5. Apply D4 if answered.

**Done when.**

- `package.json` lists exactly the four new packages (devtools under `devDependencies`), and `yarn.lock` is updated.
- `grep -rn "bunny\|Instrument Sans\|@fonts" vite.config.ts resources/` returns nothing.
- `public/fonts/geist/Geist-Variable.woff2` and `OFL.txt` exist.
- Every B.3 token name above is present in `@theme`.
- `yarn check`, `yarn types:check` and `yarn build` pass.

**Not in this phase.** Any component, the favicon (Phase 15), and the
styleguide (Phase 7).

### Phase 2 — Brand/fixtures/locales config, env keys, SSR off

Status: DONE
Evidence: tinker shows brand/client/locales defaults and `inertia.ssr.enabled` false; `.env.example` additions only; `.env` keys appended; pint ✅, `composer lint:check` only the pre-existing `ApplicationTemplateRenderer.php` failure, phpstan (1G) 0 errors, `php artisan test --compact` 2 passed; code-reviewer APPROVED.
Role: laravel-backend · Depends on: none · Covers: AC16, AC05 (config part) · Size: S
Spec: B.6 · B.7 (`VITE_USE_FIXTURES`) · B.8 (locales) · Part 0 decision 5

**Goal.** Put the brand, fixtures flag and supported locales in config and
env, and turn off Inertia SSR.

**Contract.**

- `config/talent.php` gains:
    ```php
    'brand' => [
        'name' => env('BRAND_NAME', 'Talent Labs'),
        // Wordmark split: first part regular weight, second part bold.
        'wordmark' => [env('BRAND_WORDMARK_REGULAR', 'Talent'), env('BRAND_WORDMARK_BOLD', 'Labs')],
    ],
    'client' => [
        'use_fixtures' => (bool) env('VITE_USE_FIXTURES', false),
    ],
    'locales' => ['en', 'pt', 'es'],
    ```
- `config/inertia.php`: `'ssr' => ['enabled' => false, ...]`.
- `.env.example`: **append** `BRAND_NAME="Talent Labs"`, `BRAND_WORDMARK_REGULAR=Talent`, `BRAND_WORDMARK_BOLD=Labs` and `VITE_USE_FIXTURES=true`, with a comment line: `# Keep APP_NAME in sync with BRAND_NAME manually.` Do not change existing lines.
- `.env`: append only the missing keys above, and never touch existing values. Tell the owner to restart `composer dev` so Vite picks up `VITE_USE_FIXTURES`.

**Steps.** 1. Edit both config files. 2. Append the env keys. 3. Run the PHP gate.

**Done when.**

- `php artisan tinker --execute 'dump(config("talent.brand"), config("talent.client"), config("talent.locales"), config("inertia.ssr.enabled"));'` shows the defaults and `false`.
- `git diff .env.example` shows additions only.
- PHP gate passes (baseline failures excepted).

**Not in this phase.** Sharing any of this to the frontend (Phase 4).

### Phase 3 — `users.locale` + `users.timezone`

Status: DONE
Evidence: owner confirmed `migrate:fresh --seed` ran (2026-09-26); Boost schema shows `users.locale varchar(5)` and `users.timezone varchar(64)`; both seeded users have `locale = 'en'`; `User` fillable + phpdoc present; pint ✅, `php artisan test --compact` 2 passed.
Role: laravel-backend · Depends on: none · Covers: AC08 (storage) · Size: S
Spec: B.8 (last-but-one bullet) · Part 0 decision 8

**Goal.** Add the two user columns the locale feature and later specs need.

**Contract.**

- Columns: `$table->string('locale', 5)->default('en');` and `$table->string('timezone', 64)->nullable();`, placed after `status`.
    - D1 = A (decided): add them in `0001_01_01_000000_create_users_table.php`. No new migration file. The executor does **not** run any migrate command. It stops and asks the owner to run `php artisan migrate:fresh --seed`.
- `User`: add `'locale'` and `'timezone'` to `#[Fillable]`. Add phpdoc `@property string $locale` and `@property string|null $timezone`. No cast is needed.
- `timezone` is not read or written anywhere in this spec.

**Done when.**

- The owner has run `migrate:fresh --seed` and confirmed it.
- The `database-schema` (Boost) tool shows `users.locale varchar(5) default 'en'` and `users.timezone varchar(64) null`.
- The seeded client user exists with `locale = 'en'`.
- PHP gate passes.

**Not in this phase.** Reading or writing the locale (Phases 4 and 6).

### Phase 4 — Translation files + `SharedProps` contract

Status: DONE
Evidence: `share()` keys are exactly errors, app, auth, locale, locales, translations, flash (auth.user null for guests, whitelisted shape otherwise); lang/{en,pt,es}.json have identical key sets; `Translations::for` falls back to en; `types/shared.ts` matches B.9, `types/auth.ts` removed; pint ✅, `composer lint:check` only the pre-existing failure, phpstan (1G) 0 errors, `php artisan test --compact` 2 passed, `yarn types:check` ✅; code-reviewer APPROVED.
Role: laravel-backend (+ TS type files) · Depends on: 2, 3 · Covers: AC08 (files), AC09 · Size: M
Spec: B.8 (first two bullets) · B.9 (shared props)

**Goal.** Create the three JSON translation files and share exactly the
`SharedProps` shape. Stop sharing the full user model.

**Contract.**

- `lang/en.json`, `lang/pt.json` and `lang/es.json`, with the same key set. Initial keys:
    - `locale.en` "English", `locale.pt` "Português", `locale.es` "Español". These three are the same in every file.
    - `auth.failed` "These credentials do not match our records."
    - `auth.throttle` "Too many login attempts. Please try again in :seconds seconds."
    - `auth.inactive` "Your account is not active. Contact support if you think this is a mistake."
    - PT/ES are translated naturally.
- `app/Support/I18n/Translations.php`: `final class Translations { /** @return array<string,string> */ public static function for(string $locale): array }`. It returns `en.json` merged with `<locale>.json` (the locale wins), cached with `Cache::rememberForever("translations.{$locale}.".filemtime(...), ...)` so edits invalidate the cache. Unknown locales fall back to `en`.
- `HandleInertiaRequests::share()` returns exactly this (`...parent::share($request)` keeps `errors`):
    ```php
    'app' => ['brand' => ['name' => config('talent.brand.name'), 'wordmark' => config('talent.brand.wordmark')], 'env' => config('app.env'), 'useFixtures' => config('talent.client.use_fixtures')],
    'auth' => ['user' => $user ? ['id', 'name', 'email', 'initials', 'locale'] : null],
    'locale' => app()->getLocale(), 'locales' => config('talent.locales'),
    'translations' => Translations::for(app()->getLocale()),
    'flash' => ['success' => session('success'), 'error' => session('error')],
    ```
    `initials` is the first letter of the first and last name words, uppercased, from one word → one letter (e.g. "Vinicius Bassalobre" → "VB"). It is computed in a small `User::initials()` method. Remove the top-level `name` prop.
- `resources/js/types/shared.ts` exports `Locale = 'en' | 'pt' | 'es'`, `SharedUser` and `SharedProps`, exactly as in spec B.9. `types/global.d.ts` sets `sharedPageProps: SharedProps`. `types/auth.ts` is removed and `types/index.ts` re-exports `shared`.

**Done when.**

- The browser's `data-page` JSON on `/` (or `app(HandleInertiaRequests::class)->share(Request::create('/'))` in tinker) shows exactly the keys `app, auth, locale, locales, translations, flash, errors`, and `auth.user` is `null` for guests.
- `grep -n "'user' => \$request->user()" app/Http/Middleware/HandleInertiaRequests.php` returns nothing.
- The three JSON files have the same key set (`node -e` key comparison).
- PHP gate, `yarn check` and `yarn types:check` pass. `welcome.tsx` still compiles.

**Not in this phase.** Locale resolution (Phase 6) and the frontend `t()` (Phase 5).

### Phase 5 — Frontend i18n runtime, `lib/format`, root providers, doc title

Status: DONE
Evidence: `i18n/{locale,translate,i18n-provider}` + `lib/format.ts` added (`intlLocale` lives in `i18n/locale.ts` and is re-exported from `lib/format.ts` to avoid an import cycle); `app.tsx` wraps `I18nProvider` via `withApp`, title from shared brand, progress `var(--color-accent)`; `grep VITE_APP_NAME resources/js` empty; `yarn types:check` ✅, `yarn build` ✅, `yarn run check` fails only on pre-existing md/json files outside `resources/`; node one-off of translate/pluralize/format OK; code-reviewer APPROVED. Browser checks (`t('locale.pt')`, tab title after `BRAND_NAME` change) not run.
Role: inertia-frontend · Depends on: 4 · Covers: AC08 (frontend), AC05 (`<title>`) · Size: S
Spec: B.8 (frontend bullet) · B.4 (`app.tsx`, `i18n/`, `lib/`)

**Goal.** Provide `useT()` with `t`/`plural` and Intl formatting to every
page, and derive the document title from the shared brand.

**Contract.**

- `resources/js/i18n/translate.ts` exports pure `translate(dict, key, params?)`, which replaces `:name` and, when a key is missing in dev, calls `console.warn` and returns the key. It also exports `pluralize(dict, locale, key, count, params?)`, which picks `key.one` / `key.other` via `Intl.PluralRules` and injects `:count`.
- `resources/js/i18n/i18n-provider.tsx` exports `I18nProvider` plus `useT()` → `{ t, plural, locale, locales }`. The provider is seeded from `withApp`'s `page.props` and updated on `router.on('navigate', e => …)` (and `success`), so a locale switch re-renders every string.
- `resources/js/lib/format.ts` exports `intlLocale(locale)` (`pt → pt-BR`, `es → es-ES`, `en → en-US`) and `formatNumber`, `formatCurrency(value, currency)`, `formatDate(value, opts?)`, `formatRelativeTime(value)` and `formatList(items)` (`Intl.ListFormat`, conjunction). Each takes the locale explicitly. A `useFormat()` hook in the same file binds the current locale.
- `app.tsx`: keep `configureEcho({ broadcaster: 'reverb' })`. `createInertiaApp({ withApp(app, { page }) { return <I18nProvider initialPage={page}>{app}</I18nProvider> } })`. The title becomes `title ? \`${title} - ${brand}\` : brand`, where `brand`is`app.brand.name` from the current page props (kept in a module variable updated on navigate). **`VITE_APP_NAME`is no longer used.** Progress color is`var(--color-accent)`.

**Done when.**

- A temporary `t('locale.pt')` check in the browser console, or the Phase 7 styleguide, shows "Português".
- Changing `BRAND_NAME` in `.env` changes the tab title after a reload.
- `grep -rn VITE_APP_NAME resources/js` returns nothing.
- `yarn check`, `yarn types:check` and `yarn build` pass.

**Not in this phase.** The `LanguageSwitcher` (19) and the Toaster/QueryClient providers (14, 16).

### Phase 6 — `SetLocale` middleware + `PUT /locale`

Status: DONE
Evidence: `route:list --name=locale` shows `PUT locale … locale.update`; tinker resolver order verified (user with session → cookie → `es-ES,es;q=0.9` gives `es`, `pt_BR`/`pt-BR` give `pt`, unsupported gives `en`); `/admin/login` and `/app/login` return 200; `PUT /locale` gives 204 (JSON), 422 for `fr`, 302 back for a form request (CSRF required, 419 without); pint ✅, `composer lint:check` only the pre-existing failure, phpstan (1G) 0 errors, `php artisan test --compact` 2 passed (re-run after the last edit); code-reviewer APPROVED.
Role: laravel-backend · Depends on: 3, 4 · Covers: AC08, AC17 · Size: M
Spec: B.8 (locale resolution bullet) · B.9 (route `locale.update`)

**Goal.** Resolve the locale on every web request and let the client
change it persistently.

**Contract.**

- `app/Support/I18n/LocaleResolver.php` exports `public static function resolve(Request $request): string`. Order: `$request->user()?->locale` (only when `$request->hasSession()`) → the `locale` cookie → the first supported language in `$request->getLanguages()` (match on primary subtag, so `pt-BR` → `pt`) → `'en'`. It only returns values in `config('talent.locales')`.
- `app/Http/Middleware/SetLocale.php` calls `app()->setLocale(LocaleResolver::resolve($request))`.
- `bootstrap/app.php`:
    - `$middleware->web(append: [SetLocale::class, HandleInertiaRequests::class, AddLinkHeadersForPreloadedAssets::class])`, with SetLocale **before** HandleInertiaRequests.
    - `$middleware->encryptCookies(except: ['locale'])`, so error pages rendered outside the web group can read it.
- `app/Http/Requests/UpdateLocaleRequest.php` rules: `['locale' => ['required', 'string', Rule::in(config('talent.locales'))]]`.
- `app/Http/Controllers/LocaleController.php` has `update(UpdateLocaleRequest $request)`. If a user is logged in, it runs `$user->update(['locale' => …])`. It always queues `cookie('locale', $locale, 60 * 24 * 365)`. It returns `response()->noContent()` when `$request->expectsJson()`, else `back()`.
- Route: `Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');` in the `web` group, open to guests and users.

**Done when.**

- `php artisan route:list --name=locale` shows `PUT locale … locale.update`.
- The shared `locale` prop follows this order: user value → cookie → `Accept-Language: es-ES,es;q=0.9` gives `es` → otherwise `en`. Verify with tinker or a Pest-free HTTP check via `php artisan tinker` + `Request::create`.
- `/admin/login` and `/app/login` still render.
- PHP gate passes.

**Not in this phase.** The switcher UI (19) and the DevToolbar locale control (18).

### Phase 7 — `BareLayout` + `/dev/styleguide` scaffold (tokens, type scale)

Status: DONE
Evidence: route `dev.styleguide` only under `app()->isLocal()` (`route:list --env=production --path=dev` empty); BareLayout, styleguide page, StyleguideSection, TokensSection and 11 `styleguide.*` keys (identical in en/pt/es); `yarn types:check` ✅, `yarn build` ✅, pint ✅, `php artisan test --compact` 2 passed; code-reviewer APPROVED. Owner accepted the out-of-scope formatting from `check:fix`. Browser check at 360 px not run.
Role: inertia-frontend (+1 route line) · Depends on: 1, 5 · Covers: AC04, AC03 (surface), AC13 · Size: S
Spec: B.4 (`layouts/bare-layout.tsx`) · B.9 (route table, `dev.styleguide`) · B.12

**Goal.** Build the owner's review surface, which every later phase extends.

**Contract.**

- `routes/web.php`: `if (app()->isLocal()) { Route::inertia('/dev/styleguide', 'dev/styleguide')->name('dev.styleguide'); }`
- `layouts/bare-layout.tsx`: full-height `bg-shell` wrapper with a centered content slot, used by the landing, error and styleguide pages.
- `pages/dev/styleguide.tsx`: a `BareLayout` page with a sticky section index (anchor links) and a hint line "Resize to 360, 390, 768, 1024, 1280, 1440, 1600 px". It renders sections from `features/styleguide/*-section.tsx`.
- `features/styleguide/styleguide-section.tsx`: `StyleguideSection({ id, title, children })`.
- `features/styleguide/tokens-section.tsx`: color swatches (name + CSS variable, read from tokens), radii, shadow, the full type scale (desktop and mobile), and a motion demo.
- `styleguide.*` keys in the three lang files.

**Done when.**

- With `APP_ENV=local`, `/dev/styleguide` renders the tokens and type scale.
- With `APP_ENV=production` (`php artisan route:list --env=production --path=dev` shows nothing), the route is not registered, so it returns 404.
- `yarn check`, `yarn types:check` and PHP gate pass.

**Not in this phase.** Any component section.

### Phase 8 — Primitives I: Spinner, Button, IconButton, LiveDot, Kbd

Status: DONE
Evidence: Spinner, Button, IconButton, LiveDot, Kbd + styleguide actions section (all variants × sizes × states, dark-surface IconButton); dark/accent variants use a white 2px focus ring; `yarn types:check` ✅, `yarn build` ✅, hex grep over components empty, lang key sets identical; code-reviewer APPROVED after 1 correction round. Not viewed in a browser.
Role: inertia-frontend · Depends on: 7 · Covers: AC01, AC03, AC13 · Size: M
Spec: B.3 (icons, motion, control heights) · B.4 (`ui/`, rules 1–7)

**Goal.** Build the action primitives every later component uses.

**Contract.** Files are `components/ui/{spinner,button,icon-button,live-dot,kbd}.tsx` plus `features/styleguide/actions-section.tsx`.

- `Spinner({ size?: 'sm'|'md', tone?: 'ink'|'white'|'accent', label? })`: ring with `animate-spin-ring`, `role="status"`, and `aria-label` defaulting to `t('common.loading')`.
- `Button`:
    - `variant: 'primary-ink' | 'secondary-tile' | 'ghost' | 'ghost-on-dark' | 'on-accent-white'`, `size: 'lg' (60) | 'md' (54) | 'sm' (44)`.
    - Props: `loading?`, `iconLeft?`, `iconRight?`, `fullWidth?`, `asChild`-free. It renders an Inertia `<Link>` when `href` is given.
    - States: default, hover, `focus-visible` ring, pressed (`active:`), disabled (`aria-disabled` + no pointer), and loading (Spinner replaces the left icon, `aria-busy`).
- `IconButton({ icon, label, size: 60 | 46, bg: 'tile' | 'white', dot?: boolean })`: circle with `aria-label={label}` and an accent notification dot.
- `LiveDot`: accent dot with `animate-live-pulse`.
- `Kbd`: tile keycap.
- Keys: `common.loading` "Loading…".

**Done when.**

- The styleguide section shows every variant × size × state, including loading and disabled, and the IconButton dot.
- Components contain no hex (`grep -rnE "#[0-9a-fA-F]{3,8}\b" resources/js/components` returns empty).
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Pill and Chip (9).

### Phase 9 — Primitives II: Pill, Chip, Avatar, StatusDisc, ProgressBar, TickMeter

Status: DONE
Evidence: Pill, Chip, Avatar, StatusDisc, ProgressBar, TickMeter + styleguide indicators section (all variants, incl. dark surface and Pill dark group); D5 = A applied: `--color-accent-deep` #e4520a→#b44108 (5.13:1 on accent-soft, 4.66:1 on disc-orange), `--color-success-text` #177a45→#177844 (4.61:1 on success-bg), `danger-text` 4.65:1 unchanged; `yarn types:check` ✅, `yarn build` ✅, hex grep over components empty, lang key sets identical (63 keys); code-reviewer APPROVED after 1 correction round. Not viewed in a browser.
Role: inertia-frontend · Depends on: 8 · Covers: AC01, AC03, AC13 · Size: M
Spec: B.3 (disc tints, chip type) · B.4

**Goal.** Build the label, indicator and meter primitives.

**Contract.** Files are `components/ui/{pill,chip,avatar,status-disc,progress-bar,tick-meter}.tsx` plus `features/styleguide/indicators-section.tsx`.

- `Pill({ icon?, tone: 'white-on-accent' | 'tile' | 'dark' })`: static label pill ("Today's sending" style).
- `Chip({ variant: 'stack' | 'language' | 'plan' | 'delta-up' | 'delta-down' })`. `language` is ink bg, white text, `EN`/`PT`/`ES`. `plan` is `accent-soft` bg with `accent-deep` text. The delta variants carry a TrendingUp/Down icon and use success/danger text.
- `Avatar({ initials, size })`: initials circle.
- `StatusDisc({ status: 'sending' | 'done' | 'failed' | 'waiting' | 'icon', icon?, tint: 'orange'|'neutral'|'green'|'red', size })`. `sending` shows a spinner ring, `done` a check, `failed` an x, `waiting` a clock. It always has `aria-label`, so status is never color-only.
- `ProgressBar({ value, max, tone })`: thin and rounded, width via inline style, with `role="progressbar"` and aria values.
- `TickMeter({ total, filled, tone })`: N ticks, filled ratio (daily-limit tile).
- Keys: `status.sending`, `status.done`, `status.failed`, `status.waiting`.

**Done when.** The styleguide shows every variant, including on the dark card
background, with no hex. `yarn check` and `yarn types:check` pass.

**Not in this phase.** Card (10).

### Phase 10 — Primitives III: Card, Skeleton, EmptyState, ErrorState

Status: DONE
Evidence: Card (light/dark/hero-accent), Skeleton, EmptyState, ErrorState + styleguide surfaces section; `states.*` + `styleguide.surfaces.*` keys identical in en/pt/es; `yarn types:check` ✅, `yarn build` ✅, hex grep over components empty; code-reviewer APPROVED. OPEN OWNER DECISION: `hero-accent` uses `text-ink` (white on the orange gradient is ~3:1) whereas the mockup uses white text — same contrast-vs-mockup question as Phase 9. Not viewed in a browser.
Role: inertia-frontend · Depends on: 8 · Covers: AC03, AC13 · Size: S
Spec: B.3 (radii, padding) · B.4 (rule 5)

**Goal.** Build the surfaces and the shared loading, empty and error visuals.

**Contract.** Files are `components/ui/{card,skeleton,empty-state,error-state}.tsx` plus `features/styleguide/surfaces-section.tsx`.

- `Card({ tone: 'light' | 'dark' | 'hero-accent', padding?: 'default' | 'none', as? })`: `rounded-card` padding 28, mobile `rounded-card-sm` padding 20, no hover lift. `hero-accent` = `bg-hero` + `hero-circle`.
- `Skeleton({ shape: 'block' | 'line' | 'circle', className })`: shimmer, `aria-hidden`.
- `EmptyState({ icon?, title, description?, action? })`.
- `ErrorState({ title?, description?, onRetry? })`: defaults to `t('states.error.title')` and a retry `Button` labelled `t('states.error.retry')`.
- Keys: `states.empty.title` "Nothing here yet", `states.error.title` "Something went wrong", `states.error.description` "Please try again in a moment.", `states.error.retry` "Try again".

**Done when.** The styleguide shows the three card tones and every state,
responsive at 390 px. `yarn check` and `yarn types:check` pass.

**Not in this phase.** DataCard/HeroCard patterns (25).

### Phase 11 — Form I: Field, Input, Textarea, TagsInput, Checkbox, Switch

Status: PENDING
Role: inertia-frontend · Depends on: 8 · Covers: AC03, AC13 · Size: M
Spec: B.4 (`Input`, `Textarea`, `TagsInput`, `Switch`, `Checkbox`)

**Goal.** Build the text and boolean form controls with shared label, hint
and error markup.

**Contract.** Files are `components/ui/{field,input,textarea,tags-input,checkbox,switch}.tsx` plus `features/styleguide/forms-section.tsx`.

- `Field({ label, hint?, error?, id, children })`: the one place for label, hint and error markup (B.4 rule 1). It wires `aria-describedby` and `aria-invalid`.
- `Input` and `Textarea` are forwardRef'd, with `tile` bg and states default, focus, error and disabled.
- `TagsInput({ value: string[], onChange, placeholder? })`: Enter or comma adds, Backspace on empty removes the last tag, paste splits on commas, input is trimmed and de-duplicated. Each tag has a remove button with `aria-label={t('forms.tags.remove', { tag })}`.
- `Checkbox`: custom 26 px, `rounded-checkbox`, accent when checked, supports indeterminate, keyboard Space.
- `Switch`: Headless UI `Switch`, accent when on.
- Keys: `forms.tags.remove` "Remove :tag", `forms.optional` "Optional".

**Done when.** The styleguide shows every control in default, focus, error,
disabled and filled states, and TagsInput behaves as specified with the
keyboard only. `yarn check` and `yarn types:check` pass.

**Not in this phase.** Listbox-based controls (12).

### Phase 12 — Form II: Select, MultiSelect, RadioGroup, Segmented, Tabs

Status: PENDING
Role: inertia-frontend · Depends on: 9, 11 · Covers: AC03, AC13 · Size: M
Spec: B.3 (segmented 44) · B.4 · B.5 (segmented scrolls on mobile)

**Goal.** Build the Headless UI choice controls.

**Contract.** Files are `components/ui/{select,multi-select,radio-group,segmented,tabs}.tsx` plus `features/styleguide/choices-section.tsx`.

- `Select<T>({ value, onChange, options: { value: T; label: string }[], placeholder? })`: Headless UI `Listbox` inside `Field`.
- `MultiSelect<T>`: `Listbox multiple`, selected items shown as `Chip variant="stack"`.
- `RadioGroup<T>({ options: { value; title; description? }[] })`: card-style options, selected = `accent-soft` bg + `accent-line` border.
- `Segmented<T>({ value, onChange, options, ariaLabel })`: pill segmented control (active = card bg, items 44 high), Headless UI `RadioGroup` for arrow-key navigation, horizontally scrollable below `md`.
- `Tabs`: Headless UI `TabGroup` styled as pills.
- Keys: `forms.select.placeholder` "Select…", `forms.select.empty` "No options".

**Done when.** Every control works with the keyboard alone (Tab, arrows,
Enter, Esc), and the styleguide shows the states. `yarn check` and
`yarn types:check` pass.

**Not in this phase.** Overlays (13).

### Phase 13 — Overlays: Modal, Sheet, Popover, Menu, Tooltip

Status: PENDING
Role: inertia-frontend · Depends on: 8 · Covers: AC03, AC13 · Size: M
Spec: B.4 (`Modal`, `Sheet`, `Menu`, `Popover`, `Tooltip`)

**Goal.** Build the accessible overlay primitives.

**Contract.** Files are `components/ui/{modal,sheet,popover,menu,tooltip}.tsx` plus `features/styleguide/overlays-section.tsx`.

- `Modal({ open, onClose, title, description?, footer?, size? })`: Headless UI `Dialog`, `rounded-card`, focus trap, ESC, and a close `IconButton` with `t('common.close')`.
- `Sheet({ open, onClose, side: 'right' | 'bottom', title })`: mobile drawer (Dialog), safe-area padding.
- `Popover`: Headless UI `Popover` with anchor.
- `Menu({ trigger, items: { label; icon?; onSelect?|href?; danger? }[] })`: Headless UI `Menu`.
- `Tooltip({ content, children })`: CSS/Popover-based, shown on hover and focus, `role="tooltip"`, no library.
- Keys: `common.close` "Close".

**Done when.** The styleguide opens each overlay. Focus is trapped and
restored, ESC closes, and the overlays render correctly at 390 px.
`yarn check` and `yarn types:check` pass.

**Not in this phase.** Toast (14).

### Phase 14 — FileDrop + Toast (toaster, flash bridge)

Status: PENDING
Role: inertia-frontend · Depends on: 9, 10 · Covers: AC03, AC13 · Size: M
Spec: B.4 (`FileDrop`, `Toast`) · B.9 (`flash`)

**Goal.** Build the upload area and the toast system, and surface server
flash messages as toasts.

**Contract.** Files are `components/ui/{file-drop,toast}.tsx`, `lib/use-flash-toasts.ts`, `app.tsx`, `layouts/bare-layout.tsx` and `features/styleguide/feedback-section.tsx`.

- `FileDrop({ accept: 'application/pdf', maxSizeMb, progress?: number, error?: string, onFile })`: drag-and-drop + click + keyboard, with states idle, dragging, uploading (ProgressBar) and error.
- `toast.tsx` exports `Toast` (success/error/info, icon + text), a module store `toast.success|error|info(message)`, and `Toaster`. The toaster sits bottom-right on desktop and top on mobile (`< md`), uses `aria-live="polite"` and auto-dismisses after 5 s. The dismiss timer is display-only.
- `app.tsx` mounts `<Toaster />` inside `withApp`.
- `useFlashToasts()` reads `flash.success` / `flash.error` from `usePage()` and shows a toast once per visit. It is called in `BareLayout`, and later in `GuestLayout` and `AppLayout`.
- Keys: `forms.file.drop` "Drop your PDF here or click to choose", `forms.file.too_large` "The file is larger than :size MB.", `forms.file.wrong_type` "Only PDF files are accepted.", `common.dismiss` "Dismiss".

**Done when.** The styleguide shows every FileDrop state and buttons that
fire each toast type. Toast position is correct at 1440 and 390 px.
`yarn check`, `yarn types:check` and `yarn build` pass.

**Not in this phase.** Real uploads.

### Phase 15 — Logo, logo mark, favicons, brand in Blade

Status: PENDING
Role: inertia-frontend · Depends on: 7 · Covers: AC05, AC03 · Size: S
Spec: B.6

**Goal.** Make the brand swappable in one place, and generate the icons from
the approved mark.

**Contract.**

- `components/patterns/logo-mark.tsx` holds **exactly** the B.6 SVG (`viewBox="0 0 48 48"`, `aria-hidden`, path + rect with `currentColor`, circle `fill="var(--logo-dot, #F26A1B)"`). This is the only hex exception.
- `components/patterns/logo.tsx`: `Logo({ variant: 'full' | 'mark', tone: 'default' | 'inverse' | 'on-accent', size: 'sm' | 'md' | 'lg' })`.
    - `default`: ink mark + accent dot. `inverse`: white mark + accent dot. `on-accent`: ink mark + white dot (via `--logo-dot`).
    - The wordmark comes from `usePage().props.app.brand.wordmark`: part 1 weight 400, part 2 weight 650, tracking -0.03em. `aria-label` is the brand name.
    - The top-bar size is a 36 px mark.
- `public/favicon.svg`: the mark, ink on transparent.
- `public/apple-touch-icon.png`: 180×180, ink rounded square, white mark, accent dot. Rasterize with `qlmanage -t -s 180` or a Playwright MCP screenshot of a temporary SVG. If neither gives a faithful result, keep only the SVG favicon and report.
- `app.blade.php` links only `favicon.svg` + `apple-touch-icon.png` (drop the `favicon.ico` link, leave the file) and uses `<title>{{ config('talent.brand.name') }}</title>`.
- Styleguide: `features/styleguide/brand-section.tsx` shows the logo in all tones and sizes on light, dark and accent backgrounds.

**Done when.**

- The styleguide shows 3 tones × 2 variants.
- The page source `<title>` changes when `BRAND_NAME` changes.
- `diff` against the B.6 SVG shows the same path, rect and circle.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** TopBar (20) and the landing page (23).

### Phase 16 — Data layer core: query client, `apiFetch`, keys, source switch

Status: PENDING
Role: inertia-frontend · Depends on: 5 · Covers: AC10 · Size: S
Spec: B.7 (`query-client.ts`, `api.ts`, `keys.ts`, `source.ts`)

**Goal.** Provide the query cache and a typed, CSRF-aware fetch that every
later hook uses.

**Contract.**

- `data/query-client.ts`: one `QueryClient` with queries `{ staleTime: 30_000, gcTime: 300_000, retry: 1, refetchOnWindowFocus: true }` and mutations `{ retry: 0 }`. Header comment: `// refetchInterval is forbidden: no polling (CLAUDE.md, spec B.7).`
- `data/api.ts`:
    - `export class ApiError extends Error { status: number; errors?: Record<string, string[]> }`.
    - `export async function apiFetch<T>(url: string, init?: { method?: 'get'|'post'|'put'|'patch'|'delete'; body?: unknown; signal?: AbortSignal }): Promise<T>`.
    - Request: same-origin, `credentials: 'same-origin'`, headers `Accept: application/json`, `X-Requested-With: XMLHttpRequest`, `Content-Type: application/json` when there is a body, and `X-XSRF-TOKEN` = `decodeURIComponent` of the `XSRF-TOKEN` cookie.
    - Response: 204 → `undefined`. Non-2xx throws `ApiError`, keeping the 422 `errors` and using `message` from the JSON when present. Callers pass Wayfinder `.url`.
- `data/keys.ts`: `keys.dashboard()`, `keys.jobs.all()`, `keys.jobs.list(filters)`, `keys.applications.all()`, `keys.applications.list(filters)`, `keys.account.status()`. Keys are readonly tuples, and `filters` is typed `Record<string, unknown>` until `client-app-screens` owns the contracts.
- `data/source.ts`: `export const useFixtures = import.meta.env.VITE_USE_FIXTURES === 'true'` and `export function fromSource<F>(pair: { real: F; fixture: F }): F`, which returns the pair member by flag. Components never import `useFixtures` directly; only `data/` does.
- `types/vite-env.d.ts` declares `ImportMetaEnv.VITE_USE_FIXTURES?: string`.
- `app.tsx` wraps the app with `QueryClientProvider`. `ReactQueryDevtools` is loaded only when `import.meta.env.DEV`, via `lazy(() => import('@tanstack/react-query-devtools')…)`, so it is tree-shaken from production.

**Done when.**

- `grep -rn "refetchInterval\|setInterval" resources/js` shows only the comment.
- After `yarn build`, `grep -rl "ReactQueryDevtools\|react-query-devtools" public/build` is empty.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Fixtures and realtime (17).

### Phase 17 — Fixtures runtime + dev state + realtime (`useUserChannel`, emitter)

Status: PENDING
Role: inertia-frontend · Depends on: 16 · Covers: AC10, AC11 (state store) · Size: M
Spec: B.7 (`fixtures/`, `realtime/`, initialData rule)

**Goal.** Build the fixture machinery and realtime plumbing that
`client-app-screens` fills with domain data and events.

**Contract.**

- `types/plans.ts`: `export type PlanKey = 'free' | 'starter' | 'pro'; export const PLAN_KEYS: PlanKey[]`.
- `data/fixtures/dev-state.ts`:
    - Type: `DevState = { plan: PlanKey; state: 'normal'|'loading'|'empty'|'error'; gmail: 'connected'|'needs_reconnection'|'disconnected'; onboardingComplete: boolean; failNext: boolean }`.
    - Default: `{ plan: 'starter', state: 'normal', gmail: 'connected', onboardingComplete: true, failNext: false }`.
    - Exports `getDevState()`, `setDevState(patch)` and `useDevState()` (`useSyncExternalStore`). A change to `plan`, `state`, `gmail` or `onboardingComplete` calls `queryClient.invalidateQueries()`.
- `data/fixtures/runtime.ts`: `FIXTURE_LATENCY_MS = 350` (±40% random) and `fixtureCall<T>(resolve: () => T | Promise<T>, opts?: { empty?: () => T }): Promise<T>`. After the latency it applies the dev state:
    - `loading` returns a promise that never settles.
    - `error`, or `failNext` (which then resets), throws `new ApiError(500, …)`.
    - `empty` returns `opts.empty?.() ?? resolve()`.
    - `normal` returns `resolve()`.
- `data/fixtures/store.ts`: `createFixtureStore<S>(initial: S)` → `{ get(): S; set(updater: (s: S) => S): void; reset(): void }`, in memory, so mutations change later reads.
- `data/realtime/dev-emitter.ts`: `devEmitter.emit(event: string, payload: unknown)` and `devEmitter.subscribe(handler) → unsubscribe`. Simulations: `registerSimulation(name: 'send' | 'newJobs' | 'failure', run: () => void)`, `runSimulation(name)`, `useSimulations()` (which are registered). There are none in this spec; `client-app-screens` registers them.
- `data/realtime/use-user-channel.ts`:
    - Type: `type ChannelHandlers<E extends Record<string, unknown>> = { [K in keyof E]?: (payload: E[K], qc: QueryClient) => void }`.
    - Signature: `export function useUserChannel<E>(handlers: ChannelHandlers<E>): void`.
    - Real mode: `useEcho(\`App.Models.User.${id}\`, Object.keys(handlers).map(e => \`.${e}\`), …)`from`@laravel/echo-react`, on the private channel.
    - Fixtures mode: subscribes to `devEmitter` instead and never opens a socket.
    - No-op when there is no `auth.user` or no handlers.
- `data/define-query.ts` (a small helper): `initialDataFrom(prop)` documents the B.7 rule. A page hook takes the Inertia prop of the same shape and passes it as `initialData` when defined.

**Done when.**

- `setDevState({ state: 'error' })` from the console makes a styleguide demo read via `fixtureCall` (inside `features/styleguide/data-section.tsx`, fixture-only) show `ErrorState`. `loading` shows a Skeleton and `empty` shows EmptyState.
- No Echo connection is attempted in fixtures mode (browser network tab / Boost `browser-logs`).
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** Domain events, contracts and hooks (`client-app-screens`), and the DevToolbar UI (18).

### Phase 18 — `DevToolbar` + `useSetLocale`

Status: PENDING
Role: inertia-frontend · Depends on: 6, 12, 13, 14, 17 · Covers: AC11, AC08 · Size: M
Spec: B.7 (`DevToolbar`) · B.8 (switch locale)

**Goal.** Give the owner a dev-only floating control for plan, state,
locale, Gmail and onboarding, absent from production builds.

**Contract.**

- `data/hooks/use-set-locale.ts`: `useSetLocale()` = `useMutation` whose `mutationFn` comes from `fromSource({ real: putLocale, fixture: putLocale })`. Both are the same function, because translations are server-provided in both modes; add a comment saying so. `putLocale` is `apiFetch(localeUpdate().url, { method: 'put', body: { locale } })` via the Wayfinder `locale.update` helper. `onSuccess`: `router.reload()`.
- `components/patterns/dev-toolbar.tsx`: floating pill bottom-left that opens a `Popover` panel with:
    - `Segmented` plan (free/starter/pro) and `Segmented` state (normal/loading/empty/error).
    - `Segmented` locale (en/pt/es → `useSetLocale`) and `Select` Gmail (connected/needs reconnection/disconnected).
    - `Switch` onboarding complete.
    - Buttons "Simulate send", "Simulate new jobs" and "Simulate failure" → `runSimulation`. Each is disabled with a `Tooltip` `t('dev_toolbar.no_simulation')` when not registered.
    - A link to the styleguide (dev-only import of `dev.styleguide`).
- `app.tsx` mounts it only when `import.meta.env.DEV`, via a lazy dynamic import, so production has no chunk.
- Keys: `dev_toolbar.*` (title, plan, state, state.normal/loading/empty/error, locale, gmail, gmail.connected/needs_reconnection/disconnected, onboarding, simulate_send, simulate_new_jobs, simulate_failure, no_simulation "Available once the screens are built", styleguide). Plan names are `plans.free.name` "Free", `plans.starter.name` "Starter" and `plans.pro.name` "Pro" (translate "Free"; keep Starter/Pro).

**Done when.**

- In `yarn dev` the toolbar appears and every control changes `useDevState()`. The locale switch re-renders the styleguide in PT, and `users.locale` / the cookie update.
- After `yarn build`, `grep -rl "dev_toolbar\|DevToolbar" public/build` is empty.
- `yarn check`, `yarn types:check` and `yarn build` pass.

**Not in this phase.** The top-bar LanguageSwitcher (19).

### Phase 19 — NavPills, LanguageSwitcher, UserMenu + `POST /logout`

Status: PENDING
Role: inertia-frontend (+ small backend) · Depends on: 9, 13, 18 · Covers: AC03, AC07 (logout), AC08 · Size: M
Spec: B.4 (patterns) · B.5 (tablet/mobile variants) · B.9 (`logout`)

**Goal.** Build the top-bar building blocks, including a working logout.

**Contract.**

- `app/Http/Controllers/Auth/LogoutController.php` (invokable): `Auth::guard('web')->logout()`, invalidate the session, regenerate the token, `redirect()->route('home')`. Route: `Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');`
- `components/patterns/nav-pills.tsx`: `NavPills({ items: { key; label; href?: string; active?: boolean; disabled?: boolean }[] })`. Active = ink pill. The list scrolls horizontally from `md` to `lg`, and padding shrinks on laptop.
- `components/patterns/language-switcher.tsx`: globe icon + current code (`EN`). A `Menu` lists `t('locale.<key>')` and calls `useSetLocale`.
- `components/patterns/user-menu.tsx`: `UserMenu({ plan?: PlanKey })`. It shows the Avatar (initials), the name and a plan `Chip` (hidden when `plan` is undefined), plus chevron. On tablet (`md`–`lg`) it shows the avatar only. The `Menu` has language and a Log out item (Inertia `router.post(logout().url)`).
- `features/styleguide/navigation-section.tsx`: shows the three with demo items.
- Keys: `nav.dashboard` "Dashboard", `nav.jobs` "Jobs", `nav.applications` "Applications", `nav.profiles` "Profiles", `nav.preferences` "Preferences", `nav.plans` "Plans", `user_menu.open` "Open user menu", `user_menu.language` "Language", `user_menu.logout` "Log out", `language_switcher.label` "Change language".

**Done when.**

- `php artisan route:list --name=logout` shows the route.
- Logged in (e.g. via `/app/login`, same guard), the styleguide's UserMenu → Log out lands on `/`.
- Language switching from the switcher persists across reloads.
- `yarn check`, `yarn types:check` and PHP gate pass.

**Not in this phase.** TopBar assembly (20).

### Phase 20 — TopBar, MobileNav, PageHeader

Status: PENDING
Role: inertia-frontend · Depends on: 15, 19 · Covers: AC03, AC05 (top bar), AC13 · Size: M
Spec: B.4 (`TopBar`, `MobileNav`, `PageHeader`) · B.5

**Goal.** Assemble the responsive top bar and page header exactly as in the
mockup.

**Contract.** Files are `components/patterns/{top-bar,mobile-nav,page-header}.tsx`, `features/styleguide/shell-section.tsx` and the lang set.

- `TopBar({ nav, plan?, notificationsDot? })`:
    - Desktop layout: `Logo` (mark 36 px + wordmark), `NavPills`, spacer, search trigger (pill "Search" at ≥ `xl`, `IconButton` below `xl`, no behavior yet), bell `IconButton` with dot, `LanguageSwitcher`, `UserMenu`. Controls are 60 high.
    - Below `md`: logo + bell + avatar + menu `IconButton` that opens `MobileNav`.
- `MobileNav({ open, onClose, nav, plan? })`: a `Sheet` with nav items, language, plan chip and logout.
- `PageHeader({ eyebrow?, title, summary?: ReactNode, actions?: ReactNode })`: `display` / `display-sm` title. The summary line supports bold numbers. Actions sit on the right on desktop and become a full-width button under the title below `md`.
- Keys: `topbar.search` "Search", `topbar.notifications` "Notifications", `topbar.menu` "Open menu".

**Done when.** The styleguide top bar matches the mockup header at 1600 px
and follows B.5 at 1280, 1024, 768, 390 and 360 px with no horizontal
scroll. `yarn check` and `yarn types:check` pass.

**Not in this phase.** The layout and routes (21).

### Phase 21 — `AppLayout`, `client` middleware, `/dashboard` route (skeleton)

Status: PENDING
Role: inertia-frontend (+ small backend) · Depends on: 20 · Covers: AC07 (auth guard), AC13, AC17 · Size: M
Spec: B.4 (`layouts/app-layout.tsx`) · B.5 (shell) · B.9 (`dashboard`, `client` alias)

**Goal.** Build the authenticated shell and a valid dashboard page behind
`auth` + `client`.

**Contract.**

- `app/Http/Middleware/EnsureActiveClient.php`: when `! $request->user()->isActive()`, it logs out, invalidates and regenerates, then `redirect()->route('home')->with('error', __('auth.inactive'))`. Phase 22 changes the target to `login`. Admins pass. `bootstrap/app.php`: `$middleware->alias(['client' => EnsureActiveClient::class])`.
- Route: `Route::inertia('/dashboard', 'dashboard')->middleware(['auth', 'client'])->name('dashboard');`
- `lib/navigation.ts`: `useMainNav()` returns the nav items. In this phase that is only `{ key: 'dashboard', href: dashboard().url, active }`; D3 extends it in Phase 30.
- `layouts/app-layout.tsx`:
    - Desktop (≥ `desk`): `bg-canvas` page, centered `shell` container (max-width 1520, `rounded-shell`, margin 40, `shadow-shell`, padding 26 40 44).
    - Laptop: margin 24, padding 24/28. Tablet and mobile: no canvas, no radius, `bg-shell`.
    - Contents: `TopBar` + a `main` slot with a 12-col grid helper (`gap-24`) that becomes 2 columns at tablet and 1 on mobile.
    - It calls `useFlashToasts()`. It passes `plan` from `useDevState().plan` only when fixtures are on; otherwise it is undefined.
- `pages/dashboard.tsx` (skeleton): `AppLayout` + `PageHeader` with eyebrow = `formatDate(today)` and title `t('dashboard.title')` "Dashboard". Phase 30 replaces the body.

**Done when.**

- Logged in via `/app/login`, `/dashboard` renders the shell.
- A blocked user is logged out with the flash toast.
- Unauthenticated `/dashboard` redirects to the current guest target.
- `/admin` and `/app` still work.
- `yarn check`, `yarn types:check` and PHP gate pass.

**Not in this phase.** The client login page and the `redirectGuestsTo` change (22).

### Phase 22 — Login: controller, request, throttle, `GuestLayout`, login page

Status: PENDING
Role: laravel-backend (+ page) · Depends on: 11, 15, 21 · Covers: AC07, AC17 · Size: M
Spec: B.9 (route table, login bullets)

**Goal.** Build working client login at `/login` with translated errors and
throttling. Unauthenticated users land there.

**Contract.**

- `app/Http/Requests/Auth/LoginRequest.php`:
    - Rules: `email` required|string|email, `password` required|string, `remember` boolean.
    - `authenticate()`: `ensureIsNotRateLimited()` (key `Str::transliterate(Str::lower(email).'|'.ip)`, 5 attempts). `Auth::attempt(only(email, password), boolean('remember'))`. On failure: `RateLimiter::hit` and `ValidationException::withMessages(['email' => __('auth.failed')])`. On success: `RateLimiter::clear`. Throttled: `Lockout` event + `['email' => __('auth.throttle', ['seconds' => …])]`.
- `app/Http/Controllers/Auth/LoginController.php`: `create()` → `Inertia::render('auth/login')`. `store(LoginRequest)` → `authenticate()`, `session()->regenerate()`, `redirect()->intended(route('dashboard'))`.
- Routes (`guest`): `GET /login` → `login`, and `POST /login` → `login.store` with `throttle:6,1`.
- `bootstrap/app.php`: `redirectGuestsTo(fn () => route('login'))`. `EnsureActiveClient` now redirects to `login`.
- `layouts/guest-layout.tsx`: `bg-shell`, centered `Card` with `Logo`, calls `useFlashToasts()`.
- `pages/auth/login.tsx`: Inertia `<Form {...store.form()}>` (Wayfinder `login.store`) with `Field` + `Input` email, password, `Checkbox` remember, and a `Button primary-ink lg` with a loading state while processing. Errors show under the fields.
- Keys: `auth.login.title` "Log in", `auth.login.subtitle` "Welcome back.", `auth.login.email` "Email", `auth.login.password` "Password", `auth.login.remember` "Remember me", `auth.login.submit` "Log in".

**Done when.**

- The seeded client logs in and lands on `/dashboard`.
- Wrong credentials show `auth.failed` in the current locale.
- The 7th POST within a minute gets 429/throttle.
- Logout returns to `/`, and unauthenticated `/dashboard` → `/login`.
- `/admin/login` and `/app/login` still work.
- PHP gate, `yarn check` and `yarn types:check` pass.

**Not in this phase.** Password reset and registration (out of scope).

### Phase 23 — Landing page; remove `welcome.tsx`

Status: PENDING
Role: inertia-frontend · Depends on: 15, 22 · Covers: AC06, AC05 · Size: S
Spec: B.11 · B.9 (`home`, remove `welcome.tsx`)

**Goal.** Replace the starter welcome page with the reserved closed-beta
landing.

**Contract.**

- Route: `Route::inertia('/', 'landing')->name('home');`. Delete `resources/js/pages/welcome.tsx`.
- `pages/landing.tsx` in `BareLayout` shows only: a centered `Logo variant="full" size="lg"`, `t('landing.status')` "In development · Closed beta", muted `t('landing.subtitle')` "We are onboarding a small group of testers.", and one `Button primary-ink` pill. For guests it reads `t('landing.login')` "Go to login" and links to `login()`. When `auth.user` is set it reads `t('landing.dashboard')` "Open dashboard" and links to `dashboard()`.

**Done when.**

- `/` shows exactly those four things for a guest and for a logged-in user.
- `tests/Feature/ExampleTest` passes (`php artisan test --compact`).
- `grep -rn welcome resources/js/pages` is empty.
- `yarn check` and `yarn types:check` pass.

**Not in this phase.** The real landing page (later project).

### Phase 24 — Branded Inertia error pages

Status: PENDING
Role: laravel-backend (+ page) · Depends on: 6, 15, 21 · Covers: AC15, AC05, AC17 · Size: S
Spec: B.9 (error pages bullet)

**Goal.** Render 403, 404, 419, 500 and 503 through Inertia with the brand
when debug is off.

**Contract.**

- `bootstrap/app.php` `withExceptions`: `Inertia::handleExceptionsUsing(function (ExceptionResponse $response) { … })`, which renders only when all of these hold:
    - `! config('app.debug')`.
    - The status is in `[403, 404, 419, 500, 503]`.
    - `! $request->expectsJson()`, and the request is not Livewire (`X-Livewire` header).
    - The path is not `admin`, `admin/*`, `app` or `app/*`, so Filament stays unchanged.
- When it renders, it first calls `app()->setLocale(LocaleResolver::resolve($request))` (the web group may not have run on unmatched routes), then `return $response->render('errors/error', ['status' => $status])->withSharedData();`. Otherwise it returns `null`/`$response` per the vendor API.
- `pages/errors/error.tsx` in `BareLayout`: `Logo`, big numeral (`hero-numeral` scale, ink), `t('errors.<status>.title')` and `t('errors.<status>.message')`, and a `Button` "Back to dashboard" (`dashboard()`) when `auth.user` is set, else "Home" (`home()`).
- Keys:
    - `errors.403.title` "Forbidden" / message "You don't have access to this page."
    - `errors.404` "Page not found" / "The page you are looking for does not exist."
    - `errors.419` "Page expired" / "Your session expired. Refresh the page and try again."
    - `errors.500` "Server error" / "Something went wrong on our side. Try again in a moment."
    - `errors.503` "Be right back" / "We are doing some maintenance. Please come back soon."
    - `errors.back_to_dashboard` "Back to dashboard", `errors.home` "Home".

**Done when.**

- With `APP_DEBUG=false` (temporarily, and restore the owner's value after), `/does-not-exist` renders the branded 404 with translations for the cookie locale.
- 403/419/500/503 are verified without adding routes, by rendering through the handler in tinker (`app(ExceptionHandler::class)->render(Request::create('/x'), new HttpException(503))` → Inertia `errors/error` with `status` 503). If that isn't conclusive, mark it manual.
- `/admin/does-not-exist` keeps Filament/Laravel behavior.
- PHP gate, `yarn check` and `yarn types:check` pass.

**Not in this phase.** Custom Livewire error handling.

### Phase 25 — Card patterns: HeroCard, StatTile, DataCard, DarkCard, SectionHeader

Status: PENDING
Role: inertia-frontend · Depends on: 9, 10 · Covers: AC03, AC13 · Size: M
Spec: B.3 (hero gradient, hatched bars, type) · B.4 (patterns)

**Goal.** Build the dashboard card patterns exactly as in the mockup.

**Contract.** Files are `components/patterns/{hero-card,stat-tile,data-card,dark-card,section-header}.tsx` and `features/styleguide/cards-section.tsx`.

- `HeroCard({ label, icon, value, suffix, bars: number[], caption, stats: { label; value }[3], href?, loading? })`:
    - Top: `Card tone="hero-accent"`, `Pill` label, corner arrow `IconButton`.
    - Body: `hero-numeral` + `hero-suffix` (72% white), with a `clamp()`-sized numeral on mobile.
    - Hatched mini-bars (`bg-hatched`, heights via inline style), the caption, and a 3-cell stats strip.
- `StatTile({ label, icon, tint, value, unit?, delta?: { direction: 'up'|'down'; label }, context?, meter?: { total; filled }, loading? })`: tile bg, `StatusDisc icon`, `numeral-lg`, delta `Chip` or context line, optional `TickMeter`.
- `DataCard({ title, subtitle?, actions?, footer?, state?: 'ready'|'loading'|'empty'|'error', onRetry?, children })`: `card-title`. It renders `Skeleton` / `EmptyState` / `ErrorState` per `state`.
- `DarkCard`: `Card tone="dark"` with the header slot styled for dark (`dark-muted` subtitle).
- `SectionHeader({ title, action? })`.

**Done when.** The styleguide shows each in ready, loading, empty and error
states, compared against the mockup hero and KPI tiles at 1600 px, with the
mobile type scale below `md`. `yarn check` and `yarn types:check` pass.

**Not in this phase.** Composition into the dashboard (30).

### Phase 26 — Live patterns: LiveStepper, CountdownBar, ActivityRow

Status: PENDING
Role: inertia-frontend · Depends on: 9 · Covers: AC03, AC13 · Size: S
Spec: B.3 (motion) · B.4 (`LiveStepper`, `CountdownBar`, `ActivityRow`)

**Goal.** Build the live sending visuals.

**Contract.** Files are `components/patterns/{live-stepper,countdown-bar,activity-row}.tsx` and `features/styleguide/live-section.tsx`.

- `LiveStepper({ steps: { key; label }[5], activeIndex, failedIndex?, subStep?: string, tone: 'dark' })`:
    - Steps are done (check disc), active (spinner ring) or upcoming. A failed step uses the danger disc.
    - The connecting line fill transitions over 300 ms, and the sub-step line sits under the active step.
    - It has `aria-current="step"`.
- `CountdownBar({ startsAt: string, endsAt: string })`: the "Next application starts in 0:42" text (`t('live.next_in', { time })`), with progress computed from timestamps. It uses one display-only `requestAnimationFrame`/1 s tick that never fetches.
- `ActivityRow({ status, title, subtitle, time })`: `StatusDisc` + texts + `formatRelativeTime`, 200 ms status crossfade.
- Keys: `live.next_in` "Next application starts in :time". Stage names belong to `client-app-screens`, so the styleguide passes demo step labels through `styleguide.demo.*` keys.

**Done when.** The styleguide shows the stepper at each index plus the failed
variant, a running countdown, and every activity status. Reduced motion
stops the animations. `yarn check` and `yarn types:check` pass.

**Not in this phase.** Stage contracts (`client-app-screens`).

### Phase 27 — Row patterns: CompanyLogo, QueueRow, JobRow

Status: PENDING
Role: inertia-frontend · Depends on: 9, 11 · Covers: AC03, AC13 · Size: S
Spec: B.4 (`CompanyLogo`, `QueueRow`, `JobRow`) · B.5 (JobRow stacking)

**Goal.** Build the list-row patterns.

**Contract.** Files are `components/patterns/{company-logo,queue-row,job-row}.tsx` and `features/styleguide/rows-section.tsx`.

- `CompanyLogo({ name, size })`: initials tile. The tint is chosen deterministically from a hash of `name` over the four disc tints.
- `QueueRow({ company, title, meta, language: Locale, eta })`.
- `JobRow({ selected, onSelectedChange, company, title, meta, stack: string[], language, disabled? })`: `Checkbox`, `CompanyLogo`, title, meta, stack chips and language chip. Selected = `accent-soft` bg + `accent-line` border. Below `md` it stacks the title line, then the meta line, and chips wrap. The row has padding `14 20 14 16`, `rounded-row` and a 10 gap.

**Done when.** The styleguide shows rows unselected, selected and disabled,
correct at 390 px. `yarn check` and `yarn types:check` pass.

**Not in this phase.** Lists with data (`client-app-screens`).

### Phase 28 — BarChart (hand-rolled SVG)

Status: PENDING
Role: inertia-frontend · Depends on: 10, 13 · Covers: AC03, AC13 · Size: S
Spec: B.4 (`BarChart`) · B.2 (no chart library)

**Goal.** Build the mockup's bar chart without a library.

**Contract.** Files are `components/patterns/bar-chart.tsx` and `features/styleguide/chart-section.tsx`.

- `BarChart({ data: { label: string; value: number; isToday?: boolean }[], title: string, valueFormatter?, loading?, empty? })`: SVG with a viewBox that scales to the container.
- Bars: rounded 4–5 px data end with a 3 px cap. Zero days get a stub. The "today" bar is highlighted with an accent gradient (SVG `linearGradient` using `var(--color-accent)` tokens) and a value label.
- Axes: gridlines and axis labels in `faint`.
- Accessibility and states: `<title>` + a visually hidden `<table>` fallback, a hover/focus tooltip per bar, and Skeleton/EmptyState states.

**Done when.** The styleguide chart matches the mockup chart at 1600 px,
tooltips work by mouse and keyboard, and the chart scales at 390 px with no
hex. `yarn check` and `yarn types:check` pass.

**Not in this phase.** Range switching and data (`client-app-screens`).

### Phase 29 — PlanGate, StickyActionBar, FilterBar

Status: PENDING
Role: inertia-frontend · Depends on: 8, 12, 17 · Covers: AC12, AC03, AC13 · Size: S
Spec: B.10 · B.4 (`StickyActionBar`, `FilterBar`) · B.5 (sticky bar)

**Goal.** Build the plan-lock overlay and the two remaining layout patterns.

**Contract.** Files are `components/patterns/{plan-gate,sticky-action-bar,filter-bar}.tsx` and `features/styleguide/gating-section.tsx`.

- `PlanGate({ locked, requiredPlans: PlanKey[], featureKey: string, children })`:
    - Locked content: children render inside a wrapper with `inert` + `aria-hidden="true"`.
    - Overlay: covers the card with the card radius, scrim `bg-scrim` + `backdrop-blur-[4px]`, centered content.
    - Content: a 46 px disc with a `Lock` icon and the title `t('plan_gate.title', { plans: formatList(requiredPlans.map(p => t(\`plans.${p}.name\`))) })` "Available on :plans". Then `t(featureKey)`, a primary ink pill `t('plan_gate.upgrade', { plan: t(\`plans.${requiredPlans[0]}.name\`) })`"Upgrade to :plan", and a ghost pill`t('plan_gate.see_plans')`"See plans". Both link to`#`, since the Plans route does not exist yet.
    - Unlocked: children only.
- `StickyActionBar({ children })`: pinned to the bottom on mobile with `pb-[env(safe-area-inset-bottom)]`, and inline on desktop.
- `FilterBar({ children })`: horizontally scrollable filter row.

**Done when.** The styleguide shows PlanGate locked and unlocked around a
`StatTile`. When locked, Tab cannot reach the inner controls and the card
keeps its size. `yarn check` and `yarn types:check` pass.

**Not in this phase.** Real plan data (later specs).

### Phase 30 — Demo dashboard composition + visual gate

Status: PENDING
Role: inertia-frontend · Depends on: 21, 25–29 · Covers: AC14, AC13, AC01 · Size: M
Spec: B.13 · B.5 (card spans) · B.4 (`features/<screen>`)

**Goal.** Compose the mockup dashboard from the kit with the mockup's numbers,
for the owner's side-by-side approval.

**Contract.**

- `lib/navigation.ts`: apply D3 = A (decided). The six items are Dashboard (link), then Jobs, Applications, Profiles, Preferences and Plans (disabled, no href).
- `pages/dashboard.tsx`: the demo data is hardcoded **only in this file**, taken from the mockup. It contains:
    - `PageHeader`: eyebrow with date · mode, greeting `t('dashboard.greeting.afternoon', { name })`, summary with bold numbers. Actions: `Segmented` Today/Week/Month + `Button` "Browse jobs" (`plus` icon).
    - Grid row 1: `HeroCard` (5 cols) + 4 `StatTile`s in a 2×2 grid (7 cols).
    - Grid row 2: `DarkCard` with `LiveStepper` + `CountdownBar` + `QueueRow`s (7), `DataCard` with `BarChart` (5).
    - Grid row 3: `DataCard` with `JobRow`s (7), `DataCard` with `ActivityRow`s (5).
    - Tablet: 2 columns, hero and KPI full width, KPI 2×2. Mobile: single column.
- Screen-specific pieces go in `features/dashboard/*.tsx`, built only from ui + patterns (at most ~3 files, e.g. `dashboard-grid.tsx`).
- All labels are `dashboard.*` keys in the three files. Demo company names and job titles are data, not UI copy, and may stay literal in `pages/dashboard.tsx`.

**Done when.**

- Playwright MCP screenshots of `/dashboard` at 1600, 1024 and 390 px are shown to the owner beside `reference/dashboard-mockup.png`.
- There is no horizontal scroll at 360/390/768/1024/1280/1440/1600 (`document.documentElement.scrollWidth <= innerWidth`).
- `yarn check`, `yarn types:check` and `yarn build` pass.
- Status becomes `DONE (awaiting owner visual approval)`. It becomes `DONE` only once the owner approves (AC14).

**Not in this phase.** Fixture-driven data (`client-app-screens`).

### Phase 31 — PT/ES Laravel validation messages

Status: PENDING
Role: laravel-backend · Depends on: 4 · Covers: B.8 (backend strings) · Size: S
Spec: B.8 (last bullet)

**Goal.** Localize Laravel's validation messages for pt and es, or record
that they stay English.

**Contract.**

- D2 = A (decided): run `php artisan lang:publish`, which creates `lang/en/{auth,pagination,passwords,validation}.php`. Then write `lang/pt/validation.php` and `lang/es/validation.php` with the same keys as `lang/en/validation.php`, translated. Keep `attributes` empty. The JSON `auth.*` keys keep precedence over `lang/*/auth.php`.

**Done when.**

- A: with `app()->setLocale('pt')`, `Validator::make(['email' => ''], ['email' => 'required'])->errors()->first()` is Portuguese, the same holds for Spanish, and `auth.failed` still comes from the JSON.
- PHP gate passes.

**Not in this phase.** Mail templates.

### Phase 32 — Verification and report

Status: PENDING
Role: qa-tester · Depends on: 1–31 · Covers: AC01–AC17 · Size: S
Spec: Acceptance criteria · Verification

**Goal.** Prove every AC and hand the owner a manual checklist.

**Steps.**

1. Full gate:
    - `composer ci:check`, or its parts: `yarn check`, `yarn types:check`, `composer lint:check` (only the pre-existing failure is allowed), `vendor/bin/phpstan analyse --memory-limit=1G`, `php artisan test --compact`.
    - Then `yarn build`.
2. Greps:
    - AC01: `grep -rnE "#[0-9a-fA-F]{3,8}\b" resources/js/{components,features,pages,layouts}` → only `logo-mark.tsx`.
    - AC10: `grep -rn "refetchInterval\|setInterval\|->poll(\|wire:poll" resources/ app/`.
    - AC02: `grep -rn "bunny\|fonts.googleapis\|Instrument Sans" vite.config.ts resources/`.
    - AC11: `grep -rl "DevToolbar\|dev_toolbar\|react-query-devtools" public/build`.
    - `grep -rn "'user' => \$request->user()" app/`.
    - The three lang JSONs have the same key set.
3. AC walkthrough, AC01–AC17, each marked ✅, ❌ or "manual":
    - Login with the seeded client, wrong password, 7 rapid attempts, logout.
    - Locale by user, cookie and `Accept-Language`.
    - `/dev/styleguide` 404 with `--env=production` route list.
    - Brand env swap.
    - Error pages with `APP_DEBUG=false` (restore it after).
    - `/admin` and `/app` login + pages.
4. Responsive check: Playwright MCP at 360, 390, 768, 1024, 1280, 1440 and 1600 on `/dashboard`, `/`, `/login` and `/dev/styleguide`. No horizontal scroll.
5. Report the leftovers: stale `package-lock.json`, `favicon.ico` still the Laravel default, the pre-existing lint and PHPStan baseline failures, and any AC marked manual.

**Done when.** This file's status board is updated, the report lists every AC
with evidence, and the owner's manual checklist is included:

- Log in as the seeded client.
- Compare `/dashboard` with the mockup at 1600 px.
- Open `/dev/styleguide`.
- Switch EN/PT/ES.
- Resize through the test widths.
- Use the DevToolbar.
- Check `/admin` and `/app`.

**Not in this phase.** Fixing findings. Those go back to the owning phase.
