<?php

namespace App\Contacts\Support;

final readonly class SmtpProbeResult
{
    /**
     * @param  array<string, int|null>  $codes  Recipient address => RCPT reply code (null = no reply or timeout).
     */
    public function __construct(
        public bool $connected,
        public bool $sessionOk,
        public ?string $failure,
        public array $codes,
    ) {}
}
