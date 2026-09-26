# regional-pricing-and-billing — prices per region and Stripe subscriptions

> **Order:** 8 of 10. **Depends on:** `client-core-wiring` (plan catalog,
> regions) and `plans-and-sending-modes` DONE.
> **Kind:** backend + wiring of S7 (Plans) and billing in Account.
> **Not needed for the closed beta:** until this ships, the admin assigns
> plans manually (already possible).
>
> **How to run:** resolve the owner decisions below first, then
> `/plan-spec docs/features/regional-pricing-and-billing/spec.md`.

## Part 0 — Context and decisions

Decided (owner):
1. **Stripe** is the payment provider.
2. **Prices vary by the buyer's region**: Brazil (BRL), Europe (EUR), rest of
   the world (USD). Region comes from the country the user chose (signup /
   onboarding / account), never from IP. Changing country changes the price
   for the **next** subscription, not the current one.
3. Prices, plan names and descriptions are **config/env values** editable
   before deploy.
4. **Manual plans stay:** a plan set by the admin (testers: owner's wife,
   friend) is never billed and is not overwritten by Stripe events.

Decided here (architecture, delegated by the owner):
5. **Laravel Cashier (Stripe)** (`laravel/cashier`) for customers,
   subscriptions, Checkout, Billing Portal and webhooks. One Stripe
   **Price** per plan × region (currency), IDs in env.
6. Stripe **Checkout** (hosted) to subscribe, Stripe **Billing Portal**
   (hosted) to change card, cancel, see invoices. No card forms in the app.
7. Plan changes: upgrade/downgrade through the Billing Portal (configured to
   allow switching between the plan prices of the same currency, with
   proration). The app reacts to webhooks only.

## Part B — Product spec

### B.1 Files to read first

`config/talent.php` (`plans`, `regions`), `app/Plans/PlanCatalog.php`,
`app/Models/User.php`, the users create migration, the Plans page and
Account page code (`resources/js/pages/{plans,account}.tsx`, features),
contracts `PlansData`, `PlanOffer`, `AccountStatus`, Cashier docs via Boost
`search-docs`.

### B.2 Dependency (owner approves)

`laravel/cashier` (composer). Publishes its migrations: **copy their
columns into the existing create migrations** where they touch `users`
(`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`) and keep Cashier's
`subscriptions` / `subscription_items` as new create migrations (development
mode; owner runs `migrate:fresh --seed`).

### B.3 Config

```php
'plans' => [
    // existing 'default' and 'catalog' …
    'prices' => [ // minor units, per region; placeholders until the owner sets them
        'free'    => ['br' => (int) env('PRICE_FREE_BRL', 2500),  'eu' => (int) env('PRICE_FREE_EUR', 500),  'row' => (int) env('PRICE_FREE_USD', 500)],
        'starter' => ['br' => (int) env('PRICE_STARTER_BRL', 5000), 'eu' => (int) env('PRICE_STARTER_EUR', 900), 'row' => (int) env('PRICE_STARTER_USD', 1000)],
        'pro'     => ['br' => (int) env('PRICE_PRO_BRL', 10000),   'eu' => (int) env('PRICE_PRO_EUR', 1900), 'row' => (int) env('PRICE_PRO_USD', 2000)],
    ],
    'stripe_prices' => [ // Stripe Price IDs, one per plan x region
        'free' => ['br' => env('STRIPE_PRICE_FREE_BRL'), 'eu' => env('STRIPE_PRICE_FREE_EUR'), 'row' => env('STRIPE_PRICE_FREE_USD')],
        // starter, pro: same pattern
    ],
    'highlighted' => env('PLAN_HIGHLIGHTED', 'starter'),
    'contact_email' => env('PLANS_CONTACT_EMAIL'), // shown while billing is off
],
'billing' => ['enabled' => (bool) env('BILLING_ENABLED', false)],
```

A plan whose price is `0` in a region is free there (no Checkout). The
displayed price comes from config; the charged price comes from Stripe —
the owner must keep them equal (documented in `.env.example`).

### B.4 Data model

`users`: Cashier columns (B.2) and `plan_source` string(16) default
`'manual'` (`manual` | `stripe`). Admin changes set `manual`; webhooks set
`stripe`. Webhooks never change a `manual` user's plan.

### B.5 Flows

- `GET /internal/plans?region=` → `PlansData` from config (region param only
  changes the **displayed** prices; checkout always uses the user's region),
  `billingAvailable = config('talent.billing.enabled') && Stripe keys set`.
- `POST /internal/billing/checkout` `{ plan }` → creates a Checkout Session
  for the user's region price (`$user->newSubscription('default',
  $priceId)->checkout([...])`), success/cancel URLs back to `/plans`
  (`?checkout=success|cancel`), returns `{ url }`; the frontend redirects.
  Rejected when billing is off, the plan is the current one, or the user is
  `manual` with a paid plan (message: contact us).
- `POST /internal/billing/portal` → `{ url }` of the Billing Portal.
- Webhooks: Cashier's `/stripe/webhook` (CSRF-excluded). Listen to
  `WebhookReceived`/`WebhookHandled` for subscription created/updated/deleted:
  map the active price ID back to a plan key (reverse lookup in
  `stripe_prices`), set `plan_key` and `plan_source = stripe`; on
  cancellation at period end or deletion, set `plan_key =
  config('talent.plans.default')` when the subscription ends. Dispatch
  `AccountStatusUpdated`.
- Failed payment (`invoice.payment_failed`): notify the client (database
  notification + live) with a link to the portal; Stripe's dunning handles
  retries; when the subscription ends the plan falls back as above.
- S7 Plans: real data; CTA → checkout (or portal when the user already has a
  Stripe subscription); `billingAvailable = false` keeps the "contact us"
  modal with `contact_email`.
- Account: "Billing" card (plan, source, next renewal date, "Manage
  billing" → portal) when the user has a Stripe customer.
- Admin: Users show `plan_source`, Stripe customer link (dashboard URL),
  subscription status.

### B.6 Taxes and invoices

See owner decision D-TAX. Until decided, Stripe Tax is **off** and
invoices are Stripe's standard invoices.

## Acceptance criteria

- **AC01** Plans page shows the three plans in the user's region currency
  with prices from config; changing the region selector only changes the
  displayed prices.
- **AC02** With billing enabled and Stripe test keys, a user subscribes via
  Checkout, returns to `/plans?checkout=success`, and within seconds the
  webhook sets the plan (live update of limit and mode).
- **AC03** Changing plan or cancelling in the Billing Portal updates the plan
  through webhooks; cancellation keeps the plan until the period ends, then
  falls back to the default plan.
- **AC04** A user with `plan_source = manual` is never changed by webhooks
  and cannot start a checkout for a paid plan.
- **AC05** With billing disabled, the Plans CTA shows the contact modal and
  no Stripe call is made.
- **AC06** Webhook signature is verified; replays are idempotent.

## Verification

Stripe CLI (`stripe listen --forward-to …/stripe/webhook`) with test mode
prices; `composer lint:check`, `composer types:check`, `yarn check`.

## Out of scope

Coupons, trials (unless decided), annual plans, invoices inside the app,
per-seat billing.

## Owner decisions (resolve before planning)

- **D-FREEPRICE:** is "Free" really free (price 0) or a low-price entry
  plan (config placeholder today: R$25 / €5 / $5)? If it has a price,
  consider renaming it (e.g. "Basic").
- **D-PRICES:** final prices per region (placeholders in B.3).
- **D-TAX:** EU VAT and Brazilian taxes. Options: (a) Stripe Tax (you remain
  the seller and file taxes), (b) a merchant of record (Paddle/Lemon
  Squeezy) instead of Stripe, (c) postpone paid EU sales. This is a
  legal/financial choice — get advice from an accountant.
- **D-TRIAL:** trial period for paid plans (e.g. 7 days) or none.
- **D-PIX:** whether Pix is needed for Brazil at launch (check Stripe's
  availability for your account; otherwise card only).
