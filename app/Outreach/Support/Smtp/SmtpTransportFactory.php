<?php

namespace App\Outreach\Support\Smtp;

use App\Outreach\Exceptions\EmailAuthorizationRejected;
use App\Outreach\Exceptions\EmailDeliveryDeferred;
use App\Outreach\Support\SmtpMessageSender;
use App\Support\PublicNetworkGuard;
use LogicException;
use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\PlainAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * Builds a client SMTP transport that connects to a vetted public address of the host,
 * with TLS required and verified against the original host name.
 */
class SmtpTransportFactory
{
    public function __construct(private PublicNetworkGuard $guard) {}

    /**
     * @throws EmailDeliveryDeferred when the host has no public address.
     */
    public function make(string $host, int $port, string $username, #[\SensitiveParameter] string $password): EsmtpTransport
    {
        $addresses = $this->guard->publicAddresses($host);

        if ($addresses === []) {
            throw new EmailDeliveryDeferred;
        }

        $ip = $addresses[0];

        if (str_contains($ip, ':')) {
            $ip = '['.$ip.']';
        }

        $transport = new EsmtpTransport($ip, $port, $port === 465);
        $transport->setAutoTls(true);
        $transport->setRequireTls(true);
        $transport->setUsername($username);
        $transport->setPassword($password);
        $transport->setAuthenticators([new LoginAuthenticator, new PlainAuthenticator]);
        $transport->setLocalDomain(self::localDomain());
        // The connection is new and used once: skip the NOOP that could restart it (and skip our AUTH check) inside send().
        $transport->setPingThreshold(PHP_INT_MAX);

        $stream = $transport->getStream();

        if (! $stream instanceof SocketStream) {
            throw new LogicException('The SMTP transport has no socket stream.');
        }

        $stream->setTimeout(SmtpMessageSender::TIMEOUT_SECONDS);
        $stream->setStreamOptions([
            'ssl' => [
                'peer_name' => $host,
                'SNI_enabled' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        return $transport;
    }

    /**
     * Connects, then confirms the server advertises AUTH: Symfony silently skips
     * the login when it does not, which would send unauthenticated.
     *
     * @throws EmailAuthorizationRejected when the server offers no authentication.
     */
    public function startAuthenticated(EsmtpTransport $transport): void
    {
        $transport->start();

        $reply = $transport->executeCommand("EHLO {$transport->getLocalDomain()}\r\n", [250]);

        if (preg_match('/^250[ -]AUTH(?:[ =]|$)/mi', $reply) !== 1) {
            throw new EmailAuthorizationRejected;
        }
    }

    private static function localDomain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }
}
