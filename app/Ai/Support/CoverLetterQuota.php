<?php

namespace App\Ai\Support;

use App\Ai\Agents\GenerateCoverLetter;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageRecord;
use App\Models\User;
use App\Plans\PlanCatalog;
use Carbon\CarbonImmutable;

/**
 * Monthly cover letter quota: completed, non-cached generations of the user in the
 * current calendar month (app timezone). Jobs already running may overrun it slightly;
 * that is accepted, there is no lock.
 */
final class CoverLetterQuota
{
    public static function used(User $user): int
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return AiUsageRecord::query()
            ->where('user_id', $user->id)
            ->where('agent', GenerateCoverLetter::CACHE_KEY)
            ->where('status', AiUsageStatus::Completed)
            ->where('cache_hit', false)
            ->whereBetween('created_at', [$now->startOfMonth(), $now->endOfMonth()])
            ->count();
    }

    public static function limit(User $user): int
    {
        return app(PlanCatalog::class)->for($user)->aiMonthlyQuota;
    }

    public static function exhausted(User $user): bool
    {
        return self::used($user) >= self::limit($user);
    }
}
