<?php

namespace App\Plans;

use App\Enums\PlanKey;
use App\Models\User;
use RuntimeException;

final class PlanCatalog
{
    public function for(User $user): Plan
    {
        // Read defensively: the `plan_key` column may not exist yet.
        $key = $user->getAttribute('plan_key');

        if ($key instanceof PlanKey) {
            return $this->get($key);
        }

        return $this->get(is_string($key) ? $key : '');
    }

    /**
     * Unknown keys fall back to the configured default, then to the first catalog entry.
     */
    public function get(string|PlanKey $key): Plan
    {
        $catalog = $this->catalog();
        $key = $key instanceof PlanKey ? $key->value : $key;

        if (isset($catalog[$key])) {
            return $catalog[$key];
        }

        $default = config('talent.plans.default');

        if (is_string($default) && isset($catalog[$default])) {
            return $catalog[$default];
        }

        return array_values($catalog)[0] ?? throw new RuntimeException('The plan catalog is empty.');
    }

    /**
     * @return list<Plan>
     */
    public function all(): array
    {
        return array_values($this->catalog());
    }

    /**
     * @return array<string, Plan>
     */
    private function catalog(): array
    {
        /** @var array<string, array{name: string, mode: string, daily_limit: int}> $entries */
        $entries = config('talent.plans.catalog', []);

        $plans = [];

        foreach ($entries as $key => $entry) {
            $planKey = PlanKey::tryFrom($key);

            if ($planKey === null) {
                continue;
            }

            $plans[$key] = new Plan(
                key: $planKey,
                name: $entry['name'],
                mode: $entry['mode'],
                dailyLimit: (int) $entry['daily_limit'],
            );
        }

        return $plans;
    }
}
