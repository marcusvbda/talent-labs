<?php

namespace App\Outreach\Support;

use App\Models\ConnectedIntegration;
use App\Outreach\Contracts\SendsGmailMessages;
use App\Services\ConnectedIntegrationTokenManager;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class GmailApiMessageSender implements SendsGmailMessages
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

    public function send(ConnectedIntegration $integration, string $rawMime): string
    {
        $token = $this->tokens->accessToken($integration->user, $integration->plugin_key);

        $id = Http::withToken($token)
            ->withBody($rawMime, 'message/rfc822')
            ->connectTimeout(10)
            ->timeout(self::TIMEOUT_SECONDS)
            ->post(self::SEND_URL)
            ->throw()
            ->json('id');

        if (! is_string($id) || $id === '') {
            throw new UnexpectedValueException('Gmail did not return a message id.');
        }

        return $id;
    }
}
