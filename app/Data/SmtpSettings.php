<?php

namespace App\Data;

/**
 * Validated SMTP account details, before the connection test.
 */
final readonly class SmtpSettings
{
    public function __construct(
        public string $preset,
        public string $host,
        public int $port,
        public string $security,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
        public string $fromAddress,
        public string $displayName,
    ) {}
}
