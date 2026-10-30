<?php

namespace App\Outreach\Support;

use App\Models\ConnectedIntegration;
use App\Outreach\Contracts\EmailTransport;
use App\Outreach\Data\OutboundEmail;
use App\Outreach\Exceptions\EmailAuthorizationRejected;
use App\Outreach\Exceptions\EmailDeliveryDeferred;
use App\Outreach\Exceptions\EmailDeliveryUnconfirmed;
use App\Outreach\Exceptions\EmailProviderLimitReached;
use App\Outreach\Exceptions\EmailRecipientRejected;
use App\Outreach\Support\Smtp\SmtpTransportFactory;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Throwable;

/**
 * Sends through the account's own SMTP server, with a transport built per send.
 *
 * Symfony Mailer exceptions never leave this class: their messages can carry the
 * username and server text. Only fixed-message exceptions are thrown.
 */
class SmtpMessageSender implements EmailTransport
{
    /**
     * Must stay below SendApplicationEmail::$timeout.
     */
    public const TIMEOUT_SECONDS = 20;

    /**
     * SMTP reply codes that mean the credentials or the authentication were refused.
     */
    private const array AUTHORIZATION_CODES = [530, 534, 535, 538];

    public function __construct(private SmtpTransportFactory $factory) {}

    public function send(ConnectedIntegration $integration, OutboundEmail $email): string
    {
        $transport = null;

        try {
            // Before send(): nothing has left the server yet.
            try {
                $transport = $this->connect($integration);
                $envelope = new Envelope(new Address($email->from), [new Address($email->recipient)]);
            } catch (Throwable $exception) {
                throw $this->classifyBeforeSend($integration, $exception);
            }

            try {
                $transport->send(new RawMessage($email->rawMime), $envelope);
            } catch (Throwable $exception) {
                throw $this->classifyDuringSend($integration, $exception);
            }
        } finally {
            $transport?->stop();
        }

        return $email->messageId;
    }

    private function connect(ConnectedIntegration $integration): EsmtpTransport
    {
        $metadata = $integration->metadata ?? [];
        $host = $metadata['host'] ?? null;
        $port = $metadata['port'] ?? null;
        $username = $integration->credentials['username'] ?? null;
        $password = $integration->credentials['password'] ?? null;

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            throw new EmailAuthorizationRejected;
        }

        if (! is_string($host) || $host === '' || ! is_numeric($port)) {
            throw new EmailDeliveryDeferred;
        }

        $transport = $this->factory->make($host, (int) $port, $username, $password);

        try {
            $this->factory->startAuthenticated($transport);
        } catch (Throwable $exception) {
            $transport->stop();

            throw $exception;
        }

        return $transport;
    }

    private function classifyBeforeSend(ConnectedIntegration $integration, Throwable $exception): RuntimeException
    {
        $this->log($integration, $exception);

        if ($exception instanceof EmailAuthorizationRejected || $exception instanceof EmailDeliveryDeferred) {
            return $exception;
        }

        if ($exception instanceof TransportException
            && (in_array($exception->getCode(), self::AUTHORIZATION_CODES, true) || self::isFailedAuthentication($exception))) {
            return new EmailAuthorizationRejected;
        }

        return new EmailDeliveryDeferred;
    }

    private function classifyDuringSend(ConnectedIntegration $integration, Throwable $exception): RuntimeException
    {
        $this->log($integration, $exception);

        if (! $exception instanceof UnexpectedResponseException) {
            return new EmailDeliveryUnconfirmed;
        }

        $code = (int) $exception->getCode();

        // Whole words only, and never the recipient-side mailbox statuses (5.2.x, e.g. "mailbox over quota").
        $message = $exception->getMessage();

        if (preg_match('/\b(rate|limit(s|ed)?|quota|too many|exceed(s|ed)?)\b/i', $message) === 1
            && preg_match('/\b5\.2\.\d\b/', $message) !== 1) {
            return new EmailProviderLimitReached;
        }

        if ($code >= 400 && $code <= 499) {
            return new EmailDeliveryDeferred;
        }

        if ($code >= 500 && $code <= 599) {
            return new EmailRecipientRejected;
        }

        return new EmailDeliveryUnconfirmed;
    }

    /**
     * Symfony's own authentication failures (EsmtpTransport::handleAuth), which may carry
     * a code outside AUTHORIZATION_CODES.
     */
    private static function isFailedAuthentication(TransportException $exception): bool
    {
        return ! $exception instanceof UnexpectedResponseException
            && (str_starts_with($exception->getMessage(), 'Failed to authenticate on SMTP server')
                || str_starts_with($exception->getMessage(), 'Failed to find an authenticator supported by the SMTP server'));
    }

    /**
     * Never logs the exception itself: its message can contain the username and server text.
     */
    private function log(ConnectedIntegration $integration, Throwable $exception): void
    {
        Log::warning('SMTP send failed.', [
            'user_id' => $integration->user_id,
            'plugin_key' => $integration->plugin_key,
            'code' => (int) $exception->getCode(),
            'exception' => $exception::class,
        ]);
    }
}
