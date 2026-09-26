# client-app-foundation — Inertia client app: design system, shell, data layer, i18n, login

> **Order:** 1 of 10 (see "Roadmap" at the end). **Depends on:** nothing.
> **Kind:** frontend-first. Almost everything here is React/Tailwind. The only
> backend is the minimum needed to render pages and log in.
>
> **How to run:** `/plan-spec docs/features/client-app-foundation/spec.md`,
> then `/execute-phases` phase by phase. The owner approves the new
> dependencies listed in B.2 in the phase message that installs them.

## Part 0 — Context and decisions (owner, final)

talent-labs today has two Filament panels: `/admin` (admin) and `/app`
(client: `App\Filament\App\Pages\{Jobs,Preferences,Applications}`). The client
side is being rebuilt as an **Inertia 3 + React 19 + Tailwind 4 app** inside
the same Laravel monolith. Filament stays for `/admin` only.

The product is being built **frontend first**: this spec builds the design
system, the app shell, the data layer and the component kit; the next spec
(`client-app-screens`) builds every screen as a navigable prototype on typed
fixtures; backend features are wired in by later specs. The owner is a very
visual person and wants to see the product before the features behind it.

Decisions that bind this spec:

1. **Visual identity is locked.** The approved mockup is
   `reference/dashboard-mockup.html` (+ `.png`). Palette, type, radii,
   components and layout are reproduced **exactly**. Do not "improve" or
   reinterpret it. Only the brand name and logo mark are swappable (B.6).
2. **Responsive** (desktop, tablet, mobile). The mockup is desktop only; this
   spec defines the responsive rules (B.5).
3. **Tailwind 4 only**, all tokens in `@theme`, **maximum componentization**
   in three layers (B.4). No hardcoded hex/px in components.
4. **Data layer:** Inertia props for first paint + **TanStack Query** for
   everything dynamic + `useMutation` for every in-app mutation +
   realtime events writing into the query cache. **Never polling**
   (no `refetchInterval`, no `setInterval` fetching, no `->poll()`).
   Fixtures mode for the frontend-first phases (B.7).
5. **No Inertia SSR Node process.** Server data arrives through Inertia props.
6. **i18n from day 1:** English, Portuguese, Spanish (B.8). No hardcoded UI
   strings, including this spec's components.
7. **Filament `/app` panel is NOT removed here.** It keeps working until
   `plans-and-sending-modes` removes it. New client routes must not collide
   with `/app/*` (B.9).
8. Hard rules from `CLAUDE.md` apply (git read-only, no tests unless asked,
   English in repo, realtime not polling, scope). **Migrations:** the project
   is in development; change the existing `create_*` migrations directly, do
   not add `alter` migrations; the owner runs `php artisan migrate:fresh --seed`.

## Part B — Product spec

### B.1 Files to read first

- `reference/dashboard-mockup.html` — the source of truth for look and CSS
  values (open it in a browser at ≥1600 px width). `reference/dashboard-mockup.png`
  is a render of it. `reference/style-ref-1..4.png` are the inspirations the
  owner chose (structure only, never copy their brands).
- `resources/js/app.tsx`, `resources/js/pages/welcome.tsx`,
  `resources/js/lib/utils.ts`, `resources/js/types/*`, `resources/css/app.css`,
  `resources/views/app.blade.php`, `vite.config.*`, `package.json`.
- `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`,
  `routes/channels.php`, `bootstrap/app.php`, `bootstrap/providers.php`.
- `app/Models/User.php`, `database/seeders/UserSeeder.php`, `config/talent.php`.
- Skills: `inertia-react-development`, `tailwindcss-development`,
  `wayfinder-development`, `project-core`.

### B.2 Dependencies (owner approves in the phase message)

Already installed and reused: `react` 19, `@inertiajs/react` 3, `tailwindcss` 4,
`clsx`, `tailwind-merge`, `laravel-echo`, `@laravel/echo-react`, `pusher-js`,
Wayfinder.

New (npm, via `yarn add`):

| Package                                | Why                                                                                                                 |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `@tanstack/react-query` (v5)           | Query cache, `useQuery`, `useMutation` (decision 4)                                                                 |
| `@tanstack/react-query-devtools` (dev) | Dev-only inspector, not in production bundle                                                                        |
| `lucide-react`                         | The icon family used by the mockup (stroke icons, round caps)                                                       |
| `@headlessui/react` (v2)               | Accessible Dialog, Menu, Listbox, Switch, Tabs, Combobox, Popover. Verify React 19 support in the installed version |

Nothing else. **No** chart library (charts are hand-rolled SVG), **no**
animation library (CSS only), **no** `class-variance-authority`
(variant maps with `clsx` + `tailwind-merge`), **no** i18n library (B.8),
**no** Playwright.

Fonts: **Geist** (SIL Open Font License), self-hosted. Download
`Geist-Variable.woff2` from `github.com/vercel/geist-font`
(`packages/next/dist/fonts/geist-sans/Geist-Variable.woff2`) into
`public/fonts/geist/`. Keep the license file next to it. No npm package.

### B.3 Design tokens (exact, from the mockup)

Define in `resources/css/app.css` with Tailwind 4 `@theme`. Names below are
the contract; components use only these tokens.

Colors:

| Token                 | Value                                                               | Use                                                  |
| --------------------- | ------------------------------------------------------------------- | ---------------------------------------------------- |
| `--color-canvas`      | `#D6D2CB`                                                           | page background outside the shell (desktop only)     |
| `--color-shell`       | `#EEECE8`                                                           | app container / page background on tablet and mobile |
| `--color-card`        | `#FFFFFF`                                                           | cards                                                |
| `--color-tile`        | `#F5F4F1`                                                           | tiles, list rows, inactive controls                  |
| `--color-ink`         | `#121212`                                                           | primary text, primary button, active nav pill        |
| `--color-muted`       | `#6E6B65`                                                           | secondary text                                       |
| `--color-faint`       | `#A29E96`                                                           | placeholders, timestamps, axis labels                |
| `--color-hairline`    | `#E6E3DD`                                                           | separators                                           |
| `--color-accent`      | `#F26A1B`                                                           | the single accent                                    |
| `--color-accent-deep` | `#E4520A`                                                           | accent text on light, hover                          |
| `--color-accent-soft` | `#FFF1E6`                                                           | selected rows, plan chip bg                          |
| `--color-accent-line` | `#F9B98F`                                                           | selected row border                                  |
| `--color-dark`        | `#1A1917`                                                           | live sending card                                    |
| `--color-dark-2`      | `#24231F`                                                           | inner panels of the dark card                        |
| `--color-dark-line`   | `#33322D`                                                           | lines/inactive on dark                               |
| `--color-dark-muted`  | `#A19D93`                                                           | secondary text on dark                               |
| `--color-success`     | `#1E9E5A` (bg `#DDEFE4`, text `#177A45`)                            | status only                                          |
| `--color-danger`      | `#D64545` (bg `#F7DEDA`, text `#B23A2A`)                            | status only                                          |
| disc tints            | orange `#FFE4D0`, neutral `#E3E0D8`, green `#DDEFE4`, red `#F7DEDA` | icon discs                                           |

Hero gradient (only for the one hero card per screen):
`linear-gradient(155deg, #FF7C33 0%, #F26A1B 45%, #DF4E08 100%)`, with one flat
decorative circle `rgba(255,255,255,.07)` top-right. Hatched mini-bars:
`repeating-linear-gradient(135deg, rgba(255,255,255,.55) 0 2.5px, transparent 2.5px 7px), rgba(255,255,255,.14)`.

Radii: `--radius-shell: 44px`, `--radius-card: 36px`, `--radius-panel: 26px`,
`--radius-tile: 28px`, `--radius-row: 24px`, `--radius-logo: 16px`
(18 large / 14 small), `--radius-pill: 9999px`, checkbox `8px`.

Shadow (shell only): `0 40px 90px rgba(40,30,15,.10)`.

Font: `--font-sans: "Geist", system-ui, sans-serif`, variable weight 100–900,
`font-feature-settings: "tnum" 1`, antialiased. One family only.

Type scale (size / weight / tracking; line-height 1 for numerals):

| Name                               | Desktop                       | Mobile |
| ---------------------------------- | ----------------------------- | ------ |
| `display` (page title)             | 56 / 500 / -0.045em           | 34     |
| `hero-numeral`                     | 176 / 450 / -0.065em          | 112    |
| `hero-suffix` ("/ 50")             | 44 / 400 / -0.03em, 72% white | 28     |
| `numeral-lg` (KPI, chart headline) | 62–64 / 450 / -0.055em        | 44     |
| `card-title`                       | 26 / 500 / -0.03em            | 22     |
| `row-title`                        | 17.5 / 550 / -0.02em          | 16     |
| `body`                             | 15 / 400                      | 15     |
| `label`                            | 15–16 / 400, muted            | 14     |
| `chip`                             | 12.5–13 / 500–650             | same   |

Spacing: shell padding `26 40 44` (desktop), grid gap 24, card padding 28,
tile padding `22 20 20`, row padding `14 20 14 16`, row gap 10. Control
heights: topbar controls 60, primary button 60 (page header) / 54 (card
footer), segmented items 44.

Icons: `lucide-react`, stroke width 1.8, round caps/joins, 20 px default.
Mapping used by the mockup: send (paper-plane icon is allowed as an
_action_ icon, not as the logo), bell, search, globe, chevron-down, plus,
arrow-up-right, check, x, clock, layers, trending-up/down arrows, sliders,
pause, radio (live).

Motion (CSS only, all disabled under `prefers-reduced-motion`): live dot
pulse (box-shadow ring, 1.6 s), spinner ring for the active step/`sending`
disc (0.9 s linear), number count-up on change (≤ 500 ms), stepper line fill
transition (300 ms), row status crossfade (200 ms), card hover lift none (keep flat).

### B.4 Component architecture (Tailwind + reuse)

```
resources/js/
  app.tsx                     # providers: QueryClientProvider, I18nProvider, Echo, devtools (dev)
  layouts/
    app-layout.tsx            # authenticated shell (topbar + page container)
    guest-layout.tsx          # auth pages (centered card on shell background)
    bare-layout.tsx           # landing / errors
  components/
    ui/                       # primitives, no domain knowledge
    patterns/                 # composed, reusable across screens
  features/<screen>/          # screen-specific pieces, built ONLY from ui/ + patterns/
  pages/                      # Inertia pages (lowercase file names, like pages/welcome.tsx)
  data/                       # query client, api client, query keys, hooks, fixtures, realtime
  i18n/                       # provider, t(), plural helper
  lib/                        # cn(), format (Intl), constants
  types/                      # contracts.ts (owned by client-app-screens), shared types
```

**`ui/` primitives (build all of them in this spec, each with every state):**
`Button` (variants: primary-ink, secondary-tile, ghost, ghost-on-dark,
on-accent-white; sizes: lg 60, md 54, sm 44; states: default, hover,
focus-visible ring, pressed, disabled, loading with spinner, icon-left/right),
`IconButton` (circle 60 / 46; tile or white bg; optional notification dot),
`Pill` (static label pill, e.g. "Today's sending"), `Chip` (stack chip,
language tag black `EN/PT/ES`, plan chip accent-soft, delta chip up/down),
`StatusDisc` (spinner/check/x/clock/icon; tints), `Card` (light / dark /
hero-accent), `Segmented` (pill segmented control, keyboard accessible),
`Tabs`, `Input`, `Textarea`, `TagsInput` (Enter/comma adds, Backspace
removes, paste splits on commas), `Select`/`Listbox`, `MultiSelect`,
`Switch`, `Checkbox` (custom 26 px, accent when checked), `Radio group`
(card-style options), `FileDrop` (PDF upload area with progress and error),
`Modal` (Headless UI Dialog; radius-card; focus trap; ESC), `Sheet`
(mobile drawer), `Menu` (dropdown), `Popover`, `Toast` (success/error/info,
bottom-right desktop, top on mobile), `Tooltip`, `ProgressBar` (thin, rounded),
`TickMeter` (N ticks, filled ratio, like the daily-limit tile), `Skeleton`
(block/line/circle with shimmer), `EmptyState`, `ErrorState` (with retry),
`Avatar` (initials), `Kbd`, `Spinner`, `LiveDot`.

**`patterns/`:** `Logo` (B.6), `TopBar` (brand, `NavPills`, search trigger,
notifications `IconButton`, `LanguageSwitcher`, `UserMenu` with plan chip),
`NavPills` (active = ink pill; horizontally scrollable on tablet),
`MobileNav` (Sheet), `PageHeader` (eyebrow, display title, summary line with
bold numbers, right-side actions), `HeroCard` (accent gradient card with label
pill, corner arrow button, numeral + suffix, hatched mini-bars, caption,
3-cell stats strip), `StatTile` (label, disc icon, numeral with unit, delta
chip or context line, optional `TickMeter`), `DataCard` (card with title,
subtitle, header actions, body, optional footer), `DarkCard`,
`LiveStepper` (5 steps, done/active/upcoming, sub-step line under the active
step, failed variant), `CountdownBar` ("Next application starts in 0:42" with
progress fill computed from timestamps), `QueueRow` (logo tile, title,
meta, language tag, ETA), `JobRow` (checkbox, logo tile, title, meta, stack
chips, language tag; selected state), `ActivityRow` (status disc, title,
subtitle, time), `BarChart` (hand-rolled SVG: rounded 4–5 px data end,
3 px cap, gridlines, highlighted "today" bar with gradient and value label,
stub for zero days, axis labels, accessible `<title>`/table fallback, hover
tooltip per bar), `CompanyLogo` (initials tile, deterministic tint from a
fixed palette of the disc tints), `PlanGate` (overlay, B.10),
`StickyActionBar`, `FilterBar`, `SectionHeader`, `DevToolbar` (B.7).

**Rules (reviewed on every phase):**

1. No duplicated markup: the second time something appears, extract it.
2. Variants via a typed variant map + `cn()` (`clsx` + `tailwind-merge`,
   existing `lib/utils.ts`). No inline `style` except computed geometry
   (chart bars, progress widths).
3. Only tokens from B.3. No raw hex, no arbitrary px values outside the token
   list (arbitrary values allowed only for the exact mockup measurements
   listed in B.3).
4. Every user-visible string goes through `t()` (B.8).
5. Every component that shows data supports `loading`, `empty` and `error`
   where it makes sense.
6. Before creating a new component in a later spec, reuse an existing one;
   extend with a variant rather than forking.
7. Accessibility: visible focus ring (2 px ink, 2 px offset), keyboard
   navigation for all controls, `aria-*` on custom controls, contrast
   ≥ 4.5:1 for text, never color alone for status (icon + label).

### B.5 Responsive rules

- **≥ 1440 px (desktop):** exactly the mockup. Page background
  `canvas`, a centered `shell` container (max-width 1520, radius-shell,
  margin 40, shadow). 12-col grid, gap 24. Card spans as in the mockup
  (hero 5 / KPIs 7, live 7 / chart 5, matches 7 / activity 5).
- **1024–1439 (laptop):** same structure; shell margin 24, padding 24/28;
  grid still 12 cols; `NavPills` may shrink padding; search collapses to an
  icon button below 1280.
- **768–1023 (tablet):** no outer canvas; page background `shell`, no shell
  radius. Grid becomes 2 columns; hero and KPI card full width; KPI tiles in
  a 2×2 grid; `NavPills` horizontally scrollable; user chip shows avatar
  only.
- **< 768 (mobile):** single column. Top bar: logo + notifications + avatar
    - menu button opening `MobileNav` (sheet with the nav items, language,
      plan, logout). Page header actions become a full-width button under the
      title; segmented control scrolls. Type scale uses the mobile column in
      B.3. Card radius 28, card padding 20. `JobRow` stacks: title line, meta
      line, chips wrap. Sticky action bar pinned to the bottom with safe-area
      inset. Hero numeral uses `clamp()`.
- No horizontal page scroll at any width ≥ 360 px. Test widths: 360, 390,
  768, 1024, 1280, 1440, 1600.

### B.6 Brand and logo (swappable in one place)

- Config: add to `config/talent.php`:
    ```php
    'brand' => [
        'name' => env('BRAND_NAME', 'Talent Labs'),
        // Wordmark split: first part regular weight, second part bold.
        'wordmark' => [env('BRAND_WORDMARK_REGULAR', 'Talent'), env('BRAND_WORDMARK_BOLD', 'Labs')],
    ],
    ```
    Shared to the frontend as `app.brand` (B.9). `.env.example` gets the keys
    (append only, never overwrite existing `.env` values). Also keep
    `APP_NAME` in sync manually (documented in `.env.example`).
- `patterns/Logo.tsx` renders the mark + wordmark; props: `variant`
  (`full` | `mark`), `tone` (`default` on light: ink mark + accent dot;
  `inverse` on dark: white mark + accent dot; `on-accent`: ink mark + white
  dot), `size`. The mark SVG lives in **one file**
  `resources/js/components/patterns/logo-mark.tsx` so swapping the logo is a
  one-file change. Exact mark (approved, see `reference/logo-t-dot.png`):

    ```svg
    <svg viewBox="0 0 48 48" aria-hidden="true">
      <path d="M13 6h8.5v23.2c0 4.3 2 6.3 6.2 6.3H31V44h-4.2C17.7 44 13 39.3 13 30.6Z" fill="currentColor"/>
      <rect x="6.5" y="15" width="23" height="7.5" rx="1.2" fill="currentColor"/>
      <circle cx="36.5" cy="10" r="6.5" fill="var(--logo-dot, #F26A1B)"/>
    </svg>
    ```

    Wordmark: Geist, "Talent" weight 400 + "Labs" weight 650, tracking -0.03em.
    The mockup shows a paper plane inside a black circle as the brand: that was
    a placeholder. The top bar uses the bare mark (ink) at 36 px + wordmark.

- Favicons/app icons generated from the same SVG: `public/favicon.svg`
  (mark, ink on transparent), `public/apple-touch-icon.png` (180, ink rounded
  square, white mark, accent dot) — produce the PNG by exporting the SVG
  (any tool available locally; if none, keep only the SVG favicon and report).
  Update `resources/views/app.blade.php` links and `<title>` from
  `config('talent.brand.name')`.

### B.7 Data layer

Files in `resources/js/data/`:

- `query-client.ts` — one `QueryClient`: `staleTime` 30 s, `gcTime` 5 min,
  `retry` 1 for queries / 0 for mutations, `refetchOnWindowFocus: true`
  (a single refetch on focus is allowed; it is not polling),
  **`refetchInterval` is forbidden** (lint comment + code review).
- `api.ts` — `apiFetch<T>(url, { method, body, signal })`: same-origin
  `fetch`, `credentials: 'same-origin'`, `Accept: application/json`,
  `X-Requested-With: XMLHttpRequest`, `X-XSRF-TOKEN` from the `XSRF-TOKEN`
  cookie, JSON body; throws a typed `ApiError { status, message, errors? }`
  (Laravel 422 `errors` map preserved for forms). URLs always come from
  Wayfinder route helpers, never string literals.
- `keys.ts` — query key factory, e.g. `keys.dashboard()`,
  `keys.jobs.list(filters)`, `keys.applications.list(filters)`,
  `keys.account.status()`.
- `source.ts` — `export const useFixtures = import.meta.env.VITE_USE_FIXTURES === 'true'`.
  In `.env.example` add `VITE_USE_FIXTURES=true` (development default until
  the backend specs are done). Every hook picks its `queryFn`/`mutationFn`
  from a pair `{ real, fixture }`; **components never know which one runs**.
- `fixtures/` — typed fixture data and fixture handlers with simulated
  latency (`FIXTURE_LATENCY_MS`, default 350 ms, random ±40%) and a
  simulated failure switch (DevToolbar). Fixture state lives in an in-memory
  store so mutations change what later queries return (e.g. queuing
  applications moves jobs from "matches" to "queue").
- `realtime/` — `useUserChannel()` subscribes to the private channel
  `App.Models.User.{id}` with `@laravel/echo-react` and maps each event to
  cache updates (`setQueryData` / `invalidateQueries`). Event names and
  payloads are defined in `client-app-screens` (contracts). In fixtures mode
  the same mapping is fed by `realtime/dev-emitter.ts`, which emits the same
  event shapes (e.g. drives a fake send through the 5 stages with the
  configured step delay). Echo is configured once in `app.tsx` (already
  present: `configureEcho({ broadcaster: 'reverb' })`).
- Inertia props as `initialData`: every page hook accepts the page prop of
  the same shape (`useDashboard(props.dashboard)`) and uses it as
  `initialData` when present, so first paint has data when the backend
  provides it. In fixtures mode props are absent and the fixture resolves.
- `DevToolbar` (`patterns/dev-toolbar.tsx`), rendered only when
  `import.meta.env.DEV`: floating pill bottom-left; lets the owner switch
  **plan** (free/starter/pro → changes mode/limits in fixtures), **state**
  (normal / loading / empty / error for all queries), **locale**, **Gmail
  state** (connected / needs reconnection / disconnected), **onboarding
  complete** on/off, and buttons "Simulate send", "Simulate new jobs",
  "Simulate failure". Hidden in production builds.

### B.8 i18n

- Source files: `lang/en.json`, `lang/pt.json`, `lang/es.json` with **dotted
  keys** (`"nav.dashboard": "Dashboard"`). English is complete; PT and ES must
  be complete for every key this spec adds (natural Brazilian Portuguese and
  neutral Spanish; product tone: calm, direct).
- `HandleInertiaRequests` shares `locale`, `locales: ['en','pt','es']` and
  `translations` (the current locale's JSON merged over English, so missing
  keys fall back to English). Cache the decoded files per locale.
- Frontend: `i18n/i18n-provider.tsx` + `useT()` returning
  `t(key, params?)` with `:name` replacement and a `plural(key, count)`
  helper using keys `key.one` / `key.other`. Numbers, currencies, dates and
  relative times via `Intl` in `lib/format.ts` with the current locale
  (`pt` → `pt-BR`, `es` → `es-ES`, `en` → `en-US`). Missing key in dev:
  console warning and render the key.
- Locale resolution (middleware `SetLocale`, added to the `web` group):
  authenticated user's `users.locale` → `locale` cookie → first supported
  language in `Accept-Language` → `en`. `LanguageSwitcher` posts to
  `PUT /locale` (route `locale.update`): saves `users.locale` when logged in,
  always sets the cookie (1 year), then Inertia reloads.
- Add `users.locale` (string(5), default `'en'`) **and** `users.timezone`
  (string(64), nullable) directly in `0001_01_01_000000_create_users_table.php`.
  Add both to `User` `#[Fillable]` and phpdoc. `timezone` is filled by later
  specs; here it only exists.
- Backend strings shown to the client (validation messages, flash, mails)
  use `__()` with the same keys. Laravel validation messages: add
  `lang/pt/validation.php` and `lang/es/validation.php` only if
  `php artisan lang:publish` is available without new packages; otherwise
  keep English validation and report.

### B.9 Routes, pages and login (minimal backend)

Client routes live at the **root**, not under `/app` (Filament owns
`/app/*` until it is removed):

| Method | Path              | Name             | Page / behavior                                | Middleware                              |
| ------ | ----------------- | ---------------- | ---------------------------------------------- | --------------------------------------- |
| GET    | `/`               | `home`           | `landing` (B.11)                               | web                                     |
| GET    | `/login`          | `login`          | `auth/login`                                   | guest                                   |
| POST   | `/login`          | `login.store`    | authenticate, redirect intended or `dashboard` | guest, throttle:6,1                     |
| POST   | `/logout`         | `logout`         | logout, redirect `home`                        | auth                                    |
| PUT    | `/locale`         | `locale.update`  | B.8                                            | web                                     |
| GET    | `/dashboard`      | `dashboard`      | `dashboard` (placeholder in this spec)         | auth, client                            |
| GET    | `/dev/styleguide` | `dev.styleguide` | `dev/styleguide`                               | only registered when `app()->isLocal()` |

- `client` middleware alias → `EnsureActiveClient`: user must be active
  (`User::isActive()`); inactive users are logged out with a flash message.
  Admins may use the client app too (useful for testing).
- Login: `LoginController` (or invokable pair) with a `LoginRequest`
  (email, password, remember), `Auth::attempt`, session regeneration,
  `RateLimiter` by email+IP, errors on the `email` field (translated key
  `auth.failed`). Page uses Inertia `<Form>` (auth pages are the only place
  that uses Inertia forms instead of `useMutation`).
- Laravel's default `login` route name must now point here, so
  unauthenticated redirects land on `/login`. Filament panels keep their own
  login pages.
- Shared props (`HandleInertiaRequests::share`) — exact shape, typed in
  `resources/js/types/shared.ts`:
    ```ts
    type SharedProps = {
        app: {
            brand: { name: string; wordmark: [string, string] };
            env: 'local' | 'production' | string;
            useFixtures: boolean;
        };
        auth: {
            user: {
                id: number;
                name: string;
                email: string;
                initials: string;
                locale: Locale;
            } | null;
        };
        locale: Locale;
        locales: Locale[];
        translations: Record<string, string>;
        flash: { success: string | null; error: string | null };
    };
    type Locale = 'en' | 'pt' | 'es';
    ```
    Do not share the full `User` model (today `auth.user` shares the whole
    model; replace it with the whitelisted shape above).
- Remove `pages/welcome.tsx` (replaced by `landing`).
- Error pages: render Inertia `errors/error` for 403/404/419/500/503 in
  non-debug mode (`bootstrap/app.php` `withExceptions` → `respond`), using
  `BareLayout`, big numeral + short message + "Back to dashboard"/"Home".

### B.10 PlanGate pattern (component only; used by later specs)

A feature outside the client's plan is **never hidden and never replaced**.
The card keeps its size and place; `PlanGate` wraps it:

- Props: `locked: boolean`, `requiredPlans: PlanKey[]`, `featureKey` (i18n
  key for the benefit line), `children`.
- When locked: children rendered but inert (`inert` attribute +
  `aria-hidden`), overlay covering the whole card with the card radius,
  scrim `rgba(238,236,232,.78)` + `backdrop-filter: blur(4px)`, centered
  content: 46 px disc with lock icon, title "Available on Starter and Pro"
  (plan names from `t('plans.<key>.name')`, joined with the locale's list
  format), one benefit line, primary ink pill "Upgrade to <first plan>",
  ghost pill "See plans" (both link to the Plans page route, which exists
  from `client-app-screens`; here link to `#` if the route does not exist yet).
- Styleguide shows it locked and unlocked.

### B.11 Landing (minimal, reserved)

`/` renders `pages/landing.tsx` in `BareLayout` on the `shell` background:
centered `Logo` (full, large), the text "In development · Closed beta"
(`landing.status`), one muted line (`landing.subtitle`: "We are onboarding
a small group of testers."), and one primary ink pill button "Go to login"
(`landing.login`). If the user is logged in, the button reads
"Open dashboard" and links to `dashboard`. Nothing else. The real landing
page is a later project.

### B.12 Styleguide page (dev only)

`/dev/styleguide` (local env only): every `ui/` and `patterns/` component in
every variant and state (loading, empty, error, disabled, selected, locked
PlanGate, dark variants), the token swatches, the type scale, the logo in
all tones, and a responsive preview hint. This is the owner's visual review
surface for the kit; each phase that adds components adds them here.

### B.13 Visual gate for this spec

The `dashboard` page in this spec is a **static composition of the kit using
the numbers of the mockup** (hardcoded demo data is allowed only inside
`pages/dashboard.tsx` in this spec; `client-app-screens` replaces it with
fixtures). It must match `reference/dashboard-mockup.png` at 1600 px:
layout, radii, type scale, colors, spacing, icons. The owner compares side
by side in his browser and approves before `client-app-screens` starts.
If a browser automation tool is available in the session, take screenshots
at 1600, 1024 and 390 px and show them; otherwise ask the owner to check.

## Acceptance criteria

- **AC01** `resources/css/app.css` defines every token in B.3 via `@theme`;
  `grep` finds no raw hex values in `resources/js/components`, `features`,
  `pages` or `layouts` (logo dot fallback in `logo-mark.tsx` excepted).
- **AC02** Geist is self-hosted from `public/fonts/geist/` with its license;
  no external font request is made.
- **AC03** Every primitive and pattern listed in B.4 exists, is typed, uses
  only tokens, and appears on `/dev/styleguide` in all its states.
- **AC04** `/dev/styleguide` returns 404 outside the local environment.
- **AC05** `Logo` renders the exact SVG of B.6 in three tones; changing
  `BRAND_NAME` / `BRAND_WORDMARK_*` changes every place the brand appears
  (top bar, landing, page `<title>`, error pages) with no code change.
- **AC06** `/` shows only: logo, "In development · Closed beta", subtitle,
  one button ("Go to login" for guests, "Open dashboard" when logged in).
- **AC07** Login works with the seeded client user; wrong credentials show
  the translated error; 6 failed attempts per minute are throttled; logout
  returns to `/`. Unauthenticated `/dashboard` redirects to `/login`.
- **AC08** `SetLocale` resolves user → cookie → Accept-Language → `en`;
  switching language in the top bar persists (`users.locale` when logged in,
  cookie always) and re-renders all strings; `lang/en.json`, `pt.json`,
  `es.json` contain the same key set.
- **AC09** Shared props match the `SharedProps` type exactly; the full user
  model is no longer shared.
- **AC10** `@tanstack/react-query` provider, `apiFetch` with CSRF, key
  factory, fixture source switch and `useUserChannel` exist; no
  `refetchInterval`/`setInterval`-based fetching anywhere (`grep`).
- **AC11** `DevToolbar` appears only in `import.meta.env.DEV` and switches
  plan, state, locale, Gmail state and onboarding; it is absent from
  `yarn build` output.
- **AC12** `PlanGate` behaves as B.10 (inert content, overlay, CTAs).
- **AC13** Responsive rules of B.5 hold at 360, 390, 768, 1024, 1280, 1440
  and 1600 px with no horizontal scroll.
- **AC14** The demo `dashboard` page matches the mockup at 1600 px (owner
  visual approval, B.13).
- **AC15** Error pages render through Inertia with the brand for 403/404/419/500/503 when `APP_DEBUG=false`.
- **AC16** `.env.example` gains `BRAND_NAME`, `BRAND_WORDMARK_REGULAR`,
  `BRAND_WORDMARK_BOLD`, `VITE_USE_FIXTURES` (appended; existing values untouched).
- **AC17** Filament `/admin` and `/app` still work unchanged.

## Verification

`composer lint:check`, `composer types:check`, `yarn check`,
`yarn types:check`, `yarn build` (bundle changed), existing tests
(`php artisan test --compact`). Manual: log in as the seeded client,
open `/dashboard`, `/dev/styleguide`, switch language, resize.

## Out of scope

All real screens and their data (next spec), registration and invites,
password reset (`client-core-wiring`), dark mode (tokens are structured so
it can be added later), the real landing page, notifications content,
Inertia SSR, removing Filament `/app`, tests (not requested).

## Owner decisions

None open. Dependencies in B.2 are pre-approved by the owner for this spec;
the phase message that installs them should say so explicitly.

## Roadmap (execution order of all specs)

1. `client-app-foundation` (this) → 2. `client-app-screens` →
2. `client-core-wiring` → 4. `preferences-clarity` →
3. `application-languages` → 6. `plans-and-sending-modes` →
4. `collection-scheduler` → 8. `regional-pricing-and-billing` →
5. `inventory-and-sources` → 10. `production-readiness`.
   Specs 1–2 are frontend only (navigable prototype on fixtures). Specs 3–6 wire
   the backend screen by screen. 7 can run any time after 3.
