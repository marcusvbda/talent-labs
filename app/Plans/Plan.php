<?php

namespace App\Plans;

use App\Enums\PlanKey;

final readonly class Plan
{
    /**
     * @param  string  $mode  One of `random`, `select`, `review`.
     */
    public function __construct(
        public PlanKey $key,
        public string $name,
        public string $mode,
        public int $dailyLimit,
    ) {}

    /**
     * Starter and Pro browse and pick postings; Free only sends at random.
     */
    public function canChooseJobs(): bool
    {
        return in_array($this->mode, ['select', 'review'], true);
    }
}
