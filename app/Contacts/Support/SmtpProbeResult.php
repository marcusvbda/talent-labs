<?php

namespace App\Contacts\Support;

final readonly class SmtpProbeResult
{
    /**
     * @param  array<string, int|null>  $codes  Recipient address => RCPT reply code (null = no reply or timeout).
     * @param  array<string, string|null>  $replies  Recipient address => first line of the RCPT reply, without the code.
     */
    public function __construct(
        public bool $connected,
        public bool $sessionOk,
        public ?string $failure,
        public array $codes,
        public array $replies = [],
    ) {}
}
