<?php

namespace App\Outreach\Support;

use Illuminate\Support\Str;

/**
 * Maps an application's internal `last_error` to a translated sentence a client may see.
 * The raw error text never leaves the server.
 */
final class ClientErrorMessage
{
    /**
     * Checked in order; the first prefix that matches wins.
     *
     * @var array<string, string>
     */
    private const PREFIXES = [
        'Gmail is not connected.' => 'applications.error.gmail',
        'CV file is missing.' => 'applications.error.cv',
        'Recipient is not a verified contact' => 'applications.error.recipient',
        'Company is no longer verified' => 'applications.error.company',
        'Client account is not active.' => 'applications.error.account',
        'Worker stopped mid-send.' => 'applications.error.unconfirmed',
    ];

    /**
     * @var list<string>
     */
    private const TRANSPORT_ERRORS = ['ConnectionException', 'RequestException', 'TransportException'];

    public static function for(?string $lastError): ?string
    {
        if ($lastError === null || trim($lastError) === '') {
            return null;
        }

        foreach (self::PREFIXES as $prefix => $key) {
            if (str_starts_with($lastError, $prefix)) {
                return self::translate($key);
            }
        }

        if (Str::contains($lastError, self::TRANSPORT_ERRORS)) {
            return self::translate('applications.error.unconfirmed');
        }

        return self::translate('applications.error.default');
    }

    private static function translate(string $key): string
    {
        $message = __($key);

        return is_string($message) ? $message : $key;
    }
}
