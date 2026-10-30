# Email Providers

> Body: current behaviour, read from the code on 2026-10-30 (not produced by
> `/update-feature-spec`; run it first if you want it reconciled).
> `## Pending changes` holds what the owner decided on 2026-10-30. Items
> marked **O** are open decisions: `/plan-feature` asks the owner, never
> guesses.
>
> Depends on: `production-hardening` P7 (outbound-connection guard) for SMTP.
> Order inside this feature: abstraction → SMTP → Microsoft.

## 1. What it is

The account a client sends applications from. Today only Gmail (OAuth). The
client should choose between OAuth providers (Gmail, Microsoft) and SMTP.

## 2. Current behaviour (2026-10-30)

- **Plugin contract:** `App\Contracts\OAuthIntegrationPlugin` — key, label,
  description, category, icon, capabilities, redirectUri, authorizationUrl
  (state + PKCE verifier), exchangeAuthorizationCode, refreshAccessToken,
  validateConnection, afterConnected, disconnect. Registry from
  `config/connected-integrations.php` (`plugins => [GmailPlugin::class]`).
- **Gmail:** `GmailPlugin extends GoogleOAuthPlugin`, key `gmail`,
  capability `email.send`, scopes `openid email profile
https://www.googleapis.com/auth/gmail.send`; `validateConnection` requires
  the send scope and a valid account email.
- **Storage:** `ConnectedIntegration` (`plugin_key`, `status`,
  `external_account_id`, `account_email`, `account_name`, `access_token` and
  `refresh_token` cast `encrypted`, `granted_scopes`, `metadata`,
  `expires_at`, …). Tokens via `ConnectedIntegrationTokenManager`.
- **Routes:** `integrations/{plugin}/connect|reconnect` (GET),
  `integrations/{plugin}` (DELETE), `integrations/oauth/callback`
  (`throttle:20,1`); `ConnectedIntegrationOAuthController`.
- **Sending:** interface `App\Outreach\Contracts\SendsGmailMessages`
  (`send(ConnectedIntegration, string $rawMime): string`) bound to
  `GmailApiMessageSender` in `AppServiceProvider` (POST raw MIME to the
  Gmail upload endpoint, returns the Gmail id, 30 s timeout).
- **Gmail-specific coupling:** `User::gmailIntegration()` (hasOne where
  `plugin_key = 'gmail'`); `CanSendApplications` ("Connect your Gmail
  account." / "Reconnect your Gmail account."); `SendApplicationEmail`
  sub-steps `SendSubStep::CheckingGmail` (`checking_gmail`) and
  `ConnectingGmail` (`connecting_gmail`); `SendingPauseReason::ReauthorizationRequired`
  label "Gmail reauthorization"; notification `GmailReauthorizationRequired`;
  `AccountStatusPresenter`, `SendingController`, `UsersTable`; translations
  `account.gmail.*`; `ConnectedIntegrationOAuthController::FALLBACK_LABEL =
'Gmail'`; landing FAQ "We only ask Google for permission to send".

## 3. Open points

- SMTP preset hosts/ports in P9 come from general knowledge; check each
  against the provider's current documentation when implementing.

## Pending changes

### Abstraction (first)

- **P1.** Replace `SendsGmailMessages` with a provider-agnostic
  `EmailTransport` (`send(ConnectedIntegration $integration, string $rawMime):
string`) and a resolver by `plugin_key`: `gmail` → `GmailApiMessageSender`,
  `microsoft` → `GraphMessageSender`, `smtp` → `SmtpMessageSender`. The
  `email.send` capability stays the marker of a sending account.
- **P2.** Every MIME message the app builds carries a generated `Message-ID`
  (`<uuid@{sender's domain}>`). Transports return the provider's id when they
  have one, otherwise that `Message-ID`; stored where the Gmail id is stored
  today.
- **P3.** `User::sendingIntegration()` replaces `gmailIntegration()`: the
  user's connected integration with capability `email.send`. v1: one sending
  account per user; connecting another provider requires disconnecting the
  current one. **O1.**
- **P4.** Provider-neutral wording everywhere listed in 2 ("Connect an email
  account", "Email reauthorization", etc.), en and pt. Stored enum **values**
  (`checking_gmail`, `connecting_gmail`, `reauthorization_required`) do not
  change — only labels/translations — so existing rows stay valid. The
  notification class becomes provider-neutral.
- **P5.** Error classification per transport, shared by all:
    - auth/consent revoked → `reauthorization_required` (pause + notify, as
      Gmail today);
    - provider sending/rate limit → sending paused until the next day, the
      application is **not** failed (new pause reason, e.g. `provider_limit`);
    - recipient rejected → application failed (as today);
    - timeout/5xx/network → existing retry/backoff.
- **P6.** Account and onboarding screens list the available providers from
  the plugin registry (label, icon, description) instead of a Gmail button.

### SMTP (client's choice)

- **P7.** SMTP is a credential integration, not OAuth: a separate contract
  (e.g. `CredentialIntegrationPlugin`) or a dedicated controller + form;
  stored as a `ConnectedIntegration` with `plugin_key = smtp`.
- **P8.** Form fields: preset, host, port, security, username, password ("app
  password" recommended in the help text), from address, display name.
- **P9.** Presets fill host/port/security; **Custom** allows any public host.
  Initial presets: Yahoo, iCloud, Zoho, Fastmail, Custom (verify values, see
  Open points). Gmail and Outlook are **not** SMTP presets — they use OAuth
  (Microsoft is retiring SMTP basic auth for Exchange Online).
- **P10.** Only ports 465 (implicit TLS) and 587 (STARTTLS required). No
  plaintext, no port 25.
- **P11.** Credentials in an encrypted column (or encrypted cast) on
  `ConnectedIntegration`. The password is never returned to the browser,
  never logged, never in the account export or admin screens, never in
  exception context.
- **P12.** Connection test before the account becomes Connected: host passes
  `PublicNetworkGuard` (`production-hardening` P7), connect to the validated
  IP with TLS peer name = host, EHLO + AUTH, no message sent. Endpoint
  `throttle:5,1` per user. Failure messages are generic (no server banner
  echoed back). **O2:** optional "send a test email to myself".
- **P13.** The from address must be the authenticated mailbox (or what the
  server accepts for that login); reject a from address on another domain.
- **P14.** `SmtpMessageSender`: Symfony Mailer `EsmtpTransport` built per send
  from the stored credentials, sends the raw MIME as a `RawMessage` with an
  explicit `Envelope`; connect/read timeouts below
  `SendApplicationEmail::$timeout`; returns the `Message-ID` (P2).
- **P15.** SMTP providers usually do not copy sent mail to the Sent folder:
  the UI says so. **O3:** "Bcc me on every application".

### Microsoft (OAuth)

- **P16.** `MicrosoftPlugin` (key `microsoft`, label "Outlook / Microsoft
  365"): Microsoft identity platform v2, tenant `common` (personal and work
  accounts), PKCE, scopes `openid email profile offline_access
https://graph.microsoft.com/Mail.Send` (plus `User.Read` if needed for the
  account identity). Build on `league/oauth2-client`, already installed; any
  new package needs the owner's approval.
- **P17.** `GraphMessageSender`: `POST
https://graph.microsoft.com/v1.0/me/sendMail` with the base64-encoded raw
  MIME and `Content-Type: text/plain`. `202 Accepted` has no body → return the
  `Message-ID` (P2). Graph saves to Sent Items.
- **P18.** Env (append to `.env.example`): `MICROSOFT_CLIENT_ID`,
  `MICROSOFT_CLIENT_SECRET`, `MICROSOFT_REDIRECT_URI` =
  `{APP_URL}/integrations/oauth/callback`. Publisher verification is an owner
  task (`production-hardening` P19).

### Shared limits

- **P19.** The plan's daily limit still applies to every provider; a lower
  provider limit is handled by P5.

## Out of scope

- Reading the inbox or replies (would need read scopes; Gmail read scopes are
  restricted and require a security assessment).
- IMAP.

## Decisions

- **D1.** (2026-10-30) Clients choose their sending provider: OAuth (Gmail,
  Microsoft) or SMTP.
- **D2.** Order: provider-agnostic transport → SMTP → Microsoft OAuth.
- **D3.** Gmail stays OAuth only.

## Open decisions

- **O1.** One sending account per user (v1) or several with an "active" one.
- **O2.** "Send a test email to myself" after the SMTP connection test.
- **O3.** "Bcc me on every application" option for SMTP.
