<?php

namespace App\Outreach\Support;

use App\Models\ConnectedIntegration;
use App\Outreach\Contracts\EmailTransport;
use App\Outreach\Data\OutboundEmail;
use App\Outreach\Exceptions\EmailAuthorizationRejected;
use App\Outreach\Exceptions\EmailDeliveryDeferred;
use App\Outreach\Exceptions\EmailProviderLimitReached;
use App\Outreach\Exceptions\EmailRecipientRejected;
use App\Services\ConnectedIntegrationTokenManager;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use UnexpectedValueException;

class GmailApiMessageSender implements EmailTransport
{
    /**
     * Gmail API media upload endpoint (users.messages.send, uploadType=media):
     * the request body is the raw RFC 822 message, content type message/rfc822.
     */
    public const SEND_URL = 'https://gmail.googleapis.com/upload/gmail/v1/users/me/messages/send?uploadType=media';

    /**
     * Must stay below SendApplicationEmail::$timeout.
     */
    public const TIMEOUT_SECONDS = 30;

    public function __construct(private ConnectedIntegrationTokenManager $tokens) {}

    public function send(ConnectedIntegration $integration, OutboundEmail $email): string
    {
        $token = $this->tokens->accessToken($integration->user, $integration->plugin_key);

        // A ConnectionException is not caught: the outcome stays ambiguous for the caller.
        try {
            $id = Http::withToken($token)
                ->withBody($email->rawMime, 'message/rfc822')
                ->connectTimeout(10)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::SEND_URL)
                ->throw()
                ->json('id');
        } catch (RequestException $exception) {
            throw self::classify($exception);
        }

        if (! is_string($id) || $id === '') {
            throw new UnexpectedValueException('Gmail did not return a message id.');
        }

        return $id;
    }

    /**
     * Maps a Gmail HTTP error to a shared outcome. Gmail's raw error never leaves this class.
     */
    private static function classify(RequestException $exception): RuntimeException
    {
        $response = $exception->response;
        $status = $response->status();
        $json = $response->json();
        $reason = is_array($json) ? data_get($json, 'error.errors.0.reason') : null;
        $errorStatus = is_array($json) ? data_get($json, 'error.status') : null;
        $message = is_array($json) ? (string) data_get($json, 'error.message', '') : '';

        if ($status === 401
            || in_array($reason, ['authError', 'insufficientPermissions'], true)
            || $errorStatus === 'UNAUTHENTICATED') {
            return new EmailAuthorizationRejected;
        }

        if ($status === 429
            || ($status === 403 && in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded', 'dailyLimitExceeded', 'quotaExceeded'], true))) {
            return new EmailProviderLimitReached;
        }

        // Assumed Gmail wording for a refused recipient; to be verified against a real response.
        if ($status === 400 && Str::contains($message, ['Invalid To header', 'Recipient address required'])) {
            return new EmailRecipientRejected;
        }

        return new EmailDeliveryDeferred;
    }
}
