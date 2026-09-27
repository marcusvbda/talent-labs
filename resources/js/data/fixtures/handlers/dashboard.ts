import { toJobCard } from '@/data/fixtures/handlers/cards';
import { sentCountsByDay } from '@/data/fixtures/handlers/chart';
import {
    addDays,
    localDateKey,
    startOfDay,
} from '@/data/fixtures/handlers/dates';
import { fixtureState } from '@/data/fixtures/state';
import type {
    ApplicationItem,
    DashboardData,
    DashboardPeriod,
} from '@/types/contracts';

const PERIOD_DAYS: Record<DashboardPeriod, number> = {
    today: 1,
    week: 7,
    month: 30,
};

const countIn = (stamps: number[], from: Date, to: Date): number =>
    stamps.filter((at) => at >= from.getTime() && at < to.getTime()).length;

const latestStamp = (item: ApplicationItem): number =>
    Math.max(
        item.sentAt ? new Date(item.sentAt).getTime() : 0,
        new Date(item.queuedAt).getTime(),
    );

export function buildDashboard(
    period: DashboardPeriod,
    empty = false,
): DashboardData {
    if (empty) {
        return {
            period,
            kpis: {
                collected: { value: 0, previous: 0 },
                sent: { value: 0, previous: 0 },
                totalSent: 0,
                firstSentAt: null,
            },
            hero: {
                sentToday: 0,
                failedToday: 0,
                queued: 0,
                nextSendAt: null,
                lastDays: [],
            },
            matches: { total: 0, newToday: 0, items: [] },
            activity: [],
        };
    }

    const data = fixtureState.get();
    const today = startOfDay(new Date());
    const tomorrow = addDays(today, 1);
    const days = PERIOD_DAYS[period];
    const currentStart = addDays(today, 1 - days);
    const previousStart = addDays(currentStart, -days);
    const seen = data.jobs.map((job) => new Date(job.firstSeenAt).getTime());
    const sentItems = data.applications
        .map((row) => row.item)
        .filter((item) => item.status === 'sent' && item.sentAt !== null);
    const sentStamps = sentItems.map((item) =>
        new Date(item.sentAt as string).getTime(),
    );
    const counts = sentCountsByDay();
    const matches = fixtureState.matches();
    const items = data.applications.map((row) => row.item);

    return {
        period,
        kpis: {
            collected: {
                value: countIn(seen, currentStart, tomorrow),
                previous: countIn(seen, previousStart, currentStart),
            },
            sent: {
                value: countIn(sentStamps, currentStart, tomorrow),
                previous: countIn(sentStamps, previousStart, currentStart),
            },
            totalSent: sentItems.length,
            firstSentAt:
                sentStamps.length === 0
                    ? null
                    : new Date(Math.min(...sentStamps)).toISOString(),
        },
        hero: {
            sentToday: counts.get(localDateKey(today)) ?? 0,
            failedToday: items.filter(
                (item) =>
                    item.status === 'failed' &&
                    latestStamp(item) >= today.getTime(),
            ).length,
            queued: items.filter(
                (item) => item.status === 'queued' || item.status === 'sending',
            ).length,
            nextSendAt: fixtureState.liveSending().nextSendAt,
            lastDays: Array.from({ length: 5 }, (_, index) => {
                const date = localDateKey(addDays(today, index - 5));

                return { date, count: counts.get(date) ?? 0 };
            }),
        },
        matches: {
            total: matches.length,
            newToday: matches.filter((job) => job.collectedToday).length,
            items: matches.slice(0, 4).map(toJobCard),
        },
        activity: [...items]
            .sort((a, b) => latestStamp(b) - latestStamp(a))
            .slice(0, 5),
    };
}
