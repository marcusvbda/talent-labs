# production-readiness — hosting, Google verification, legal, opt-out, monitoring

> **Order:** 10 of 10. **Depends on:** specs 1–7 DONE (8–9 optional for the
> closed beta). **Kind:** infrastructure, compliance and small features.
> Several items are actions for the owner outside the code; they are listed
> so nothing is forgotten.
>
> **How to run:** resolve D-HOST and D-DOMAIN, then
> `/plan-spec docs/features/production-readiness/spec.md`.

## Part 0 — Context and decisions

The product emails real companies from real people's Gmail accounts and
probes company mail servers over SMTP. Production must keep these
processes alive, protect client data, and meet Google's OAuth rules.

Decided:
1. **Services:** hosting with PostgreSQL (web, queue workers, Reverb and
   scheduler on the same host), **Resend** (transactional email), **Stripe**
   (when billing ships), an **error tracker**. Nothing else.
2. **Vercel and most serverless/PaaS hosts are unsuitable**: the app needs
   long-running processes and **outbound port 25** (SMTP probes of contact
   discovery). Without port 25 every contact becomes `mx_only` and the pool
   is empty.
3. **Google OAuth:** the app requests only `gmail.send`, classified by
   Google as a **sensitive** scope (not restricted) — verification needs a
   public homepage, privacy policy on the same domain, domain verification,
   a demo video and scope justification; **no paid security assessment**
   (that applies to restricted scopes). Until verified: test mode with
   manually added test users (small cap) and a warning screen; test-mode
   refresh tokens expire, so testers may have to reconnect periodically.
4. **Company opt-out** is mandatory before sending at scale (B.5).

## Part B — Product spec

### B.1 Hosting target (architecture recommendation)

A single VPS to start, managed by a provisioning tool:
- **Server:** a small Linux VPS from a provider that **allows outbound port
  25 on request** (e.g. Hetzner Cloud; verify their current policy — new
  accounts usually have it blocked until a request is approved), 2 vCPU /
  4 GB RAM is enough for the closed beta.
- **Provisioning:** Laravel Forge or Ploi (paid, saves time) or a manual
  setup following B.2. Laravel Cloud (repo skill `deploying-to-cloud`) is
  convenient but check outbound port 25 support before choosing it.
- **Database:** PostgreSQL on the same server (managed DB later), daily
  backups (B.6).

### B.2 Processes (supervisor / provisioning tool daemons)

| Process | Command |
|---|---|
| Web | nginx + php-fpm 8.4, HTTPS (Let's Encrypt), HTTP/2 |
| Queue `collection` | `php artisan queue:work database --queue=collection --tries=1 --timeout=120` |
| Queue `contacts` | `php artisan queue:work database --queue=contacts --timeout=60` |
| Queue `outreach` | `php artisan queue:work database --queue=outreach --timeout=75` (must exceed the job timeout 60 and stay below `retry_after` 90) |
| Queue `default` | `php artisan queue:work database --queue=default` |
| Reverb | `php artisan reverb:start` behind nginx (`wss://<domain>/app` proxy with upgrade headers) |
| Scheduler | cron `* * * * * php artisan schedule:run` |

Deploy script: `composer install --no-dev -o`, `yarn install --frozen-lockfile && yarn build`,
`php artisan migrate --force`, `config:cache`, `route:cache`,
`view:cache`, `event:cache`, `queue:restart`, `reverb:restart`.
Never `migrate:fresh` in production (owner rule).

### B.3 Environment inventory

Keep `.env.example` complete and grouped with comments (no extra doc
files, per the docs rule), and list in the phase report every key with
"required in production" / "optional": app (`APP_ENV=production`, `APP_DEBUG=false`,
`APP_URL`, `APP_KEY`), brand, DB, queue, Reverb (+ `VITE_REVERB_*` with the
public host and `wss`), mail/Resend, Google OAuth (`GOOGLE_REDIRECT_URI`
on the production domain), OpenAI, outreach (intervals, window, step delay;
`OUTREACH_INTERCEPT_TO` **empty** in production), plans/prices, Stripe,
contacts SMTP (`CONTACTS_SMTP_HELO` = a hostname whose PTR record points
to the server IP; `CONTACTS_SMTP_MAIL_FROM` on the domain), error tracker
DSN, `VITE_USE_FIXTURES=false`. Seed users are refused in production
already (`UserSeeder`); the first admin is created with a documented
`php artisan tinker`/command step.

### B.4 Public pages for Google verification

- **Homepage requirement:** Google needs a public homepage that clearly
  describes the app. The minimal "closed beta" landing is not enough.
  Extend `landing` (still small): one paragraph "What <brand> does" (collects
  developer jobs, matches them to your preferences, sends applications from
  your own Gmail with your CV, one company at a time), how Gmail is used
  ("only to send the applications you choose or allow; we never read your
  inbox"), links to Privacy and Terms, contact email. No marketing design.
- **Privacy policy** (`/privacy`) and **Terms** (`/terms`): Inertia pages
  rendering Markdown files from `resources/content/{privacy,terms}.{en,pt,es}.md`.
  The spec provides the **structure**; the owner provides the final text
  (ideally reviewed by a lawyer). Privacy must cover: data collected
  (account, CV files, templates, Gmail OAuth tokens, application records),
  Google user data use limited to sending (Google API Services User Data
  Policy / Limited Use statement), storage and retention, deletion and
  export (already in the app), processors (hosting, Resend, Stripe, OpenAI
  for job text only — never client data), contact for requests
  (LGPD/GDPR), cookies (session only).
- **Company opt-out page** (`/opt-out`, public): see B.5.
- Owner checklist (outside code): verify the domain in Google Search
  Console; configure the OAuth consent screen (app name, logo, homepage,
  privacy, terms, authorized domain, `gmail.send` scope, justification);
  record the demo video (unlisted YouTube) showing sign-in, consent, and a
  send; submit for verification.

### B.5 Company opt-out (suppression list)

- Table `outreach_suppressions` (new create migration): `id`, `domain`
  string nullable (registrable domain), `email` string nullable, `reason`
  string(32) (`company_request`, `bounce_report`, `admin`), `note`,
  `created_at`; unique on domain and on email.
- Public form `/opt-out`: company email address → a confirmation email
  (Resend) with a signed link; confirming adds the **domain** of that email
  to the list. Rate limited. Copy: "Stop receiving job applications sent
  through <brand>."
- Enforcement: `MatchingJobPostings` excludes companies whose domain is
  suppressed; `SendApplicationEmail` re-checks at send time (validating
  stage → failed "The company asked not to receive applications").
- Admin: `SuppressionResource` (list, add, remove).
- Every application email gets a short footer line (in the profile
  language): "Sent via <brand> on behalf of <client name>. Companies can
  opt out at <url>/opt-out." — owner decision D-FOOTER.

### B.6 Backups, monitoring, security

- **Backups:** daily `pg_dump` + the private `storage/app/private/cvs`
  folder to off-site object storage, 14 daily + 8 weekly retention, a
  documented restore test. (Tool: provisioning tool backups or
  `spatie/laravel-backup` — dependency, owner approves.)
- **Error tracking:** `sentry/sentry-laravel` (dependency, owner approves)
  or the provisioning tool's built-in tracker; PII scrubbing on (no CV
  contents, no tokens, no email bodies).
- **Alerts:** failed jobs (`Queue::failing`) and a daily summary email to
  admins (Resend): failed/ambiguous counts, collection runs, `mx_only`
  share (port 25 health, from `reports:funnel` if spec 9 ran).
- **Health:** `/up` (Laravel default) checked by an uptime monitor.
- **Security:** HTTPS only, HSTS, secure/HttpOnly/SameSite=Lax cookies,
  `SESSION_SECURE_COOKIE=true`, admin panel only for `is_admin`, rate
  limits already on auth and internal routes, `storage` CV files never
  public, OAuth tokens encrypted (already), `APP_DEBUG=false`, logs rotated
  daily (14 days), no secrets in logs.
- **Deliverability of transactional mail:** Resend domain verified with SPF,
  DKIM and a DMARC record (`p=none` to start).

## Acceptance criteria

- **AC01** A production deploy runs all processes of B.2; killing a worker
  restarts it; a deploy does not lose queued jobs.
- **AC02** Outbound port 25 works from the server (a `contacts:backfill`
  sample yields `smtp_verified` contacts; `mx_only` share is not ~100%).
- **AC03** Realtime works over `wss` on the production domain.
- **AC04** Landing (with the "What it does" paragraph), Privacy and Terms are
  public in EN/PT/ES and linked from the landing and the OAuth consent
  screen.
- **AC05** Opt-out: confirming the email link suppresses the domain; its
  jobs disappear from pools; queued applications to it fail at validation.
- **AC06** Backups run daily and a restore was tested once.
- **AC07** Errors reach the tracker without PII; failed jobs trigger an
  admin email.
- **AC08** `.env.example` documents every production key; production runs
  with `APP_DEBUG=false` and `OUTREACH_INTERCEPT_TO` empty.

## Verification

Staging-style run on the VPS before inviting testers: register via invite,
connect Gmail (test user), send to an intercept address, then one real send
to a friendly company address.

## Out of scope

Multi-server scaling, managed database, CDN, marketing site.

## Owner decisions (resolve before planning)

- **D-HOST:** VPS + Forge/Ploi (recommended) vs manual VPS vs Laravel Cloud
  (only if port 25 is supported).
- **D-DOMAIN:** product name and domain (needed for OAuth verification,
  Resend and legal pages). Name expected "<something> Labs", logo "t" with
  orange dot is ready.
- **D-FOOTER:** add the opt-out footer to every application email
  (recommended for LGPD/GDPR and deliverability) — yes/no.
- **D-DEPS:** approve `sentry/sentry-laravel` and optionally
  `spatie/laravel-backup`.
- **D-LEGAL:** who writes/reviews Privacy and Terms (recommended: a lawyer,
  since the product contacts third parties on behalf of users).
