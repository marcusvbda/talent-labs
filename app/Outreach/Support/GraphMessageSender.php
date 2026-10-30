<?php

namespace App\Outreach\Support;

use App\Models\ConnectedIntegration;
use App\Outreach\Contracts\EmailTransport;
use App\Outreach\Contracts\LimitsMessageSize;
use App\Outreach\Data\OutboundEmail;
use App\Outreach\Exceptions\EmailAuthorizationRejected;
use App\Outreach\Exceptions\EmailDeliveryDeferred;
use App\Outreach\Exceptions\EmailMessageTooLarge;
use App\Outreach\Exceptions\EmailProviderLimitReached;
use App\Outreach\Exceptions\EmailRecipientRejected;
use App\Services\ConnectedIntegrationTokenManager;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GraphMessageSender implements EmailTransport, LimitsMessageSize
{
    /**
     * Microsoft Graph sendMail: a base64 MIME body (text/plain) sends the raw message as is.
     */
    public const SEND_URL = 'https://graph.microsoft.com/v1.0/me/sendMail';

    /**
     * Must stay below SendApplicationEmail::$timeout.
     */
    public const TIMEOUT_SECONDS = 30;

    /**
     * Base64 grows the body by a third: about 4 MB on the wire, under Graph's request limit.
     */
    public const MAX_RAW_MIME_BYTES = 3_000_000;

    public function __construct(private ConnectedIntegrationTokenManager $tokens) {}

    public function send(ConnectedIntegration $integration, OutboundEmail $email): string
    {
        $token = $this->tokens->accessToken($integration->user, $integration->plugin_key);

        // A ConnectionException is not caught: the outcome stays ambiguous for the caller.
        try {
            Http::withToken($token)
                ->withBody(base64_encode($email->rawMime), 'text/plain')
                ->connectTimeout(10)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::SEND_URL)
                ->throw();
        } catch (RequestException $exception) {
            throw self::classify($exception);
        }

        // 202 Accepted has no body: the Message-ID header is the only id.
        return $email->messageId;
    }

    public function maxRawMimeBytes(): int
    {
        return self::MAX_RAW_MIME_BYTES;
    }

    /**
     * Maps a Graph HTTP error to a shared outcome. Graph's raw error never leaves this class.
     */
    private static function classify(RequestException $exception): RuntimeException
    {
        $response = $exception->response;
        $status = $response->status();
        $json = $response->json();
        $code = is_array($json) ? data_get($json, 'error.code') : null;
        $code = is_string($code) ? $code : null;

        if ($status === 401 || in_array($code, ['InvalidAuthenticationToken', 'ErrorAccessDenied', 'AccessDenied'], true)) {
            return new EmailAuthorizationRejected;
        }

        if ($status === 429 || in_array($code, ['ApplicationThrottled', 'MailboxConcurrency', 'ErrorExceededMessageLimit', 'ErrorQuotaExceeded'], true)) {
            return new EmailProviderLimitReached;
        }

        if (in_array($code, ['ErrorInvalidRecipients', 'ErrorInvalidRecipient'], true)) {
            return new EmailRecipientRejected;
        }

        if ($status === 413) {
            return new EmailMessageTooLarge;
        }

        return new EmailDeliveryDeferred;
    }
}
