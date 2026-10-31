<?php

namespace App\Reports;

use App\Enums\ApplicationStatus;
use App\Models\Application;

/**
 * Read-only reply rates per recipient tier for applications sent in the window.
 * Replies are client-reported, so the counts are a minimum.
 *
 * @phpstan-type TierRow array{tier: string, sent: int, replies: int, reply_rate: float}
 * @phpstan-type TierReport array{window_days: int, since: string, tiers: list<TierRow>, totals: TierRow}
 */
class TierPerformance
{
    /**
     * @return TierReport
     */
    public function build(int $days): array
    {
        $since = now()->subDays($days);

        $rows = Application::query()
            ->toBase()
            ->where('status', ApplicationStatus::Sent->value)
            ->where('sent_at', '>=', $since)
            ->groupBy('recipient_tier')
            ->orderByRaw('recipient_tier asc nulls last')
            ->selectRaw('recipient_tier, count(*) as sent, count(replied_at) as replies')
            ->get();

        $tiers = [];
        $sent = 0;
        $replies = 0;

        foreach ($rows as $row) {
            $tierSent = (int) $row->sent;
            $tierReplies = (int) $row->replies;
            $sent += $tierSent;
            $replies += $tierReplies;

            $tiers[] = self::row(is_string($row->recipient_tier) && $row->recipient_tier !== '' ? $row->recipient_tier : 'unknown', $tierSent, $tierReplies);
        }

        return [
            'window_days' => $days,
            'since' => $since->toIso8601String(),
            'tiers' => $tiers,
            'totals' => self::row('total', $sent, $replies),
        ];
    }

    /**
     * @return TierRow
     */
    private static function row(string $tier, int $sent, int $replies): array
    {
        return [
            'tier' => $tier,
            'sent' => $sent,
            'replies' => $replies,
            'reply_rate' => $sent > 0 ? round($replies / $sent * 100, 1) : 0.0,
        ];
    }
}
