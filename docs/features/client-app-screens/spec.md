# client-app-screens — every client screen as a navigable prototype on typed fixtures

> **Order:** 2 of 10. **Depends on:** `client-app-foundation` (design system,
> shell, data layer, i18n, login) DONE and visually approved.
> **Kind:** frontend only. No new tables, no new endpoints. Every screen is
> reachable, every button does something visible (on fixtures), and the
> **data contracts** here are the binding interface every backend spec
> implements later.
>
> **How to run:** `/plan-spec docs/features/client-app-screens/spec.md`,
> then `/execute-phases`. No new dependencies.

## Part 0 — Context and decisions (owner, final)

- The owner wants to **see and click through the whole product before any
  backend work**. This spec delivers a complete navigable prototype: all
  screens, links, modals, mutations with simulated latency, simulated
  realtime, and all states (loading, empty, error, locked, limit reached).
- Look is locked to `docs/features/client-app-foundation/reference/dashboard-mockup.*`
  and the tokens/components of `client-app-foundation`. New screens are
  composed from the existing kit; new primitives only when nothing fits
  (and then added to `/dev/styleguide`).
- **Contracts first.** Section B.2 defines the TypeScript types for every
  piece of data. Fixtures produce exactly these shapes; later specs make the
  backend produce exactly these shapes. Changing a contract later means
  changing this spec's section via a new spec addition, never silently.
- **Plans and modes** (config values arrive from the backend later; here from
  fixtures): Free = 25/day, mode `auto` (sends automatically, no choosing,
  no review). Starter = 50/day, mode `select` (client chooses jobs). Pro =
  150/day, mode `review` (chooses + edits each email before it is queued).
- **Out-of-plan features are never hidden**: they stay on screen under
  `PlanGate` (foundation B.10) with an upgrade call to action.
- **The client never sees data that lets them apply outside the product:**
  no job URLs, no recipient email addresses, no company websites/domains.
  Where the email template contains `{{ job_url }}`, previews show a
  "job link" chip instead of the URL.
- **Honest live sending:** the stages shown are the real pipeline steps
  (`validating_recipient → adapting_template → attaching_cv → sending →
  sent`), narrated with real sub-steps. Fixtures simulate them with the same
  timing model the backend will use.
- Hard rules from `CLAUDE.md` apply. All strings through `t()` with EN, PT
  and ES complete. Never polling.

## Part B — Product spec

### B.1 Files to read first

`client-app-foundation/spec.md` (whole), the reference mockup, everything
under `resources/js/` produced by foundation (kit, `data/`, `i18n/`,
layouts, `DevToolbar`), `lang/*.json`, `routes/web.php`,
`app/Outreach/Support/ApplicationTemplateRenderer.php` (variables list and
defaults), `app/Ai/Agents/ExtractJobPostingProfile.php` (seniority values).

### B.2 Data contracts — `resources/js/types/contracts.ts`

Create this file exactly (additions allowed, renames not). Dates are ISO
8601 strings with timezone. Money is integer minor units.

```ts
export type ISODateTime = string;
export type ISODate = string; // YYYY-MM-DD in the user's timezone
export type Locale = 'en' | 'pt' | 'es';
export type JobLanguage = 'en' | 'pt' | 'es';
export type PlanKey = 'free' | 'starter' | 'pro';
export type SendMode = 'auto' | 'select' | 'review';
export type RegionKey = 'br' | 'eu' | 'row';
export type Seniority = 'intern' | 'junior' | 'mid' | 'senior' | 'lead' | 'unknown';
export type RemoteMode = 'remote_only' | 'remote_or_locations' | 'locations_only';
export type ApplicationStatus = 'queued' | 'sending' | 'sent' | 'failed' | 'ambiguous';
export type SendStage = 'validating_recipient' | 'adapting_template' | 'attaching_cv' | 'sending' | 'sent' | 'failed';
export type SubStep =
  | 'checking_company' | 'confirming_recipient' | 'checking_gmail'   // validating_recipient
  | 'filling_variables' | 'building_html'                            // adapting_template
  | 'opening_cv' | 'checking_pdf' | 'attaching_file'                 // attaching_cv
  | 'connecting_gmail' | 'delivering';                               // sending
export type TemplateVariable = 'company' | 'job_title' | 'job_location' | 'job_url' | 'client_name' | 'cover_letter';

export type CompanyRef = { id: number; name: string; initials: string };

export type Quota = { usedToday: number; limit: number; remaining: number; resetsAt: ISODateTime };

export type AccountStatus = {
  plan: { key: PlanKey; name: string; mode: SendMode; dailyLimit: number };
  quota: Quota;
  gmail: { state: 'connected' | 'reauthorization_required' | 'disconnected'; accountEmail: string | null };
  sending: { paused: boolean; autoPausedReason: 'reauthorization_required' | 'repeated_failures' | null };
  onboarding: { complete: boolean; steps: { key: 'basics' | 'gmail' | 'profile' | 'preferences'; done: boolean }[] };
  profiles: { activeLanguages: JobLanguage[] }; // active AND complete profiles
  region: RegionKey;
  country: string | null;   // ISO 3166-1 alpha-2
  timezone: string | null;  // IANA
  unreadNotifications: number;
};

export type JobCard = {
  id: number;
  company: CompanyRef;
  title: string;
  location: string | null;
  isRemote: boolean | null;
  language: JobLanguage;
  seniority: Seniority;
  stack: string[];            // max 6 shown
  summary: string | null;     // one sentence, client-safe
  firstSeenAt: ISODateTime;
  collectedToday: boolean;
};
export type JobDetail = JobCard & {
  locations: string[];
  employmentType: string | null;
  department: string | null;
  publishedAt: ISODateTime | null;
  sourceLabel: string;        // e.g. "RemoteOK" — text only, never a link
};
export type JobFilters = {
  q?: string;
  language?: JobLanguage | 'all';
  seniority?: Seniority[];
  remote?: 'any' | 'remote' | 'not_remote';
  today?: boolean;
  stack?: string[];
  cursor?: string | null;
};
export type Paginated<T> = { data: T[]; meta: { nextCursor: string | null; total: number } };
export type JobsPage = Paginated<JobCard> & {
  summary: { total: number; collectedToday: number; lockedByLanguage: { language: JobLanguage; count: number }[] };
};

export type ApplicationItem = {
  id: number;
  company: CompanyRef;
  title: string | null;
  language: JobLanguage;
  origin: 'auto' | 'manual';
  status: ApplicationStatus;
  stage: SendStage | null;
  subStep: SubStep | null;
  lastError: string | null;    // client-safe sentence, already translated by the backend
  queuedAt: ISODateTime;
  scheduledFor: ISODateTime | null;
  sentAt: ISODateTime | null;
};
export type ApplicationDetail = ApplicationItem & {
  subject: string;
  body: string;                // client-safe (job links replaced by the chip token)
  cvFileName: string | null;
  timeline: { stage: SendStage; at: ISODateTime }[];
};
export type ApplicationFilters = {
  status?: 'all' | 'in_progress' | 'sent' | 'attention'; // in_progress = queued+sending, attention = failed+ambiguous
  language?: JobLanguage | 'all';
  q?: string;
  cursor?: string | null;
};

export type DashboardPeriod = 'today' | 'week' | 'month';
export type DashboardData = {
  period: DashboardPeriod;
  kpis: {
    collected: { value: number; previous: number };
    sent: { value: number; previous: number };
    totalSent: number;
    firstSentAt: ISODateTime | null;
  };
  hero: {
    sentToday: number;
    failedToday: number;
    queued: number;
    nextSendAt: ISODateTime | null;
    lastDays: { date: ISODate; count: number }[]; // the 5 days before today, oldest first
  };
  matches: { total: number; newToday: number; items: JobCard[] }; // items: up to 4, newest first
  activity: ApplicationItem[];                                     // up to 5, newest first
};
export type ChartData = {
  range: '14d' | '30d';
  days: { date: ISODate; count: number }[];   // oldest first, includes today
  averagePerActiveDay: number;                // days with count > 0, one decimal
  limit: number;
};
export type LiveSending = {
  state: 'idle' | 'sending' | 'waiting' | 'paused' | 'limit_reached' | 'outside_window';
  current: ApplicationItem | null;             // the application in status 'sending'
  progress: { index: number; total: number } | null; // index = sent today + 1, total = daily limit
  queue: ApplicationItem[];                    // next 3 queued, by scheduledFor
  queuedCount: number;
  nextSendAt: ISODateTime | null;
  waitStartedAt: ISODateTime | null;           // for the countdown bar fill
  estimatedFinishAt: ISODateTime | null;       // when the current queue should be done
  spacing: { minSeconds: number; maxSeconds: number };
  window: { start: string; end: string; weekdaysOnly: boolean; timezone: string } | null;
};

export type ReviewDraft = {
  job: JobCard;
  language: JobLanguage;
  subject: string;
  body: string;            // rendered, but `{{ job_url }}` kept as a token shown as a chip
  cvFileName: string;
  recipientLabel: string;  // e.g. "Klarwerk careers team" — never the address
};
export type QueueResult = {
  queued: { jobId: number; applicationId: number; scheduledFor: ISODateTime }[];
  rejected: { jobId: number; reason: string }[];  // reason: client-safe, translated
  quota: Quota;
};

export type Preferences = {
  titles: string[];
  seniorities: Seniority[];
  stack: string[];
  locations: string[];
  remoteMode: RemoteMode;
  excludeWords: string[];
};
export type PreferencesPreview = { matchCount: number; byLanguage: Record<JobLanguage, number> };

export type ApplicationProfile = {
  language: JobLanguage;
  active: boolean;
  cv: { fileName: string; sizeBytes: number; uploadedAt: ISODateTime } | null;
  emailSubject: string;
  emailBody: string;
  coverLetter: string;
  complete: boolean;
  missing: ('cv' | 'subject' | 'body')[];
};
export type ProfilesData = {
  profiles: ApplicationProfile[];                  // only created languages
  variables: TemplateVariable[];
  unlockCounts: Record<JobLanguage, number>;       // pool jobs per language, ignoring the language rule
};
export type TemplatePreview = { subject: string; body: string; sampleJob: { company: string; title: string } };

export type PlanOffer = {
  key: PlanKey; name: string; mode: SendMode; dailyLimit: number;
  price: number; currency: string; interval: 'month';
  highlighted: boolean;
};
export type PlansData = {
  region: RegionKey;
  regions: { key: RegionKey; currency: string }[];
  plans: PlanOffer[];
  current: PlanKey;
  billingAvailable: boolean;   // false until regional-pricing-and-billing ships
};

export type NotificationType =
  | 'gmail_reauthorization_required' | 'application_failed' | 'daily_limit_reached'
  | 'jobs_collected' | 'sending_auto_paused';
export type NotificationItem = {
  id: string; type: NotificationType;
  data: Record<string, string | number | null>;
  readAt: ISODateTime | null; createdAt: ISODateTime;
};

export type Account = { name: string; email: string; locale: Locale; timezone: string | null; country: string | null; region: RegionKey };
export type OnboardingBasics = { country: string; locale: Locale; timezone: string };

export type InviteCheck = { valid: true; email: string | null } | { valid: false };
```

Realtime contracts (`resources/js/types/realtime.ts`):

```ts
// Private channel `App.Models.User.{id}`; Echo listens with a leading dot, e.g. '.application.progressed'
export type UserChannelEvents = {
  'application.progressed': { application: ApplicationItem };
  'sending.updated': { sending: LiveSending };
  'account.updated': { status: AccountStatus };
  'notification.created': { notification: NotificationItem };
};
// Public channel `jobs`, event '.jobs.collected'
export type JobsChannelEvents = { 'jobs.collected': { collectionRunId: number; newJobs: number } };
```

### B.3 Endpoint map (implemented later, mirrored by fixtures now)

Hooks live in `resources/js/data/hooks/`. Each hook has a `real` function
(Wayfinder route helper → `apiFetch`) and a `fixture` function. In this spec
the **routes do not exist yet**, so `real` functions are written against
the URLs below as typed placeholders in `data/endpoints.ts` (a single
object; later specs replace entries with Wayfinder helpers). All paths are
under the `web` + `auth` + `client` middleware and prefixed `/internal`.

| Hook | Method + path | Returns |
|---|---|---|
| `useAccountStatus` | GET `/internal/account/status` | `AccountStatus` |
| `useDashboard(period)` | GET `/internal/dashboard?period=` | `DashboardData` |
| `useChart(range)` | GET `/internal/dashboard/chart?range=` | `ChartData` |
| `useLiveSending` | GET `/internal/sending` | `LiveSending` |
| `usePauseSending` / `useResumeSending` | POST `/internal/sending/pause` / `resume` | `LiveSending` |
| `useJobs(filters)` (infinite) | GET `/internal/jobs?…` | `JobsPage` |
| `useJob(id)` | GET `/internal/jobs/{id}` | `JobDetail` |
| `useQueueApplications` | POST `/internal/applications` `{ jobIds }` | `QueueResult` |
| `useReviewDrafts` | POST `/internal/applications/drafts` `{ jobIds }` | `ReviewDraft[]` |
| `useQueueReviewed` | POST `/internal/applications/reviewed` `{ jobId, subject, body }` | `QueueResult` |
| `useApplications(filters)` (infinite) | GET `/internal/applications?…` | `Paginated<ApplicationItem>` |
| `useApplication(id)` | GET `/internal/applications/{id}` | `ApplicationDetail` |
| `usePreferences` / `useSavePreferences` | GET / PUT `/internal/preferences` | `Preferences` |
| `usePreferencesPreview(draft)` | POST `/internal/preferences/preview` | `PreferencesPreview` |
| `useProfiles` | GET `/internal/profiles` | `ProfilesData` |
| `useCreateProfile` | POST `/internal/profiles` `{ language }` | `ApplicationProfile` |
| `useSaveProfile` | PUT `/internal/profiles/{language}` | `ApplicationProfile` |
| `useDeleteProfile` | DELETE `/internal/profiles/{language}` | `{}` |
| `useUploadCv` / `useDeleteCv` | POST (multipart `cv`) / DELETE `/internal/profiles/{language}/cv` | `ApplicationProfile` |
| `useTemplatePreview` | POST `/internal/profiles/{language}/preview` `{ subject, body, coverLetter }` | `TemplatePreview` |
| `usePlans(region?)` | GET `/internal/plans?region=` | `PlansData` |
| `useNotifications` / `useMarkAllRead` | GET / POST `/internal/notifications` / `read-all` | `NotificationItem[]` |
| `useAccount` / `useSaveAccount` | GET / PUT `/internal/account` | `Account` |
| `useChangePassword` | PUT `/internal/account/password` | `{}` |
| `useDeleteAccount` | DELETE `/internal/account` `{ password }` | `{}` |
| `useSaveOnboardingBasics` | PUT `/internal/onboarding/basics` | `AccountStatus` |

Gmail connect/reconnect/disconnect use the **existing** OAuth routes
(`integrations.oauth.connect|reconnect|disconnect`, plugin `gmail`); in
fixtures they flip the DevToolbar Gmail state instead of navigating.

Account data export (`GET /internal/account/export`, JSON download) is a
plain link.

### B.4 Fixtures

`resources/js/data/fixtures/`:

- **Companies:** ~30 fictional companies (never real company names), e.g.
  Klarwerk (Berlin), Lumen Health (Lisbon), Estrela Pay (São Paulo), Nuvia
  (Madrid), Cobalt Freight (Remote EU), Pampa Logística (Porto Alegre),
  Brisa Seguros (Recife), Norte Analytics (Porto), Arcadia Games (Barcelona),
  Fjord Mobility (Oslo), Tessera Cloud (Dublin), Maré Energia (Florianópolis),
  Solvio (Amsterdam), Quanta Retail (Remote), Oriol Studio (Valencia), Duna
  Fintech (Belo Horizonte), Kestrel Security (London), Alto Commerce (Remote
  LATAM), Ribeira Tech (Coimbra), Faro Data (Remote), Helix Bio (Munich),
  Tinta Media (Buenos Aires), Vértice (Curitiba), Moraga Systems (Bilbao),
  Kiln (Remote US).
- **Jobs:** 60 jobs, ~55% EN / 30% PT / 15% ES, realistic titles per language
  ("Senior Backend Engineer, PHP / Laravel", "Desenvolvedor Full-stack Pleno",
  "Ingeniero Backend (Go)"), seniority mix, stack arrays, one-sentence
  summaries in the job's language, 20 of them collected today.
- **Applications:** 420 total sent over 30 days with realistic per-day counts
  (weekend dips), today 18 sent + 12 queued + 1 failed, a few `ambiguous`,
  failure reasons as client-safe sentences.
- **Profiles:** EN complete, PT complete, ES not created (so ES jobs are
  "locked by language" with a count).
- **Plan:** from DevToolbar (default Starter). Quota and modes follow it.
- **In-memory store** so mutations are visible: queueing moves jobs out of
  matches into the queue with staggered `scheduledFor` (random 45–120 s);
  the `dev-emitter` sends the head of the queue through the stages (each
  sub-step `stepDelayMs` = 1200 ms), emits `application.progressed`,
  `sending.updated`, `account.updated`, then waits for the next slot. Pause
  stops it; resume continues. "Simulate failure" makes the next send fail at
  `attaching_cv` with "Your CV file could not be read." Auto mode (Free) runs
  the emitter continuously from the matching jobs.
- Latency and error switch from foundation B.7 apply to every handler.

### B.5 Navigation and routes

Top navigation items (in order): **Dashboard, Jobs, Applications, Profiles,
Preferences, Plans**. User menu: Account, Language, Log out. Bell opens the
notifications popover. Search (⌘K / Ctrl+K) opens the command palette.

New Laravel routes (`routes/web.php`, `auth` + `client`, all
`Route::inertia(...)` with no props in this spec):

| Path | Name | Page |
|---|---|---|
| `/dashboard` | `dashboard` | `dashboard` |
| `/jobs` | `jobs` | `jobs` |
| `/applications` | `applications` | `applications` |
| `/profiles` | `profiles` | `profiles` |
| `/preferences` | `preferences` | `preferences` |
| `/plans` | `plans` | `plans` |
| `/account` | `account` | `account` |
| `/onboarding` | `onboarding` | `onboarding` |

Guest routes (`guest` middleware, pages only in this spec, submit handled on
fixtures): `/register` (`register`, page `auth/register`, reads `?invite=`),
`/register/closed` (`register.closed`, page `auth/closed`),
`/forgot-password` (`password.request`, page `auth/forgot-password`),
`/reset-password/{token}` (`password.reset`, page `auth/reset-password`).
In fixtures mode the register page treats any `invite` value starting with
`ok` as valid and anything else as invalid (redirect to closed).

### B.6 Screens

Every screen: `PageHeader` (eyebrow, display title, summary, actions), all
states (loading skeleton that mirrors the final layout, empty, error with
retry), responsive per foundation B.5, all copy translated.

**S1 Dashboard** (`pages/dashboard.tsx`) — the mockup, now data-driven:
- Header: eyebrow `<weekday, date> · <mode label>`; title "Good
  morning/afternoon/evening, <first name>" from the user's local time;
  summary "**18** applications sent today, **61** new jobs match your
  preferences."; actions: `Segmented` Today/Week/Month (drives `period`),
  primary "Browse jobs" → Jobs.
- Row 1: `HeroCard` (span 5) — label "Today's sending", numeral
  `hero.sentToday` + suffix `/ <quota.limit>`, mini-bars from
  `hero.lastDays` + today (solid), caption "applications sent today ·
  <remaining> left on your <plan> plan", stats strip Queued /
  Not delivered (`failedToday`) / Next send in (countdown from
  `nextSendAt`, "—" when null). KPI card (span 7) titled by period
  ("Today"/"This week"/"This month") with 4 `StatTile`s: Jobs collected
  (period, delta vs previous), Sent (period, delta), Total sent ("since
  <date>"), Daily limit (`usedToday / limit` + `TickMeter` of 20 ticks +
  "<remaining> left today").
- Row 2: **Live sending** `DarkCard` (span 7) driven by `LiveSending`:
  current application panel (company tile, title, company · location,
  language tag, "19 of 50"), `LiveStepper` (done/active/upcoming, sub-step
  text under the active step from `sending.sub.<subStep>`), `CountdownBar`
  "Next application starts in 0:42" + "Spacing 45 to 120 s", "Up next ·
  <queuedCount> queued" + estimated finish, 3 `QueueRow`s. Header: "Live"
  pill (pulsing when `state` is sending/waiting), Pause/Resume ghost button
  (`usePauseSending`/`useResumeSending`, optimistic). Other states:
  `idle` → empty state "Nothing in the queue" + CTA (select/review: "Choose
  jobs"; auto: "Sending starts automatically when new jobs match");
  `paused` → "Sending is paused" + Resume; `limit_reached` → "Daily limit
  reached, sending resumes tomorrow at <time>" + upgrade link;
  `outside_window` → "Sending resumes at <window start>". Failed send: the
  stepper marks the failed step red with the reason for 6 s, then the
  panel moves on. Chart card (span 5): "Sends per day", `Segmented` 14d/30d,
  headline `averagePerActiveDay` "avg per active day", `BarChart` with today
  highlighted and the daily limit as a dashed line, legend.
- Row 3: **New matches** `DataCard` (span 7): subtitle "Collected today ·
  <total> match your preferences · all in your active languages"; up to 4
  `JobRow`s; mode behavior:
  - `select`: checkboxes, footer "<n> selected · <remaining> sends left
    today", Clear, primary "Send <n> applications" → confirm modal
    (B.6 S3 confirm) → `useQueueApplications`.
  - `review`: same selection, primary "Review <n> applications" → Review
    modal (S3).
  - `auto`: rows shown without checkboxes, card wrapped in
    `PlanGate(locked, requiredPlans ['starter','pro'], feature 'choose jobs')`.
  Header arrow → Jobs. **Recent activity** (span 5): `ActivityRow`s
  (sending spinner / sent / not delivered with reason / needs review for
  `ambiguous`), "Live" pill, footer "View all applications".
- Onboarding incomplete (`AccountStatus.onboarding.complete = false`): Row 1
  is replaced by a full-width **Setup** hero card (accent) with the 4 steps
  as a checklist and "Continue setup" → Onboarding; Live sending and New
  matches are covered by a `LockOverlay` (the generic base of `PlanGate`,
  extract it now) with "Finish setup to start sending".
- Gmail `reauthorization_required`: a full-width warning banner above Row 1
  "Your Gmail connection expired. Sending is paused until you reconnect." +
  "Reconnect Gmail".

**S2 Jobs** (`pages/jobs.tsx`):
- Header: title "Jobs", summary "<total> jobs match your preferences ·
  <collectedToday> collected today", actions: "Edit preferences" (secondary).
- `FilterBar` (one row, wraps on mobile): search input, "Collected today"
  toggle, Language select (only active languages + "All"), Seniority
  multi-select, Remote select, Stack tags. Filters are reflected in the URL
  query string (shareable, back/forward works).
- "Locked by language" notice when `summary.lockedByLanguage` has counts:
  "<count> Spanish jobs are hidden because you have no Spanish application
  profile." + "Create Spanish profile" → Profiles.
- List of `JobRow`s, infinite list with a "Load more" button (no automatic
  scroll loading), click on a row body opens the **Job detail** `Sheet`
  (right drawer desktop, full-screen mobile): company, title, location and
  remote, seniority, language, stack, summary, employment type,
  department, published, "Source: <sourceLabel>" as plain text. **No link
  out.** Actions inside: select (select/review), or the plan gate (auto).
- Selection (select/review): checkbox per row, "Select all on page",
  `StickyActionBar` at the bottom: "<n> selected · <remaining> sends left
  today" + Clear + "Send <n> applications" (select) / "Review <n>
  applications" (review). Selecting more than `remaining` shows an inline
  error in the bar and disables the button. One job per company: selecting
  a second job of an already-selected company replaces the first and shows a
  toast "Only one application per company".
- Auto mode: checkboxes hidden, the list is shown read-only inside a
  `PlanGate` that covers **only the selection features** (the sticky bar
  area shows the gate CTA "Choose your own jobs on Starter and Pro"); a
  status banner "Sending automatically · <remaining> left today · next at
  <time>" with Pause/Resume.

**S3 Send flows**
- **Confirm modal (select):** "Send <n> applications?" list of companies,
  "They will go out one by one from <gmail>, about every 45–120 seconds.
  Estimated finish <time>." Buttons Cancel / Send. On success: toast
  "<n> applications queued", selection cleared, Dashboard live panel and
  queue update. Partial rejections listed in the toast detail.
- **Review modal (Pro)** (`features/review/review-modal.tsx`, large `Modal`,
  full-screen on mobile): loads `useReviewDrafts(jobIds)`; header "Review
  <i> of <n>" + progress dots; left: job summary card (company, title,
  language tag, seniority, stack); right: editable Subject (`Input`) and
  Body (`Textarea` with the `{{ job_url }}` token rendered as a
  non-editable chip "job link"), CV chip "<cvFileName>", recipient line
  "To: <recipientLabel>", language profile tag. Footer: "Skip", "Previous",
  "Approve and queue" (→ `useQueueReviewed` then next). Validation: subject
  1–200 chars, body 1–5000 chars. When all done: summary "Queued <x>,
  skipped <y>" and close.

**S4 Applications** (`pages/applications.tsx`): tabs All / In progress /
Sent / Needs attention (with counts), language filter, search. Desktop:
table (company, role, language tag, status badge with icon + label, stage
for in-progress rows, queued/sent time). Mobile: stacked `ActivityRow`
cards. Row click → **Application detail** `Sheet`: status, timeline of
stages with times, subject and body snapshot (read-only, job link chip), CV
file name, failure reason and what it means ("won't retry" for
`ambiguous`: "We could not confirm delivery, so we will not send it again
to avoid a duplicate"). Rows update live.

**S5 Profiles** (`pages/profiles.tsx`) — application content per language:
- Header: title "Application profiles", summary "A job is only sent with a
  profile in its language. You have <n> active languages." Action: "Add
  language" menu (EN/PT/ES not yet created).
- Language `Tabs` with a status dot (complete / incomplete / inactive).
- Per tab, two columns (stacked on mobile):
  - Left: **CV** `FileDrop` (PDF only, max 5 MB, shows name/size/date,
    replace, remove with confirm); **Email** Subject input + Body textarea
    with a variable bar (chips inserting `{{ company }}`, `{{ job_title }}`,
    `{{ job_location }}`, `{{ job_url }}`, `{{ client_name }}`,
    `{{ cover_letter }}` at the cursor); **Cover letter** textarea (longer
    text, inserted into the email where `{{ cover_letter }}` appears;
    help text explains this); Active `Switch`; Save (mutation, dirty
    state, unsaved-changes guard on tab switch and navigation); Delete
    profile (danger, confirm).
  - Right (sticky on desktop): **Live preview** card rendering subject and
    body with a sample job from `useTemplatePreview` (debounced 500 ms),
    job link shown as chip; **Jobs unlocked** stat "<count> jobs in
    <language> match your preferences"; "Missing: CV, subject" warning list
    when incomplete.
- Empty state (no profiles): "Create your first application profile" with
  the three language options and the counts each would unlock.

**S6 Preferences** (`pages/preferences.tsx`):
- Rule banner (always visible): "Inside a field, any value can match. Between
  fields, all must match."
- Sections (each a `DataCard`): **Roles** (titles `TagsInput`, help "e.g.
  Backend, Frontend, React Developer"), **Seniority** (toggle chips for
  intern/junior/mid/senior/lead; note "Jobs where the level is unclear are
  always included"), **Stack** (`TagsInput` with suggestions from a fixed
  list of common technologies), **Location and remote** (radio cards:
  Remote only / Remote or these locations / Only these locations; the
  locations `TagsInput` is shown for the last two and required for
  "Only these locations"), **Exclude** ("Words to exclude", matched against
  job titles and stack, e.g. "Java, WordPress, Intern").
- Right column (sticky desktop, bottom sheet summary on mobile): **Live
  summary sentence** built on the client with i18n, e.g. "Frontend or
  backend roles, senior or lead level, with React or Vue, remote or in
  Dublin, excluding WordPress."; **Match counter** from
  `usePreferencesPreview(draft)` (debounced 400 ms, previous value kept
  while loading), with per-language breakdown; Save button (disabled until
  dirty; success toast).

**S7 Plans** (`pages/plans.tsx`): three plan cards side by side (stacked on
mobile): name, price per month in the region currency (`Intl` currency
format), daily limit, mode explanation (Auto: "We choose and send for you
within your preferences"; Select: "You choose which jobs to apply to";
Review: "You choose and edit every email before it goes"), feature list
(checks), CTA: "Current plan" (disabled) / "Upgrade" / "Switch". Highlighted
card uses the accent treatment. Region line: "Prices for <region> ·
Change" → region `Menu`. With `billingAvailable = false` the CTA opens a
modal "Plans are assigned by us during the closed beta. Contact us to
change your plan." (mailto from config later; fixtures: just the modal).

**S8 Account** (`pages/account.tsx`): cards: Profile (name editable, email
read-only), Language & region (interface language, country select with
`Intl.DisplayNames`, timezone select defaulting to the browser's), Gmail
(status, connected address, explanation "Applications leave from your own
Gmail address", Connect/Reconnect/Disconnect with confirm), Password (current,
new, confirm), Data (download my data), Danger zone (delete account with
password confirm).

**S9 Onboarding** (`pages/onboarding.tsx`, app shell without nav items
except the logo and user menu): `Stepper` 1–4 with progress, one card per
step, Back/Continue:
1. **Basics:** country, interface language, timezone (prefilled from the
   browser).
2. **Connect Gmail:** explanation + "Connect Gmail" (existing OAuth route;
   returns to `/onboarding`), shows connected address when done.
3. **First application profile:** choose language, upload CV, subject/body
   prefilled with the default template of that language, cover letter
   optional, live preview.
4. **Preferences:** compact version of S6 (roles, seniority, remote mode,
   locations) with the live counter.
Finish → Dashboard with a success toast. Steps already done show as done
and can be revisited.

**S10 Auth pages** (`GuestLayout`, centered card, logo on top):
- **Register** (`?invite=`): name, email (prefilled and read-only if the
  invite has an email), password + confirm, hidden timezone from the
  browser; "Create account". Invalid/used invite → redirect to Closed.
- **Closed:** "Sign-ups are closed for now. We are in a closed beta with a
  small group of testers." + "Go to login".
- **Forgot password** and **Reset password** standard flows with success
  states.
- **Login** already exists (foundation); add "Forgot password?" link.

**S11 Notifications popover** (bell): list (icon disc by type, one-line
text from `notifications.<type>` with data params, relative time), unread
dot, "Mark all as read", empty state. New notifications arrive via realtime
and show a toast for `gmail_reauthorization_required`,
`sending_auto_paused` and `daily_limit_reached`.

**S12 Command palette** (⌘K/Ctrl+K and the search pill): pages
(navigation), jobs search (uses `useJobs({ q })`, top 5), actions
("Pause sending", "Add language", "Edit preferences"). Keyboard navigable.

### B.7 Realtime wiring (fixtures now, real later)

`useUserChannel` (foundation) maps events to the cache:

| Event | Cache effect |
|---|---|
| `application.progressed` | Upsert the item in every cached `applications` list (by id), in `dashboard.activity` (prepend when new), and in `sending.current`; when status becomes `sent`/`failed`/`ambiguous`, invalidate `dashboard`, `chart`, `account.status`. |
| `sending.updated` | `setQueryData(keys.sending(), payload.sending)` |
| `account.updated` | `setQueryData(keys.account.status(), payload.status)` |
| `notification.created` | Prepend to notifications, bump unread count, toast for the 3 types above |
| `jobs` → `jobs.collected` | Debounced (2 s) invalidation of `jobs.*`, `dashboard`, `preferences.preview` |

Countdowns (`nextSendAt`) tick on the client with one `requestAnimationFrame`/1 s
timer that only updates the display; it never fetches.

### B.8 i18n

Every string of this spec in `lang/en.json`, `pt.json`, `es.json` (same key
set). Includes: nav, page titles, all labels, states, stage and sub-step
names (`sending.stage.*`, `sending.sub.*`), notification texts, plan mode
descriptions, seniority/remote labels, summary sentence fragments
(`preferences.summary.*` with list formatting via `Intl.ListFormat`, "or"
type for values inside a field), error messages. Portuguese is Brazilian,
Spanish is neutral.

## Acceptance criteria

- **AC01** `contracts.ts` and `realtime.ts` exist exactly as B.2; fixtures
  and hooks are typed against them; `yarn types:check` passes.
- **AC02** All routes in B.5 exist and render; top nav, user menu, bell,
  command palette and every in-page link reach their target; the browser
  back button works everywhere.
- **AC03** With `VITE_USE_FIXTURES=true`, every screen S1–S12 shows realistic
  fixture data and has working loading, empty and error states (switchable
  from DevToolbar).
- **AC04** Switching the plan in DevToolbar changes the mode everywhere:
  select → checkboxes + "Send n"; review → "Review n" + review modal;
  auto → no checkboxes, `PlanGate` over the selection features, automatic
  sends running in the live panel.
- **AC05** Queuing jobs (select or review) moves them out of matches, adds
  them to the queue with staggered times, and the live panel runs them
  through the 5 stages with sub-steps; Dashboard hero, KPI tiles, chart,
  activity and Applications update without reload.
- **AC06** Pause stops the simulated sending and shows the paused state;
  Resume continues. "Simulate failure" shows the failed step and reason and
  the item appears under "Needs attention".
- **AC07** Selecting more jobs than the remaining quota blocks sending with
  an inline message; one job per company is enforced in the selection.
- **AC08** No screen shows a job URL, a recipient email address or a company
  domain; template previews show the "job link" chip.
- **AC09** Profiles: create/delete language, upload/replace/remove CV (PDF
  only, ≤ 5 MB, validated client-side), edit subject/body/cover letter with
  variable chips, live preview, unsaved-changes guard; Jobs shows the
  "locked by language" notice when a language has no profile.
- **AC10** Preferences shows the rule banner, the summary sentence updates
  as the user types, the counter updates (debounced) and "Only these
  locations" requires at least one location.
- **AC11** Onboarding runs the 4 steps; with onboarding incomplete, the
  Dashboard shows the Setup card and the locked overlays.
- **AC12** Register with a valid fixture invite shows the form; an invalid
  one lands on Closed; forgot/reset pages show their success states.
- **AC13** All strings translated in EN/PT/ES (same key set in the 3
  files); switching language changes every screen, including numbers,
  currencies and dates.
- **AC14** Responsive: every screen works at 360, 768, 1024, 1440 px with
  no horizontal scroll; modals become full-screen sheets on mobile.
- **AC15** New UI reuses the foundation kit; any new primitive/pattern is
  on `/dev/styleguide`; no raw hex in new code; no polling.

## Verification

`yarn check`, `yarn types:check`, `yarn build`, `composer lint:check`,
`composer types:check`, existing tests. Owner walkthrough with DevToolbar:
each plan, each state, each language, mobile width.

## Out of scope

Any backend endpoint, database change or email; real registration, invites,
password reset, billing, OAuth changes; dark mode; the real landing page.

## Owner decisions

None open.
