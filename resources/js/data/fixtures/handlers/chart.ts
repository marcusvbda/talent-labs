import {
    addDays,
    localDateKey,
    startOfDay,
} from '@/data/fixtures/handlers/dates';
import { getDevState } from '@/data/fixtures/dev-state';
import { fixtureState } from '@/data/fixtures/state';
import type { ChartData } from '@/types/contracts';

const RANGE_DAYS: Record<ChartData['range'], number> = { '14d': 14, '30d': 30 };

export function sentCountsByDay(): Map<string, number> {
    const counts = new Map<string, number>();

    fixtureState.get().applications.forEach(({ item }) => {
        if (item.status !== 'sent' || item.sentAt === null) {
            return;
        }

        const key = localDateKey(new Date(item.sentAt));

        counts.set(key, (counts.get(key) ?? 0) + 1);
    });

    return counts;
}

export function buildChart(
    range: ChartData['range'],
    empty = false,
): ChartData {
    const counts = sentCountsByDay();
    const today = startOfDay(new Date());
    const total = RANGE_DAYS[range];
    const days = Array.from({ length: total }, (_, index) => {
        const date = localDateKey(addDays(today, index - (total - 1)));

        return { date, count: empty ? 0 : (counts.get(date) ?? 0) };
    });
    const active = days.filter((day) => day.count > 0);
    const average =
        active.length === 0
            ? 0
            : Math.round(
                  (active.reduce((sum, day) => sum + day.count, 0) /
                      active.length) *
                      10,
              ) / 10;

    return {
        range,
        days,
        averagePerActiveDay: average,
        limit: fixtureState.planConfig(getDevState().plan).dailyLimit,
    };
}
