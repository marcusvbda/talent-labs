<?php

namespace App\Outreach\Data;

final readonly class SendEligibility
{
    /**
     * @param  list<string>  $unmet
     */
    public function __construct(
        public array $unmet,
        public int $sentToday,
        public int $remaining,
    ) {}

    public function ok(): bool
    {
        return $this->unmet === [];
    }
}
