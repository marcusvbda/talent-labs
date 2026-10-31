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
        'Email account is not connected.' => 'applications.error.gmail',
        // Rows written before the provider-neutral wording.
        'Gmail is not connected.' => 'applications.error.gmail',
        'CV file is missing.' => 'applications.error.cv',
        'CV file is not a valid PDF.' => 'applications.error.cv_invalid',
        'CV is too large for this email provider.' => 'applications.error.cv_too_large_provider',
        'Email content is invalid.' => 'applications.error.content',
        'Recipient is not a verified contact' => 'applications.error.recipient',
        'Recipient asked not to receive applications.' => 'applications.error.suppressed',
        'Company is no longer verified' => 'applications.error.company',
        'Client account is not active.' => 'applications.error.account',
        'Worker stopped mid-send.' => 'applications.error.unconfirmed',
        'EmailRecipientRejected' => 'applications.error.recipient_rejected',
        'EmailMessageTooLarge' => 'applications.error.cv_too_large_provider',
    ];

    /**
     * @var list<string>
     */
    private const TRANSPORT_ERRORS = ['ConnectionException', 'RequestException', 'TransportException', 'EmailDeliveryUnconfirmed'];

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
