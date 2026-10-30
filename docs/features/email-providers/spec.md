# Email Providers

> Body: current behaviour, checked against the code on 2026-10-30 after the
> plan's phases 1–16. Run `/update-feature-spec email-providers` to reconcile
> it again after manual changes.
>
> Depends on: `production-hardening` P7 (outbound-connection guard) for SMTP.

## 1. What it is

The account a client sends applications from. The client chooses between
OAuth providers (Gmail, Microsoft) and SMTP. One sending account per user.

## 2. Current behaviour (2026-10-30)

### Plugins and storage

- **Contracts:** `App\Contracts\IntegrationPlugin` (key, label, description,
  category, icon, capabilities, `isAvailable()`, disconnect), extended by
  `OAuthIntegrationPlugin` (redirectUri, authorizationUrl with state + PKCE
  verifier, exchangeAuthorizationCode, refreshAccessToken, validateConnection,
  afterConnected) and by the marker `CredentialIntegrationPlugin`. The
  registry (`config/connected-integrations.php`, `plugins`) offers `get()`,
  `oauth()` (throws for non-OAuth plugins, so `GET /integrations/smtp/connect`
  is a 404) and `sendingPluginKeys()` (plugins with capability `email.send`,
  not filtered by availability).
- **Plugins:** `gmail` (OAuth, `GmailPlugin extends GoogleOAuthPlugin`, scopes
  `openid email profile https://www.googleapis.com/auth/gmail.send`, Gmail
  stays OAuth only), `microsoft` (OAuth) and `smtp` (credentials). All have
  capability `email.send`. Microsoft is optional: `isAvailable()` is true only
  when both `MICROSOFT_CLIENT_ID` and `MICROSOFT_CLIENT_SECRET` are set; it is
  then listed and connectable, otherwise `connect` is a 404 and it is not
  listed. `ProductionCheck` fails only when exactly one of the two is set.
- **Storage:** `ConnectedIntegration` (`plugin_key`, `status`,
  `external_account_id`, `account_email`, `account_name`, `access_token` and
  `refresh_token` cast `encrypted`, `credentials` cast `encrypted:array`
  (`{username, password}`), `granted_scopes`, `metadata`, `expires_at`, …).
  `credentials` is hidden on the model; disconnecting or marking
  reauthorization required clears it. SMTP's non-secret settings (`preset`,
  `host`, `port`, `security`) live in `metadata`.
- **One sending account:** `User::sendingIntegration()` is the user's
  integration with capability `email.send` that is not `Disconnected` (a
  `reauthorization_required` row is still returned). Connecting another
  provider while one is active is refused at the start (OAuth `connect`), on
  completion (`CompleteConnectedIntegration`, under a lock on the user row) and
  for SMTP (`ConnectSmtpIntegration`, re-checked under the same lock);
  the user sees "Disconnect your current email account before connecting
  another."
- **Routes:** `integrations/{plugin}/connect|reconnect` (GET, OAuth only),
  `integrations/{plugin}` (DELETE, any plugin), `integrations/oauth/callback`
  (`throttle:20,1`) via `ConnectedIntegrationOAuthController`, and
  `POST integrations/smtp/connect` (`throttle:5,1,smtp-connect`, verified and
  active users) via `SmtpConnectionController`.

### Sending

- `EmailTransport::send(ConnectedIntegration, OutboundEmail): string`, where
  `OutboundEmail` holds the raw MIME, `Message-ID`, from address and
  recipient. `EmailTransportResolver` picks the transport from
  `config('connected-integrations.transports')` by `plugin_key`: `gmail` →
  `GmailApiMessageSender`, `smtp` → `SmtpMessageSender`, `microsoft` →
  `GraphMessageSender`. The transport is resolved in the job's pre-check,
  before the attempt starts, so a broken config leaves the row queued.
- Every MIME message carries a generated `Message-ID`
  (`<uuid@{sender's domain}>`). Transports return the provider's id, or that
  `Message-ID` when the provider has none; it is stored where the Gmail id was.
- A test-mode recipient (`OUTREACH_INTERCEPT_TO`) is the envelope recipient, so
  test mode never reaches the company.
- The plan's daily limit applies to every provider (checked in
  `SendApplicationEmail::beginAttempt()`).
- **Error classification** is done per transport with shared exceptions
  (`EmailAuthorizationRejected`, `EmailProviderLimitReached`,
  `EmailRecipientRejected`, `EmailDeliveryDeferred`, `EmailDeliveryUnconfirmed`,
  `EmailMessageTooLarge`), and the job reacts by type:
    - authorization or consent revoked → `reauthorization_required` (pause and
      notification `EmailReauthorizationRequired`);
    - provider sending or rate limit → the application is **not** failed: it
      goes back to `queued` with no slot, sending is paused with
      `SendingPauseReason::ProviderLimit`, the user gets
      `SendingProviderLimitReached`, and `ResumeSendingAfterProviderLimit`
      resumes at the next day's window (only if the pause is unchanged and the
      account is still `Connected`; manual Resume also works);
    - recipient rejected or message too large → application failed, no retry;
    - provably not delivered (5xx, SMTP connect, TLS, AUTH) → existing
      retry/backoff;
    - timeout or network failure after delivery may have started → `ambiguous`,
      never retried, so a message cannot go out twice.
- **Gmail:** POST of the raw MIME to the Gmail upload endpoint, 30 s timeout;
  401/`authError`/`insufficientPermissions` → authorization, 429 or limit
  reasons → provider limit, invalid-recipient 400s → recipient rejected,
  anything else → deferred.
- **SMTP (`SmtpMessageSender`):** a Symfony Mailer `EsmtpTransport` is built
  per send from the stored credentials (`SmtpTransportFactory`): host resolved
  through `PublicNetworkGuard` and connected by validated IP, TLS required
  (implicit on 465, STARTTLS on 587) with peer name = host and peer
  verification, LOGIN/PLAIN, and an explicit check that the server advertises
  `AUTH`. The raw MIME is sent as a `RawMessage` with an explicit `Envelope`;
  the timeout is 20 s (below `SendApplicationEmail::$timeout`). Failures before
  `send()` are authorization (530/534/535/538, failed authentication) or
  deferred; during `send()` a limit-looking reply (whole words, not mailbox
  statuses 5.2.x) is a provider limit, 4xx is deferred, 5xx is a recipient
  rejection, and any other failure is unconfirmed. The password never appears
  in logs (only user id, plugin key, numeric code, exception class) or
  exception messages, and no Symfony exception is chained. The sender display
  name is the account's display name.
- **Microsoft (`GraphMessageSender`):** `POST
https://graph.microsoft.com/v1.0/me/sendMail` with the base64-encoded raw
  MIME as `text/plain`; `202` has no body, so the `Message-ID` is returned
  and Graph saves to Sent Items. Classification by `error.code` (401 and
  access errors → authorization, 429 and throttle/quota codes → provider
  limit, invalid recipients → recipient rejected, 413 → too large, else
  deferred). Requests are capped at about 4 MB, so the transport implements
  `LimitsMessageSize` (3,000,000 raw bytes): a larger message fails in the
  pre-check before anything is sent ("CV is too large for this email
  provider.", shown to the client as a Microsoft-specific message asking for a
  PDF under 2 MB). The 5 MB CV limit stays for the other providers. No upload
  sessions (they need more than `Mail.Send`).

### SMTP connection

- **Form:** preset, host, port, security, username (an email address), password
  (help text recommends an app password), from address, display name. Presets
  fill and lock host, port and security: Yahoo `smtp.mail.yahoo.com` 465 TLS,
  iCloud `smtp.mail.me.com` 587 STARTTLS, Zoho `smtp.zoho.com` 465 TLS,
  Fastmail `smtp.fastmail.com` 465 TLS (checked against each provider's
  documentation on 2026-10-30; Zoho paid-organization accounts use
  `smtppro.zoho.com`, i.e. Custom). **Custom** unlocks them. Gmail and
  Microsoft hosts (`smtp.gmail.com`, `smtp.googlemail.com`,
  `smtp.office365.com`, `smtp-mail.outlook.com`, `smtp.live.com`) are blocked;
  those providers use OAuth.
- **Rules:** only ports 465 (implicit TLS) and 587 (STARTTLS), no plaintext or
  port 25; for a preset the server ignores the posted host, port and security;
  the from address must share the username's domain (so non-email logins such
  as `apikey` and custom-domain aliases are rejected); display name max 100
  with no control characters.
- **Connection test** before the account becomes Connected: the host passes
  `PublicNetworkGuard`, the connection goes to the validated IP, TLS, EHLO and
  AUTH run, and nothing is sent. The network test runs outside the database
  transaction. Any failure returns one generic 422 message ("We could not sign
  in to this server…") with no server banner, username or password. A
  successful connect resumes a `reauthorization_required` pause. There is no
  "send a test email to myself" and no "Bcc me" option.
- **Secrecy:** the password is never returned to the browser, logged, exported
  or shown in admin; `smtpDefaults` in the account status (preset, host, port,
  security, from address, display name) never carries the username or
  password, and the form is empty on every open. The form's mutation is
  evicted from the client cache on unmount (`gcTime: 0`).
- SMTP providers usually do not copy sent mail to the Sent folder; the account
  card and the form say so and point to Applications.

### Screens and wording

- The account and onboarding screens list the available providers from the
  registry (`sendingAccount.providers[]` with `key` and `kind` `oauth` or
  `credential`; text from `integrations.{key}.label|description`). An OAuth
  provider connects through its connect URL; a credential provider opens the
  SMTP form (also used for reconnect, with `smtpDefaults`). The Microsoft row
  and the account card mention the 2 MB CV limit. The contract exposes
  `sendingAccount` (state, accountEmail, providerKey, providers,
  smtpDefaults); the old `gmail` key is gone and the onboarding step key is
  `email`.
- Wording is provider-neutral in EN and PT ("Connect an email account",
  "Email reauthorization", the notification `email_reauthorization_required`,
  sub-step labels, FAQ), except `integrations.gmail.*`. Stored values
  (`checking_gmail`, `connecting_gmail`, `reauthorization_required`,
  `gmail_reauthorization_required` notification rows) are unchanged, so old
  rows still render. Translation keys and parameters keep their names
  (`account.gmail.*`, `:gmail`).

## Out of scope

- Reading the inbox or replies (would need read scopes; Gmail read scopes are
  restricted and require a security assessment).
- IMAP.
- Publisher verification of the Microsoft app (owner task,
  `production-hardening` P19).

## Decisions

- **D1.** (2026-10-30) Clients choose their sending provider: OAuth (Gmail,
  Microsoft) or SMTP.
- **D2.** Order: provider-agnostic transport → SMTP → Microsoft OAuth.
- **D3.** Gmail stays OAuth only.
- **D4.** (2026-10-30) One sending account per user (was O1).
- **D5.** (2026-10-30) No "send a test email to myself" (was O2).
- **D6.** (2026-10-30) No "Bcc me on every application" (was O3).
- **D7.** (2026-10-30) Replacing `SendsGmailMessages` may update the fake
  sender's interface, signature and binding in
  `tests/Feature/Outreach/SendingDatabaseGuardsTest.php`; its assertions stay
  unchanged.
