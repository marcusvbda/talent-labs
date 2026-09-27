// Relative-time helpers for fixtures. Every value is computed from Date.now()
// at call time (module load for the catalog), so the fixture never goes stale.
import type { ISODateTime } from '../../../types/contracts';

const MINUTE_MS = 60_000;
const HOUR_MS = 60 * MINUTE_MS;
const DAY_MS = 24 * HOUR_MS;

export const minutesAgo = (n: number): ISODateTime =>
    new Date(Date.now() - n * MINUTE_MS).toISOString();

export const hoursAgo = (n: number): ISODateTime =>
    new Date(Date.now() - n * HOUR_MS).toISOString();

export const daysAgo = (n: number): ISODateTime =>
    new Date(Date.now() - n * DAY_MS).toISOString();

// Minutes elapsed since the local midnight of today.
const minutesSinceLocalMidnight = (): number => {
    const now = new Date();
    const midnight = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    return Math.floor((now.getTime() - midnight.getTime()) / MINUTE_MS);
};

// Like minutesAgo, but clamped so the result is never before local midnight:
// "collected today" rows stay inside today even right after 00:00.
export const minutesAgoToday = (n: number): ISODateTime =>
    minutesAgo(Math.min(n, minutesSinceLocalMidnight()));
