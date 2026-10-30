# Plan — Email Providers

Spec: `docs/features/email-providers/spec.md` · SHA-256 `5f9bf9c3739399e6cc2e96ddde5168dd50be8b0badd0a1339f3efe62dfda964c`
Run phases with `/exec-phase email-providers <phases>` — one or a few per session.
Phase status is updated in place in this file.

## Diagnosis — 2026-10-30

Implemented: 1 · Partial: 3 · Divergent: 0 · Missing: 15 · Unverifiable: 0

| Requirement | State | Evidence |
| ----------- | ----- | -------- |
| P1 `EmailTransport` and resolver | Missing | `app/Outreach/Contracts/SendsGmailMessages.php`, bound in `AppServiceProvider` |
| P2 generated `Message-ID` | Partial | Symfony generates a random id in `Email::toString()`; not a UUID, never captured |
| P3 `User::sendingIntegration()` | Missing | `User::gmailIntegration()` |
| P4 neutral wording | Missing | Gmail copy in `lang/*.json`, enums, `CanSendApplications`, job, controller, frontend |
| P5 error classification | Partial | Reauth and retry exist in `SendApplicationEmail`; no limit or recipient handling |
| P6 provider list | Missing | `gmail-card.tsx`, `step-gmail.tsx` hardcode Gmail |
| P7 SMTP credential integration | Missing | Registry only accepts `OAuthIntegrationPlugin` |
| P8 SMTP form | Missing | — |
| P9 presets | Missing | — |
| P10 ports 465/587 only | Missing | — |
| P11 encrypted credentials | Partial | `encrypted` cast pattern on `ConnectedIntegration`; no credentials column. `AccountExportController` has no integration data |
| P12 connection test | Missing | `PublicNetworkGuard` is ready (`production-hardening` P7) |
| P13 from address rule | Missing | — |
| P14 `SmtpMessageSender` | Missing | `symfony/mailer` 8.1.7 installed |
| P15 Sent-folder notice | Missing | — |
| P16 `MicrosoftPlugin` | Missing | `league/oauth2-client` 2.9.1 installed |
| P17 `GraphMessageSender` | Missing | — |
| P18 Microsoft env | Missing | — |
| P19 plan daily limit | Implemented | `SendApplicationEmail::beginAttempt()` is provider-independent |

## Status board

| Phase | Title | Role | Depends on | Origin | Size | Status |
| ----- | ----- | ---- | ---------- | ------ | ---- | ------ |
| 1 | Provider-agnostic transport and `Message-ID` | laravel-backend | none | P1, P2 | M | DONE |
| 2 | `sendingIntegration()` replaces `gmailIntegration()` | laravel-backend | 1 | P3 | M | PENDING |
| 3 | Enforce one sending account | laravel-backend | 2 | P3 | S | PENDING |
| 4 | Shared error classification | laravel-backend | 1 | P5 | M | PENDING |
| 5 | Provider-limit pause and auto-resume | laravel-backend | 2, 4 | P5 | M | PENDING |
| 6 | Neutral wording and notification | laravel-backend | 2, 5 | P4 | M | PENDING |
| 7 | `sendingAccount` contract and main screens | inertia-frontend | 6 | P3, P4 | M | PENDING |
| 8 | Remove the `gmail` contract key | inertia-frontend | 7 | P4 | M | PENDING |
| 9 | Provider list from the registry | inertia-frontend | 8 | P6 | M | PENDING |
| 10 | Credential plugin contract and storage | laravel-backend | 2 | P7, P11 | M | PENDING |
| 11 | `SmtpMessageSender` | laravel-backend | 4, 10 | P2, P5, P14 | M | PENDING |
| 12 | SMTP connect endpoint and test | laravel-backend | 3, 11 | P8, P9, P10, P12, P13 | M | PENDING |
| 13 | SMTP form and registration | inertia-frontend | 9, 12 | P6, P8, P15 | M | PENDING |
| 14 | `GraphMessageSender` and CV size check | laravel-backend | 4 | P5, P17 | M | PENDING |
| 15 | `MicrosoftPlugin`, env and card notice | laravel-backend | 9, 10, 14 | P16, P17, P18 | M | PENDING |
| 16 | Verification and report | laravel-backend | all | all | S | PENDING |

## Owner decisions

All answered on 2026-10-30 and recorded in `spec.md`.

### D1 — How many sending accounts per user?

Blocks: none · Options: A one (recommended), B several with an active one · Why: simplest · Answer: A.

### D2 — "Send a test email to myself"?

Blocks: none · Options: A not in v1 (recommended), B add · Why: EHLO + AUTH proves the login · Answer: A.

### D3 — "Bcc me on every application"?

Blocks: none · Options: A not in v1 (recommended), B add · Why: applications stay visible in the app · Answer: A.

### D4 — Timeouts and network errors?

Blocks: none · Options: A retry only if provably unsent (recommended), B always ambiguous, C always retry · Why: a recruiter must never get the same application twice · Answer: A.

### D5 — Transport signature?

Blocks: none · Options: A `OutboundEmail` object (recommended), B keep `string $rawMime` and parse it · Why: SMTP needs the recipient and `Message-ID`; parsing is fragile · Answer: A.

### D6 — May Phase 1 edit `SendingDatabaseGuardsTest.php`?

Blocks: none · Options: A this one edit (recommended), B keep the old interface, C owner edits it · Why: the test binds the removed interface · Answer: A, limited to the fake sender's interface, signature and binding.

### D7 — CVs too large for Microsoft Graph?

Blocks: none · Options: A fail clearly and warn on the card (recommended), B lower the limit for everyone, C upload sessions · Why: about 4 MB request cap; upload sessions need more than `Mail.Send` · Answer: A.

### D8 — SMTP from address rule?

Blocks: none · Options: A email login and same domain (recommended), B same domain only if the login is an email · Why: blocks relays that can send as anyone · Answer: A.

## Global constraints (every phase)

- Git writes only through `/exec-phase`; subagents never run them.
- Never run `migrate:fresh`, `refresh`, `reset`, `rollback`, `db:wipe`, `DROP` or `TRUNCATE`. Schema changes are folded into the table's create migration; the owner runs `php artisan migrate:fresh --seed`.
- No new composer or npm packages.
- Tests stay untouched, except the edit Phase 1 is allowed (D6).
- All repo text is English; client-facing copy is EN and PT only, in `lang/en.json` and `lang/pt.json`. No Spanish.
- Realtime only: never `->poll()` or `wire:poll`.
- Stored values do not change: `checking_gmail`, `connecting_gmail`, `reauthorization_required`. Old `gmail_reauthorization_required` notification rows must still render.
- Translation keys keep their names (Assumption): only values change, except new keys listed in a phase. `account.gmail.*` stays as a key.
- An SMTP password is never returned to the browser, logged, exported, shown in admin, or placed in exception messages or context. Never pass a Symfony Mailer exception as `previous` (its message can contain the username and server text).
- A message must never go out twice: anything after delivery may have started ends as `ambiguous`.
- Preserve: `CompleteConnectedIntegration` resumes a `reauthorization_required` pause on reconnect; `SendApplicationEmail::$timeout = 60`; OAuth state and return-URL handling. Do not change disconnect's lack of a pause or `GmailBanner`'s ignored `redirect` query (flagged to the owner, out of scope).
- Gmail stays OAuth only (spec D3).

## Requirement coverage

| Requirement | Phases |
| ----------- | ------ |
| P1 | 1, 11, 14 |
| P2 | 1, 11, 14 |
| P3 | 2, 3, 7 |
| P4 | 6, 7, 8 |
| P5 | 4, 5, 11, 14 |
| P6 | 9, 13 |
| P7 | 10, 12 |
| P8 | 12, 13 |
| P9 | 12 |
| P10 | 12 |
| P11 | 10, 12 |
| P12 | 12 |
| P13 | 12 |
| P14 | 11 |
| P15 | 13 |
| P16 | 15 |
| P17 | 14, 15 |
| P18 | 15 |
| P19 | Implemented; verified in 16 |

## Phases

### Phase 1 — Provider-agnostic transport and `Message-ID`

Status: DONE
Evidence: `grep SendsGmailMessages app tests` empty; test diff limited to import, fake sender and binding; pint, `composer types:check` and `SendingDatabaseGuards` (7 tests) pass; reviewer finding (stage left at `attaching_file` when the transport cannot be resolved) fixed by resetting the stage before rethrow.
Role: laravel-backend · Depends on: none · Covers: P1, P2 · Size: M
Origin: P1 "Replace `SendsGmailMessages` with a provider-agnostic `EmailTransport`" · P2 "Every MIME message the app builds carries a generated `Message-ID`"

**Goal.** The send job talks to a transport chosen by `plugin_key`; every message has a known `Message-ID`.

**Gap -> desired.** `SendApplicationEmail` depends on `SendsGmailMessages` and a raw string -> it uses `EmailTransportResolver` and an `OutboundEmail`.

**Contract.**

- `App\Outreach\Data\OutboundEmail`: `final readonly`, constructor `(string $rawMime, string $messageId, string $from, string $recipient)`.
- `App\Outreach\Contracts\EmailTransport`: `send(ConnectedIntegration $integration, OutboundEmail $email): string`. Returns the provider id, or `$email->messageId` when the provider has none.
- `App\Outreach\Support\EmailTransportResolver`: `for(ConnectedIntegration $integration): EmailTransport`, reads `config('connected-integrations.transports')[plugin_key]`, resolves with `app($class)`, throws `LogicException` on an unknown key.
- `config/connected-integrations.php`: `'transports' => ['gmail' => GmailApiMessageSender::class]`.
- `buildMime()` returns `OutboundEmail`. `$messageId = Str::uuid().'@'.Str::after((string) $integration->account_email, '@')`, set with `$email->getHeaders()->addIdHeader('Message-ID', $messageId)` (no angle brackets stored). `recipient` is `$interceptTo ?? $application->recipient_email`, so test mode never reaches the company. `from` is `account_email`.
- Resolve the transport before `beginAttempt()`, so a broken config leaves the row queued rather than `ambiguous`.
- `GmailApiMessageSender` still returns the Gmail id and posts `$email->rawMime`.
- Allowed test edit (D6), in `tests/Feature/Outreach/SendingDatabaseGuardsTest.php` only: `RecordingGmailSender implements EmailTransport` with the new signature; in `beforeEach`, `$this->app->instance(RecordingGmailSender::class, $this->gmail)` and `config(['connected-integrations.transports.gmail' => RecordingGmailSender::class])`. Replace the import. Change no assertion.

**Steps.**

1. Create the DTO, interface and resolver; add the config map.
2. Update `GmailApiMessageSender` and `SendApplicationEmail` (`handle`, `preCheck`, `buildMime`, `beginAttempt` docblocks and types).
3. Delete `SendsGmailMessages` and its binding in `AppServiceProvider`.
4. Make the allowed test edit.

**Done when.**

- `grep -rn SendsGmailMessages app tests` finds nothing.
- `git diff` of the test touches only the import, fake sender and binding.
- `vendor/bin/pint --dirty --format agent`, `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** Error classification, other transports.

### Phase 2 — `sendingIntegration()` replaces `gmailIntegration()`

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: P3 · Size: M
Origin: P3 "`User::sendingIntegration()` replaces `gmailIntegration()`"

**Goal.** All code reads the user's sending account without naming Gmail.

**Gap -> desired.** Seven classes use `gmailIntegration` -> all use `sendingIntegration`. Behaviour for Gmail users is unchanged.

**Contract.**

- `ConnectedIntegrationRegistry::sendingPluginKeys(): array` (`list<string>`): keys whose `capabilities()` contains `email.send`.
- `User::sendingIntegration(): HasOne`: `hasOne(ConnectedIntegration::class)->whereIn('plugin_key', app(ConnectedIntegrationRegistry::class)->sendingPluginKeys())->where('status', '!=', ConnectedIntegrationStatus::Disconnected)`. Assumption: a `reauthorization_required` row must still be returned, because the Reconnect UI and `CanSendApplications` depend on it.
- Replace `gmailIntegration` in `SendApplicationEmail::preCheck`, `CanSendApplications`, `AccountStatusPresenter`, `SendingController::resume`, and `UsersTable` (column `sendingIntegration.status` and the eager load). Remove `gmailIntegration()` and its `@property-read`.
- `CanSendApplications` keeps its null-or-disconnected and reauthorization branches, using the same strings for now.

**Steps.**

1. Add the registry method and relation.
2. Update the consumers.

**Done when.**

- `grep -rn gmailIntegration app tests resources` finds nothing.
- `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** Copy changes, one-account enforcement.

### Phase 3 — Enforce one sending account

Status: PENDING
Role: laravel-backend · Depends on: 2 · Covers: P3 · Size: S
Origin: P3 "connecting another provider requires disconnecting the current one"

**Goal.** A user can never hold two active sending accounts, even with two tabs open.

**Gap -> desired.** Only the same plugin is checked at connect -> other sending plugins block it too, at start and on completion.

**Contract.**

- `ConnectedIntegrationOAuthController::connect()`: if `$user->sendingIntegration` exists with a different `plugin_key` and the requested plugin has `email.send`, remember the return target, then redirect to it `->with('error', __('account.email_provider.disconnect_first'))`.
- `CompleteConnectedIntegration::run()`: inside the transaction, lock the user row (`User::query()->whereKey($user->getKey())->lockForUpdate()->first()`), and throw `RuntimeException` if another `email.send` integration of the user is not `Disconnected`. The callback's existing `catch (Throwable)` shows the failure flash.
- New keys: `account.email_provider.disconnect_first`. EN "Disconnect your current email account before connecting another." PT "Desconecte sua conta de e-mail atual antes de conectar outra."

**Steps.**

1. Add both checks and the two translations.

**Done when.**

- With Gmail connected, connecting another `email.send` plugin is refused; reconnecting Gmail still works.
- `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** SMTP's own check (Phase 12).

### Phase 4 — Shared error classification

Status: PENDING
Role: laravel-backend · Depends on: 1 · Covers: P5 · Size: M
Origin: P5 "Error classification per transport, shared by all"

**Goal.** Transports report outcomes through shared exceptions; the job no longer reads Gmail's HTTP errors.

**Gap -> desired.** `SendApplicationEmail::isAuthorizationError()` parses Gmail JSON, and recipient rejections retry 3 times -> `GmailApiMessageSender` classifies, the job reacts by type.

**Contract.**

- New exceptions in `App\Outreach\Exceptions`, all extending `RuntimeException`, with fixed messages:
    - `EmailAuthorizationRejected`: auth or consent revoked.
    - `EmailProviderLimitReached`: provider sending or rate limit.
    - `EmailRecipientRejected`: recipient refused.
    - `EmailDeliveryDeferred`: provably not delivered; retry with backoff.
    - `EmailDeliveryUnconfirmed`: outcome unknown.
- `GmailApiMessageSender` catches `RequestException` and maps it:
    - 401, or reason `authError`/`insufficientPermissions`, or status `UNAUTHENTICATED` -> `EmailAuthorizationRejected`.
    - 429, or 403 with reason `rateLimitExceeded`/`userRateLimitExceeded`/`dailyLimitExceeded`/`quotaExceeded` -> `EmailProviderLimitReached`.
    - 400 whose `error.message` contains `Invalid To header` or `Recipient address required` -> `EmailRecipientRejected` (Assumption: verify these strings against a real response in Phase 16).
    - anything else -> `EmailDeliveryDeferred`.
    - `ConnectionException` is not caught.
- `SendApplicationEmail::handle()` catches, in order:
    - `ConnectionException` -> existing `Ambiguous` branch.
    - `ConnectedIntegrationReauthorizationRequired` -> existing branch.
    - `EmailAuthorizationRejected` -> existing reauth branch (`requireReauthorization`, Failed, auto-pause).
    - `EmailRecipientRejected` -> Failed, `recorder->record(Failed, Delivering)`, `afterTerminal`, no retry.
    - `EmailProviderLimitReached` -> in this phase, same as `EmailDeliveryDeferred`; Phase 5 replaces it.
    - `EmailDeliveryDeferred` -> existing retry block (Failed once `attempts() >= tries`, else Queued, `recorder->reset`, rethrow).
    - `Throwable` -> existing `Ambiguous` branch.
- Remove `isAuthorizationError()`.
- `ClientErrorMessage`: add `EmailDeliveryUnconfirmed` to `TRANSPORT_ERRORS`; add prefix `EmailRecipientRejected` -> `applications.error.recipient_rejected`. EN "The recipient's mail server rejected this address." PT "O servidor de e-mail do destinatário rejeitou este endereço."

**Steps.**

1. Add the exceptions.
2. Move Gmail's classification into its sender.
3. Update the job and `ClientErrorMessage`, plus the two translations.

**Done when.**

- `grep -rn "RequestException\|isAuthorizationError" app/Outreach/Jobs` finds nothing.
- `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** The `provider_limit` pause.

### Phase 5 — Provider-limit pause and auto-resume

Status: PENDING
Role: laravel-backend · Depends on: 2, 4 · Covers: P5 · Size: M
Origin: P5 "provider sending/rate limit → sending paused until the next day, the application is **not** failed"

**Goal.** A provider limit parks the application and pauses sending until the next day's window.

**Gap -> desired.** No such pause reason -> new reason, parked row, automatic resume.

**Contract.**

- `SendingPauseReason::ProviderLimit = 'provider_limit'`. Label "Provider sending limit", color `warning`. Fits `sending_pause_reason` (32 chars).
- Job, on `EmailProviderLimitReached`: `forceFill(['status' => Queued, 'scheduled_for' => null, 'last_error' => self::errorMessage($exception)])`, `$recorder->reset($application)`, then `pause($user, ProviderLimit)`. Return without rethrowing. Do not call `afterTerminal`.
- When `pause()` returns true:
    - send new `App\Notifications\Client\SendingProviderLimitReached` (type `sending_provider_limit`, empty payload);
    - dispatch `App\Outreach\Jobs\ResumeSendingAfterProviderLimit($userId, $pausedAtTimestamp)` on queue `outreach`, delayed to `$scheduler->nextWindowStart($user, CarbonImmutable::now()->addDay()->startOfDay())` (same call as the over-limit branch in `beginAttempt`).
- `ResumeSendingAfterProviderLimit::handle(SendScheduler $scheduler)`: resume only when the user exists, `sending_pause_reason === ProviderLimit`, `sending_paused_at` matches `$pausedAtTimestamp`, and `sendingIntegration?->status === Connected`. Otherwise do nothing. Assumption: the existing manual Resume also works during this pause.
- `AccountStatusPresenter`: add `ProviderLimit` to the `autoPausedReason` list. `resources/js/types/contracts.ts`: add `'provider_limit'` to `autoPausedReason` and `'sending_provider_limit'` to `NotificationType`.
- Frontend maps: `notifications-popover.tsx` icon `{ icon: PauseCircle, tint: 'orange' }` and target `account().url`; `handlers.ts` value `'error'`.
- Key `notifications.sending_provider_limit`. EN "Your email provider's sending limit was reached. Sending resumes tomorrow." PT "O limite de envio do seu provedor de e-mail foi atingido. O envio será retomado amanhã."

**Steps.**

1. Add the enum case, notification and job.
2. Update the job's catch branch and the presenter.
3. Update the contract, maps and translations.

**Done when.**

- A sender throwing `EmailProviderLimitReached` leaves the application `queued` with no slot and the user paused with `provider_limit`, and a delayed job exists (checked with `php artisan tinker`).
- `composer types:check`, `yarn check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** Limit detection for SMTP and Graph (Phases 11, 14).

### Phase 6 — Neutral wording and notification

Status: PENDING
Role: laravel-backend · Depends on: 2, 5 · Covers: P4 · Size: M
Origin: P4 "Provider-neutral wording everywhere listed in 2"

**Goal.** No client or admin copy assumes Gmail.

**Gap -> desired.** Gmail copy throughout -> neutral text; class becomes `EmailReauthorizationRequired`.

**Contract.**

- PHP strings:
    - `CanSendApplications`: "Connect an email account." / "Reconnect your email account."
    - `SendApplicationEmail`: "Email account is not connected." (replaces "Gmail is not connected.").
    - `ClientErrorMessage`: add the new prefix -> `applications.error.gmail` and keep the old prefix for existing rows.
    - `SendSubStep` labels: "Checking email account", "Connecting to email account".
    - `SendingPauseReason`: "Email reauthorization".
    - `ConnectedIntegrationOAuthController::FALLBACK_LABEL = 'Email account'`.
    - `UsersTable` column label "Email account".
    - Update the Gmail wording in comments in these files. Do not rename enum cases or values.
- Before changing the `CanSendApplications` strings, grep `app/Outreach/Support/QueueRejectionMessage.php` and `resources/js` for them and update any match.
- `GmailReauthorizationRequired` -> `EmailReauthorizationRequired`, type `email_reauthorization_required`, payload `['provider' => <plugin label>]`, sent from `ConnectedIntegrationTokenManager`.
- `NotificationType` keeps `gmail_reauthorization_required` and adds the new type; update `notifications-popover.tsx`, `handlers.ts` and the fixture catalog.
- Translations, EN / PT:

| Key | EN | PT |
| --- | -- | -- |
| `notifications.email_reauthorization_required` (new) | Your email connection expired — reconnect to keep sending. | Sua conexão de e-mail expirou — reconecte para continuar enviando. |
| `notifications.gmail_reauthorization_required` | same as above | same as above |
| `sending.sub.checking_gmail` | Checking email account | Verificando conta de e-mail |
| `sending.sub.connecting_gmail` | Connecting to email account | Conectando à conta de e-mail |
| `sending.resume.reconnect_gmail` | Reconnect your email account to resume sending. | Reconecte sua conta de e-mail para retomar o envio. |
| `dashboard.gmail.expired` | Your email connection expired. Sending is paused until you reconnect. | Sua conexão de e-mail expirou. O envio está pausado até você reconectar. |
| `dashboard.gmail.reconnect` | Reconnect email | Reconectar e-mail |
| `plans.summary` | Every plan sends from your own email account. Pick the daily volume and how much control you want. | Todos os planos enviam da sua própria conta de e-mail. Escolha o volume diário e o nível de controle que você quer. |
| `plans.features.gmail` | Sends from your own email address | Envia do seu próprio endereço de e-mail |
| `applications.error.gmail` | Your email account is not connected. | Sua conta de e-mail não está conectada. |
| `onboarding.steps.gmail` | Email | E-mail |
| `onboarding.gmail.title` | Connect your email | Conectar seu e-mail |
| `onboarding.gmail.body`, `account.gmail.body` | Applications leave from your own email address. | As candidaturas saem do seu próprio endereço de e-mail. |
| `onboarding.gmail.connect`, `account.gmail.connect` | Connect email | Conectar e-mail |
| `onboarding.gmail.connected` | Your email is connected. | Seu e-mail está conectado. |
| `account.gmail.title` | Email account | Conta de e-mail |
| `account.gmail.reconnect` | Reconnect email | Reconectar e-mail |
| `account.gmail.disconnect_title` | Disconnect this email account? | Desconectar esta conta de e-mail? |
| `account.gmail.disconnected` | Email account disconnected | Conta de e-mail desconectada |
| `account.gmail.connected_flash` | Email account connected. | Conta de e-mail conectada. |
| `account.gmail.failed_flash` | We could not connect your email account. Please try again. | Não conseguimos conectar sua conta de e-mail. Tente novamente. |
| `landing.faq.1.a` | No. We only use your connected account to send applications. We never read, list or delete your messages. | Não. Usamos a conta conectada apenas para enviar candidaturas. Nunca lemos, listamos ou apagamos mensagens. |

**Steps.**

1. Change the PHP strings and rename the notification.
2. Update both language files and the notification maps.
3. Check whether `lang/en.json` and `lang/pt.json` still mention Gmail in any value.

**Done when.**

- No value in `lang/en.json` or `lang/pt.json` contains "Gmail" or "Google".
- Old rows with `last_error` "Gmail is not connected." still map to `applications.error.gmail`.
- `composer types:check`, `yarn check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** `gmail` in the frontend contract; `GmailPlugin` keeps its Gmail copy.

### Phase 7 — `sendingAccount` contract and main screens

Status: PENDING
Role: inertia-frontend · Depends on: 6 · Covers: P3, P4 · Size: M
Origin: P4 "Provider-neutral wording everywhere" · P3 (the account the UI shows)

**Goal.** The account, dashboard and onboarding screens use a provider-aware contract.

**Gap -> desired.** `AccountStatus.gmail` drives everything and `'gmail'` is hardcoded in routes -> new `sendingAccount` with the provider key. `gmail` stays until Phase 8.

**Contract.**

- `AccountStatusPresenter` adds `'sendingAccount' => ['state' => …, 'accountEmail' => …, 'providerKey' => $integration?->plugin_key]`; `providerKey` is `null` when no account exists. `gmail` still returned, same values.
- `contracts.ts`: `sendingAccount: { state: …; accountEmail: string | null; providerKey: string | null }`.
- Renames:
    - `features/account/gmail-card.tsx` -> `email-account-card.tsx` (`EmailAccountCard`, prop `account`).
    - `features/dashboard/gmail-banner.tsx` -> `email-account-banner.tsx` (`EmailAccountBanner`, prop `providerKey`).
    - `features/onboarding/step-gmail.tsx` -> `step-email-account.tsx` (`StepEmailAccount`).
    - `data/hooks/use-connect-gmail.ts` -> `use-connect-email.ts` (`useConnectEmail`, `useDisconnectEmail(providerKey)`).
- Connect, reconnect and disconnect use `providerKey`; connect stays `'gmail'` until Phase 9.
- Update `pages/account.tsx`, `pages/dashboard.tsx`, `pages/onboarding.tsx`. Keep the fixture path (`setDevState`) working.

**Steps.**

1. Add the backend key and the type.
2. Rename the files and update their consumers.

**Done when.**

- Connect, reconnect and disconnect still work for Gmail in the browser, and with fixtures.
- `yarn check` and `composer types:check` pass.

**Not in this phase.** Send modals, fixtures, onboarding step key.

### Phase 8 — Remove the `gmail` contract key

Status: PENDING
Role: inertia-frontend · Depends on: 7 · Covers: P4 · Size: M
Origin: P4 "Provider-neutral wording everywhere"

**Goal.** The contract no longer names Gmail, apart from stored values.

**Gap -> desired.** Modals, plans and fixtures still use `gmail` -> they use `sendingAccount`.

**Contract.**

- `confirm-send-modal.tsx`, `random-send-modal.tsx`, `new-matches-card.tsx`, `pages/jobs.tsx`, `pages/dashboard.tsx`: prop `gmail` -> `fromAddress`. The translation param stays `:gmail` (Assumption, keys and params keep their names).
- Onboarding step key `'gmail'` -> `'email'` in `AccountStatusPresenter`, `contracts.ts`, `pages/onboarding.tsx` and `t()` lookups, mapping to the existing `onboarding.steps.gmail` copy. Confirm first with a grep that the key is never stored.
- `plan-card.tsx` keeps the feature id `'gmail'`; it only selects `plans.features.gmail`.
- Fixtures (`state.ts`, `engine.ts`, `dev-state.ts`, `catalog/notifications.ts`): return `sendingAccount`, not `gmail`. The dev-state field may keep its name.
- Remove `gmail` from `AccountStatusPresenter` and `contracts.ts`.
- `checking_gmail` and `connecting_gmail` stay everywhere.

**Steps.**

1. Update the consumers and fixtures.
2. Remove the key.

**Done when.**

- `grep -rn "\.gmail\b" resources/js` matches only translation keys.
- Onboarding completes in the browser and with fixtures.
- `yarn check` and `composer types:check` pass.

**Not in this phase.** Provider choice.

### Phase 9 — Provider list from the registry

Status: PENDING
Role: inertia-frontend · Depends on: 8 · Covers: P6 · Size: M
Origin: P6 "Account and onboarding screens list the available providers from the plugin registry"

**Goal.** Both screens render a provider list rather than a Gmail button.

**Gap -> desired.** One hardcoded connect button -> one row per registered sending provider. Only Gmail appears for now.

**Contract.**

- `AccountStatusPresenter` adds `sendingAccount.providers`: `list<{key, label, description, kind}>`, from registry entries with `email.send`. `kind` is `'oauth'` for now.
- Text comes from new keys, so it is translated: `integrations.{key}.label`, `integrations.{key}.description`. Gmail: label "Gmail" (both); description EN "Send applications from your own Gmail account." PT "Envie candidaturas da sua própria conta do Gmail."
- New `features/account/provider-list.tsx`: one row per provider with icon, label, description and connect button. Assumption: the registry's `icon()` is a Heroicon for Filament, so React uses lucide `Mail` for all providers.
- Used by `EmailAccountCard` when disconnected and `StepEmailAccount` when not connected, with the return target `account` or `onboarding`.
- Connect button: `connect(key, {query: {return}})`, text `account.gmail.connect`.

**Steps.**

1. Add the presenter data, contract and translations.
2. Build the component and use it on both screens. Add `providers` to the fixtures.

**Done when.**

- Account and onboarding show Gmail as a list row, and connecting works.
- `yarn check` and `composer types:check` pass.

**Not in this phase.** SMTP or Microsoft rows.

### Phase 10 — Credential plugin contract and storage

Status: PENDING
Role: laravel-backend · Depends on: 2 · Covers: P7, P11 · Size: M
Origin: P7 "SMTP is a credential integration, not OAuth" · P11 "Credentials in an encrypted column"

**Goal.** The registry supports non-OAuth plugins, and credentials have a safe home.

**Gap -> desired.** Registry and interface are OAuth-only and there is no credentials column -> split contracts and an encrypted column. `SmtpPlugin` exists but is not registered.

**Contract.**

- `App\Contracts\IntegrationPlugin`: `key`, `label`, `description`, `category`, `icon`, `capabilities`, `disconnect`.
- `OAuthIntegrationPlugin extends IntegrationPlugin` keeps only the OAuth methods.
- `CredentialIntegrationPlugin extends IntegrationPlugin` (marker).
- `ConnectedIntegrationRegistry`: `get(): IntegrationPlugin`; new `oauth(string $key): OAuthIntegrationPlugin`, throwing `InvalidArgumentException` for non-OAuth plugins.
- Use `oauth()` in `ConnectedIntegrationOAuthController` connect, reconnect and callback (so `GET /integrations/smtp/connect` is a 404), `CompleteConnectedIntegration` and `ConnectedIntegrationTokenManager`. Disconnect uses `get()`. Check the plugin binding in `AppServiceProvider` still types correctly.
- `App\Integrations\Smtp\SmtpPlugin implements CredentialIntegrationPlugin`: key `smtp`, label "Other email (SMTP)", description "Send applications through your email provider's SMTP server.", category `Email`, icon `heroicon-o-at-symbol`, capabilities `['email.send']`, `disconnect` no-op.
- Edit `database/migrations/2026_09_24_100004_create_connected_integrations_table.php`: `$table->text('credentials')->nullable();` after `refresh_token`.
- `ConnectedIntegration`: fillable and `Hidden` include `credentials`; cast `'credentials' => 'encrypted:array'`; `@property array{username: string, password: string}|null $credentials`.
- `DisconnectConnectedIntegration` and `ConnectedIntegrationTokenManager::markReauthorizationRequired` also set `credentials` to null. Assumption: an auth failure clears the stored password; the user re-enters it.
- Non-secret settings go in `metadata`: `preset`, `host`, `port`, `security`.

**Steps.**

1. Split the contracts and update the registry and callers.
2. Edit the migration and the model.
3. Add `SmtpPlugin`; do not add it to `config('connected-integrations.plugins')`.

**Done when.**

- Report to the owner: **run `php artisan migrate:fresh --seed` now**. Disconnecting fails on a database without the column.
- Gmail connect, callback, reconnect and disconnect are unchanged.
- `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** Sending, the endpoint.

### Phase 11 — `SmtpMessageSender`

Status: PENDING
Role: laravel-backend · Depends on: 4, 10 · Covers: P2, P5, P14 · Size: M
Origin: P14 "`SmtpMessageSender`: Symfony Mailer `EsmtpTransport` built per send" · P5 · P2

**Goal.** Applications can be delivered through a stored SMTP account.

**Gap -> desired.** No SMTP transport -> a sender with safe connection handling and full classification.

**Contract.**

- `App\Outreach\Support\Smtp\SmtpTransportFactory::make(string $host, int $port, string $username, #[\SensitiveParameter] string $password): EsmtpTransport`:
    - `$addresses = $guard->publicAddresses($host)`; empty -> `EmailDeliveryDeferred`.
    - Connect to `$addresses[0]`, bracketed if IPv6: `new EsmtpTransport($ip, $port, $port === 465)`.
    - `setAutoTls(true)`, `setRequireTls(true)`, `setUsername`, `setPassword`.
    - `setAuthenticators([new LoginAuthenticator, new PlainAuthenticator])`.
    - `setLocalDomain` to the host of `config('app.url')`.
    - Stream: `setTimeout(SmtpMessageSender::TIMEOUT_SECONDS)`; `setStreamOptions(['ssl' => ['peer_name' => $host, 'SNI_enabled' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]])`.
- `SmtpTransportFactory::startAuthenticated(EsmtpTransport $transport): void`: calls `start()`, then `executeCommand("EHLO {domain}\r\n", [250])`, and throws `EmailAuthorizationRejected` if the reply has no `AUTH` capability. **Required:** Symfony silently skips login when the server does not advertise AUTH.
- `App\Outreach\Support\SmtpMessageSender implements EmailTransport`, `TIMEOUT_SECONDS = 20`. Builds per send from `metadata` and `credentials`, `startAuthenticated`, then `$transport->send(new RawMessage($email->rawMime), new Envelope(new Address($email->from), [new Address($email->recipient)]))`, `stop()` in `finally`, returns `$email->messageId`.
- Classification:
    - Failure before `send()`: code 530, 534, 535 or 538, or a failed-authentication `TransportException` -> `EmailAuthorizationRejected`; anything else -> `EmailDeliveryDeferred` (D4: nothing left).
    - `UnexpectedResponseException` during `send()`: message matches `/rate|limit|quota|too many|exceed/i` -> `EmailProviderLimitReached`; otherwise code 400–499 -> `EmailDeliveryDeferred`; otherwise code 500–599 -> `EmailRecipientRejected`.
    - Any other failure during `send()` -> `EmailDeliveryUnconfirmed`.
    - Log only `user_id`, `plugin_key`, numeric code and exception class. No `previous`.
- Register `'smtp' => SmtpMessageSender::class` in `connected-integrations.transports`.
- `SendApplicationEmail`:
    - `preCheck` calls `$tokens->accessToken()` only when the plugin is an `OAuthIntegrationPlugin`; for SMTP it requires non-empty `credentials`, else the same "Email account is not connected." failure.
    - `buildMime` uses `$integration->account_name` as display name when `plugin_key === 'smtp'`, otherwise `$user->name` as today.

**Steps.**

1. Add the factory and the sender.
2. Register the transport.
3. Update the job's pre-check and display name.

**Done when.**

- A manual send with a real test mailbox through `php artisan tinker` delivers one message with the expected `Message-ID`, using `OUTREACH_INTERCEPT_TO`.
- A wrong password raises `EmailAuthorizationRejected`, and no exception text contains the password or username.
- `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** Form, endpoint.

### Phase 12 — SMTP connect endpoint and test

Status: PENDING
Role: laravel-backend · Depends on: 3, 11 · Covers: P8, P9, P10, P12, P13 · Size: M
Origin: P8 "Form fields" · P9 "Presets" · P10 "Only ports 465 and 587" · P12 "Connection test" · P13 "from address"

**Goal.** A user's SMTP details are validated and tested before the account becomes Connected.

**Gap -> desired.** Nothing -> `POST /integrations/smtp/connect`.

**Contract.**

- Route, in its own group with `['web', 'auth', 'verified']`, name `integrations.smtp.connect`, `throttle:5,1,smtp-connect`, `SmtpConnectionController::store`. Abort 403 for inactive users, as the OAuth controller does.
- `config/connected-integrations.php`, `'smtp' => ['presets' => […], 'blocked_hosts' => […]]`:

| Preset | Host | Port | Security |
| ------ | ---- | ---- | -------- |
| `yahoo` | `smtp.mail.yahoo.com` | 465 | `tls` |
| `icloud` | `smtp.mail.me.com` | 587 | `starttls` |
| `zoho` | `smtp.zoho.com` | 465 | `tls` |
| `fastmail` | `smtp.fastmail.com` | 465 | `tls` |
| `custom` | user input | user input | user input |

- **Verify each row against the provider's official documentation first** (spec Open points). Planning only confirmed Zoho on an official page; the others came from third-party lists. Other Zoho regions use Custom.
- `blocked_hosts` (Assumption, from spec D3 and P9): `smtp.gmail.com`, `smtp.googlemail.com`, `smtp.office365.com`, `smtp-mail.outlook.com`, `smtp.live.com`.
- `ConnectSmtpRequest` rules:
    - `preset`: in preset keys.
    - `host`: required for custom, max 253, not blocked.
    - `port`: `in:465,587`.
    - `security`: `tls` with 465, `starttls` with 587.
    - `username`: required, email, max 254.
    - `password`: required, string, max 1024.
    - `from_address`: required, email, max 254, domain equal to the username's domain, case-insensitive.
    - `display_name`: required, max 100, no control characters.
    - For a non-custom preset, the server uses the preset's values and ignores posted host, port and security.
- `App\Actions\ConnectSmtpIntegration::run(User $user, SmtpSettings $settings): ConnectedIntegration`:
    - Lock the user row and refuse when another sending plugin is active (Phase 3 rule).
    - Run the test with `SmtpTransportFactory::make` + `startAuthenticated` + `stop`. Nothing is sent.
    - On success, `firstOrNew(['user_id', 'plugin_key' => 'smtp'])`: status Connected, `account_email` = from, `account_name` = display name, `external_account_id` = username, `credentials`, `metadata`, `connected_at`, `last_error_at` null.
    - Resume a `reauthorization_required` pause, as `CompleteConnectedIntegration` does.
- `SmtpSettings` is a `final readonly` DTO; mark the password parameter `#[\SensitiveParameter]`.
- Failure -> 422 `errors.connection`, generic text only. Key `integrations.smtp.failed`: EN "We could not sign in to this server. Check the details and try again." PT "Não conseguimos entrar neste servidor. Confira os dados e tente novamente." Domain mismatch key `integrations.smtp.from_domain`: EN "The from address must use the same domain as your username." PT "O endereço de envio deve usar o mesmo domínio do seu usuário."
- Success -> 200 with the `AccountStatus` payload.
- Confirm `AccountExportController` and Filament screens still expose no credentials.

**Steps.**

1. Verify the presets and add the config.
2. Add the request, DTO, action and controller, then the route.
3. Add the translations.

**Done when.**

- Through tinker on the action: a private host (`127.0.0.1`), port 25, a from address on another domain, a non-email username and a wrong password are all refused, and a real mailbox connects with no message sent.
- Two wrong-password attempts show the same generic text, with no server banner.
- `composer types:check` and `vendor/bin/pint --dirty --format agent` pass.

**Not in this phase.** The form, registering the plugin.

### Phase 13 — SMTP form and registration

Status: PENDING
Role: inertia-frontend · Depends on: 9, 12 · Covers: P6, P8, P15 · Size: M
Origin: P8 "Form fields" · P15 "the UI says so" · P6

**Goal.** Users can choose SMTP on the account and onboarding screens.

**Gap -> desired.** The endpoint has no UI and the plugin is unregistered -> form, list row and Sent-folder notice.

**Contract.**

- Add `SmtpPlugin::class` to `config('connected-integrations.plugins')`.
- `providers[].kind`: `'credential'` for `CredentialIntegrationPlugin`.
- `sendingAccount.smtpDefaults` (only when `providerKey === 'smtp'`): `{preset, host, port, security, fromAddress, displayName}`. Never username or password.
- `features/account/smtp-form.tsx` in a `Modal`, using `Field`, `Input`, `Select`. Choosing a preset fills and locks host, port and security; Custom unlocks them. The password is always empty on open.
- `use-connect-smtp.ts`: `useMutation` with `fromSource({real, fixture})`, `apiFetch` to Wayfinder `integrations.smtp.connect`; 422 errors shown per field. Regenerate routes with `php artisan wayfinder:generate`.
- `provider-list.tsx` opens the form for `kind === 'credential'`. Reconnect on an SMTP account opens the form with `smtpDefaults`.
- Translations, EN / PT (`integrations.smtp.*`):

| Key | EN | PT |
| --- | -- | -- |
| `label` | Other email (SMTP) | Outro e-mail (SMTP) |
| `description` | Use your email provider's SMTP server. | Use o servidor SMTP do seu provedor de e-mail. |
| `preset` | Provider | Provedor |
| `preset_custom` | Custom | Personalizado |
| `host` | SMTP server | Servidor SMTP |
| `port` | Port | Porta |
| `security` | Security | Segurança |
| `username` | Username (your email address) | Usuário (seu endereço de e-mail) |
| `password` | Password | Senha |
| `password_help` | We recommend an app password from your email provider. | Recomendamos uma senha de aplicativo do seu provedor de e-mail. |
| `from_address` | From address | Endereço de envio |
| `display_name` | Display name | Nome de exibição |
| `submit` | Test and connect | Testar e conectar |
| `sent_notice` | Your provider may not copy these emails to your Sent folder. You can always see them under Applications. | Seu provedor pode não copiar estes e-mails para a pasta Enviados. Você sempre pode vê-los em Candidaturas. |

- `sent_notice` is shown on the card when `providerKey === 'smtp'` and in the form.
- Confirm the label "Candidaturas" matches the existing PT navigation before finishing.

**Steps.**

1. Register the plugin and extend the presenter and contract.
2. Build the form and hook, and wire the list.
3. Add the translations and fixture path.

**Done when.**

- In the browser: connecting, seeing the notice, wrong-password error, reconnecting and disconnecting all work on account and onboarding.
- The response and page props never include the password (checked in the network panel).
- `yarn check`, `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** Microsoft.

### Phase 14 — `GraphMessageSender` and CV size check

Status: PENDING
Role: laravel-backend · Depends on: 4 · Covers: P5, P17 · Size: M
Origin: P17 "`GraphMessageSender`" · P5

**Goal.** The Graph transport exists and oversized CVs fail clearly before any send.

**Gap -> desired.** No Graph transport -> sender, classification and size guard. Unreachable until Phase 15 registers the plugin.

**Contract.**

- `App\Outreach\Support\GraphMessageSender implements EmailTransport, App\Outreach\Contracts\LimitsMessageSize`.
- `POST https://graph.microsoft.com/v1.0/me/sendMail`, `Http::withToken($token)->withBody(base64_encode($email->rawMime), 'text/plain')->connectTimeout(10)->timeout(30)->post(…)->throw()`. Token from `ConnectedIntegrationTokenManager`. `202` has no body: return `$email->messageId`.
- `LimitsMessageSize::maxRawMimeBytes(): int`; Graph returns `3_000_000` (base64 gives about 4 MB). A separate interface, so no other transport or test fake changes.
- Classification on `error.code`:
    - 401, or `InvalidAuthenticationToken`, `ErrorAccessDenied`, `AccessDenied` -> `EmailAuthorizationRejected`.
    - 429, or `ApplicationThrottled`, `MailboxConcurrency`, `ErrorExceededMessageLimit`, `ErrorQuotaExceeded` -> `EmailProviderLimitReached`.
    - `ErrorInvalidRecipients`, `ErrorInvalidRecipient` -> `EmailRecipientRejected`.
    - 413 -> new `App\Outreach\Exceptions\EmailMessageTooLarge`. `SendApplicationEmail::handle()` catches it like `EmailRecipientRejected`: Failed, `record(Failed, Delivering)`, `afterTerminal`, no retry.
    - anything else -> `EmailDeliveryDeferred`.
    - `ConnectionException` not caught (`ambiguous`).
- `SendApplicationEmail::preCheck`: after `buildMime`, if the transport implements `LimitsMessageSize` and `strlen($email->rawMime)` exceeds it: Failed, `last_error` "CV is too large for this email provider.", `record(Failed, AttachingFile)`, `afterTerminal`, return null. Nothing sent.
- `ClientErrorMessage`: prefixes "CV is too large for this email provider." and `EmailMessageTooLarge` -> `applications.error.cv_too_large_provider`. EN "Your CV is too large to send from a Microsoft account. Use a PDF under 2 MB." PT "Seu currículo é grande demais para envio por uma conta Microsoft. Use um PDF com menos de 2 MB."
- Register `'microsoft' => GraphMessageSender::class` in `transports`.

**Steps.**

1. Add the interface, exception and sender.
2. Add the pre-check, error mapping and translations.

**Done when.**

- `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.
- The size check is exercised with a stub transport in tinker: an oversized message fails and nothing is sent.

**Not in this phase.** OAuth plugin.

### Phase 15 — `MicrosoftPlugin`, env and card notice

Status: PENDING
Role: laravel-backend · Depends on: 9, 10, 14 · Covers: P16, P17, P18 · Size: M
Origin: P16 "`MicrosoftPlugin`" · P18 "Env" · P17 (card mention)

**Goal.** Users can connect Outlook or Microsoft 365 through OAuth.

**Gap -> desired.** Gmail is the only OAuth plugin -> Microsoft is registered and shown when configured.

**Contract.**

- `App\Integrations\Microsoft\MicrosoftPlugin implements OAuthIntegrationPlugin`, key `microsoft`, label "Outlook / Microsoft 365", category `Email`, icon `heroicon-o-envelope`, capabilities `['email.send']`.
- `League\OAuth2\Client\Provider\GenericProvider`, no new package:
    - `urlAuthorize` `https://login.microsoftonline.com/common/oauth2/v2.0/authorize`.
    - `urlAccessToken` `https://login.microsoftonline.com/common/oauth2/v2.0/token`.
    - `urlResourceOwnerDetails` `https://graph.microsoft.com/v1.0/me`.
    - `scopeSeparator` `' '`, `responseResourceOwnerId` `'id'`.
    - HTTP client timeouts as in `GoogleOAuthPlugin` (`connect_timeout` 3, `timeout` 10).
    - Follow `GoogleOAuthPlugin` for PKCE: pass `code_challenge` and `code_challenge_method=S256`, call `setPkceCode` on exchange. Add `prompt=select_account`.
- `config('connected-integrations.microsoft.scopes')`: `openid email profile offline_access https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read`. `User.Read` is needed for `/me`.
- Identity: `id` -> `externalAccountId`; `mail ?? userPrincipalName` -> `accountEmail`; `displayName` -> `accountName`.
- `validateConnection`: requires `Mail.Send` and a valid email. Compare scopes case-insensitively after removing `https://graph.microsoft.com/`, because Microsoft may return the short form.
- Refresh: `invalid_grant` or `interaction_required` -> `OAuthRefreshTokenRejected`.
- `config/services.php`: `'microsoft' => ['client_id' => env('MICROSOFT_CLIENT_ID'), 'client_secret' => env('MICROSOFT_CLIENT_SECRET'), 'redirect' => env('MICROSOFT_REDIRECT_URI')]`.
- `.env.example`: `MICROSOFT_CLIENT_ID=`, `MICROSOFT_CLIENT_SECRET=`, `MICROSOFT_REDIRECT_URI="${APP_URL}/integrations/oauth/callback"`.
- Assumption: Microsoft is optional. Add `isAvailable(): bool` to `IntegrationPlugin` (true for Gmail and SMTP; Microsoft needs both credentials). The provider list filters on it, and `connect` returns 404 when unavailable. `ProductionCheck` fails only if exactly one of the two Microsoft credentials is set.
- Register the plugin. Translations:
    - `integrations.microsoft.label`: "Outlook / Microsoft 365" (both).
    - `integrations.microsoft.description`: EN "Send applications from your Outlook or Microsoft 365 account." PT "Envie candidaturas da sua conta do Outlook ou Microsoft 365."
    - `integrations.microsoft.cv_notice`: EN "Microsoft accounts can send CVs up to 2 MB." PT "Contas Microsoft podem enviar currículos de até 2 MB."
- `EmailAccountCard` and the provider row show `cv_notice` for `microsoft`.

**Steps.**

1. Add the plugin, config, env and availability check.
2. Register it and add translations and the notice.

**Done when.**

- Without credentials, Microsoft is not listed and Gmail and SMTP are unaffected.
- With a test Azure app: connecting a personal account and sending through `OUTREACH_INTERCEPT_TO` works, and the message appears in Sent Items.
- `yarn check`, `composer types:check` and `php artisan test --compact --filter=SendingDatabaseGuards` pass.

**Not in this phase.** Publisher verification (owner task, `production-hardening` P19).

### Phase 16 — Verification and report

Status: PENDING
Role: laravel-backend · Depends on: all · Covers: all · Size: S
Origin: spec x code gap

**Goal.** Confirm every requirement and hand the owner a manual checklist.

**Gap -> desired.** Implemented phases -> verified feature with a report.

**Contract.**

- Forbidden-pattern greps over the feature's diff: `->poll(`, `wire:poll`, git write commands, Spanish copy.
- Credential grep: `credentials` and `password` appear in no presenter, resource, Filament schema, export or log context.
- No value in `lang/*.json` names Gmail or Google, except `integrations.gmail.*`.
- Walk P1–P19 with evidence, including P19: the plan limit applies to all three providers.

**Steps.**

1. Run `composer ci:check`.
2. Run the greps.
3. Write the requirement walkthrough and owner checklist in the report.

**Done when.**

- `composer ci:check` passes.
- The report includes this owner checklist:
    - Run `php artisan migrate:fresh --seed` if not yet done.
    - Gmail: connect, send, revoke access at Google, confirm pause and notification, reconnect.
    - SMTP: connect with a real app password on each preset you care about; wrong password; send; confirm the Sent notice.
    - Microsoft: register the Azure app, set env, personal and work accounts, send, confirm Sent Items and whether the `Message-ID` is preserved.
    - Microsoft: try a CV of 1.9 MB and one over 2.2 MB; confirm the real Graph limit matches the plan.
    - Gmail: confirm the rejection strings from Phase 4 against a real invalid recipient.
    - Provider limit: confirm auto-resume at the next window (can be simulated in tinker).
    - Review `landing.faq.1.a` and whether the privacy policy should mention stored SMTP passwords and Microsoft.
    - Publisher verification (`production-hardening` P19).

**Not in this phase.** New features or fixes outside the plan.
