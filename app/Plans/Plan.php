<?php

namespace App\Plans;

use App\Enums\PlanKey;

final readonly class Plan
{
    /**
     * @param  string  $mode  One of `auto`, `select`, `review`.
     */
    public function __construct(
        public PlanKey $key,
        public string $name,
        public string $mode,
        public int $dailyLimit,
    ) {}
}
