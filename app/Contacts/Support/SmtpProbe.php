<?php

namespace App\Contacts\Support;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Handshake-only SMTP probe: greets the server, declares a sender and asks about
 * recipients, then quits. It never transmits a message body.
 */
class SmtpProbe
{
    /**
     * The only SMTP verbs this client is allowed to write to the socket.
     */
    private const array ALLOWED_VERBS = ['EHLO', 'HELO', 'MAIL FROM', 'RCPT TO', 'QUIT'];

    private const int SESSION_DEADLINE_SECONDS = 45;

    private float $deadline = 0.0;

    /**
     * @param  list<string>  $recipients
     */
    public function probe(string $host, array $recipients): SmtpProbeResult
    {
        $timeout = max(1, (int) config('talent.contacts.smtp_timeout', 5));
        $helo = trim((string) config('talent.contacts.smtp_helo', 'localhost'));
        $helo = $helo !== '' ? $helo : 'localhost';
        $from = trim((string) config('talent.contacts.smtp_mail_from', ''));

        /** @var array<string, int|null> $codes */
        $codes = array_fill_keys($recipients, null);

        $this->deadline = microtime(true) + self::SESSION_DEADLINE_SECONDS;

        set_error_handler(static fn (): bool => true);

        $socket = null;

        try {
            $socket = stream_socket_client("tcp://{$host}:25", $errno, $errstr, $timeout);
        } catch (Throwable $e) {
            $errstr = $e->getMessage();
        } finally {
            restore_error_handler();
        }

        if (! is_resource($socket)) {
            return new SmtpProbeResult(false, false, 'connect failed: '.trim((string) ($errstr ?? '')), $codes);
        }

        $sessionOk = false;
        $failure = null;

        set_error_handler(static fn (): bool => true);

        try {
            stream_set_timeout($socket, $timeout);

            $greeting = $this->readReply($socket);

            if ($greeting !== 220) {
                return new SmtpProbeResult(true, false, 'greeting '.($greeting ?? 'timeout'), $codes);
            }

            $hello = $this->command($socket, 'EHLO', $helo);

            if ($hello !== null && $hello >= 500) {
                $hello = $this->command($socket, 'HELO', $helo);
            }

            if (! $this->isPositive($hello)) {
                $this->quit($socket);

                return new SmtpProbeResult(true, false, 'hello '.($hello ?? 'timeout'), $codes);
            }

            $mailFrom = $this->command($socket, 'MAIL FROM', "<{$from}>");

            if (! $this->isPositive($mailFrom)) {
                $this->quit($socket);

                return new SmtpProbeResult(true, false, 'mail from '.($mailFrom ?? 'timeout'), $codes);
            }

            $sessionOk = true;

            foreach ($recipients as $recipient) {
                if ($this->deadlinePassed()) {
                    $failure = 'session deadline reached';

                    break;
                }

                $codes[$recipient] = $this->command($socket, 'RCPT TO', "<{$recipient}>");
            }

            $this->quit($socket);

            return new SmtpProbeResult(true, $sessionOk, $failure, $codes);
        } catch (Throwable $e) {
            return new SmtpProbeResult(true, $sessionOk, 'socket error: '.$e->getMessage(), $codes);
        } finally {
            restore_error_handler();
            fclose($socket);
        }
    }

    /**
     * Single write path to the server. Refuses any verb outside the allow-list.
     *
     * @param  resource  $socket
     */
    private function command($socket, string $verb, string $argument = ''): ?int
    {
        if (! in_array($verb, self::ALLOWED_VERBS, true)) {
            throw new InvalidArgumentException("SMTP verb [{$verb}] is not allowed.");
        }

        if (str_contains($argument, "\r") || str_contains($argument, "\n")) {
            throw new InvalidArgumentException('SMTP argument must not contain line breaks.');
        }

        if ($this->deadlinePassed()) {
            return null;
        }

        $line = match ($verb) {
            'MAIL FROM', 'RCPT TO' => "{$verb}:{$argument}",
            default => $argument === '' ? $verb : "{$verb} {$argument}",
        };

        if (fwrite($socket, $line."\r\n") === false) {
            throw new RuntimeException("write failed for {$verb}");
        }

        return $this->readReply($socket);
    }

    /**
     * @param  resource  $socket
     */
    private function quit($socket): void
    {
        try {
            $this->command($socket, 'QUIT');
        } catch (Throwable) {
            // The session is over either way.
        }
    }

    /**
     * Reads a (possibly multi-line) reply and returns its code, or null on timeout/EOF.
     *
     * @param  resource  $socket
     */
    private function readReply($socket): ?int
    {
        while (! $this->deadlinePassed()) {
            $line = fgets($socket, 1024);

            if ($line === false) {
                return null;
            }

            if (strlen($line) < 3 || ! ctype_digit(substr($line, 0, 3))) {
                return null;
            }

            if (($line[3] ?? ' ') === '-') {
                continue;
            }

            return (int) substr($line, 0, 3);
        }

        return null;
    }

    private function isPositive(?int $code): bool
    {
        return $code !== null && $code >= 200 && $code < 300;
    }

    private function deadlinePassed(): bool
    {
        return microtime(true) >= $this->deadline;
    }
}
