import {
    APPLICATION_SEEDS,
    COMPANIES,
    JOBS,
    NOTIFICATIONS,
    PREFERENCES,
    PROFILES,
} from '@/data/fixtures/catalog';
import { getDevState } from '@/data/fixtures/dev-state';
import { SEND_DURATION_MS } from '@/data/fixtures/send-steps';
import { createFixtureStore } from '@/data/fixtures/store';
import type {
    Account,
    AccountStatus,
    ApplicationItem,
    ApplicationProfile,
    JobDetail,
    JobLanguage,
    LiveSending,
    NotificationItem,
    PlanKey,
    Preferences,
    Quota,
    QueueResult,
    RegionKey,
    SendMode,
    SendStage,
    SubStep,
} from '@/types/contracts';

// The client's own Gmail account (never a recipient address).
const ACCOUNT_EMAIL = 'ana.silva@example.test';
const EU_COUNTRIES = new Set([
    'AT',
    'BE',
    'BG',
    'HR',
    'CY',
    'CZ',
    'DK',
    'EE',
    'FI',
    'FR',
    'DE',
    'GR',
    'HU',
    'IE',
    'IT',
    'LV',
    'LT',
    'LU',
    'MT',
    'NL',
    'PL',
    'PT',
    'RO',
    'SK',
    'SI',
    'ES',
    'SE',
    'GB',
    'NO',
    'CH',
    'IS',
    'LI',
]);

export const regionForCountry = (country: string | null): RegionKey => {
    const code = (country ?? '').toUpperCase();

    if (code === 'BR') {
        return 'br';
    }

    return EU_COUNTRIES.has(code) ? 'eu' : 'row';
};

const SPACING = { minSeconds: 45, maxSeconds: 120 };
const LANGUAGES: JobLanguage[] = ['en', 'pt'];

const PLAN_CONFIG: Record<PlanKey, { dailyLimit: number; mode: SendMode }> = {
    free: { dailyLimit: 25, mode: 'random' },
    starter: { dailyLimit: 50, mode: 'select' },
    pro: { dailyLimit: 150, mode: 'review' },
};
const PLAN_NAMES: Record<PlanKey, string> = {
    free: 'Free',
    starter: 'Starter',
    pro: 'Pro',
};

export type StoredApplication = { item: ApplicationItem; jobId: number };

type FixtureData = {
    companies: typeof COMPANIES;
    jobs: JobDetail[];
    applications: StoredApplication[];
    profiles: ApplicationProfile[];
    preferences: Preferences;
    notifications: NotificationItem[];
    account: Omit<Account, 'region'>;
    onboarding: { basicsDone: boolean; preferencesSaved: boolean };
    paused: boolean;
    sendingApplicationId: number | null;
    currentStage: SendStage | null;
    currentSubStep: SubStep | null;
    failNextSend: boolean;
    waitStartedAt: string | null; // when the current wait for the queue head began
    // NOTE: the window is only a LABEL. The simulated engine still sends when
    // the owner presses "Simulate send" outside of it (e.g. at night).
    window: {
        start: string;
        end: string;
        weekdaysOnly: boolean;
        timezone: string;
    };
};

const isToday = (iso: string): boolean =>
    new Date(iso).toDateString() === new Date().toDateString();

const todayRows = (rows: StoredApplication[]) =>
    rows.filter((row) => isToday(row.item.queuedAt));

// Mirrors `Application::countedToday()`: what consumes today's quota.
const COUNTED_STATUSES: ApplicationItem['status'][] = [
    'queued',
    'sending',
    'sent',
    'ambiguous',
];

const countedToday = (rows: StoredApplication[]): number =>
    todayRows(rows).filter((row) => COUNTED_STATUSES.includes(row.item.status))
        .length;

const sentTodayCount = (rows: StoredApplication[]): number =>
    todayRows(rows).filter((row) => row.item.status === 'sent').length;

const initial: FixtureData = {
    companies: [...COMPANIES],
    jobs: [...JOBS],
    applications: APPLICATION_SEEDS.map((seed) => ({
        item: { ...seed.item },
        jobId: seed.jobId,
    })),
    profiles: PROFILES.map((profile) => ({ ...profile })),
    preferences: { ...PREFERENCES },
    notifications: [...NOTIFICATIONS],
    account: {
        name: 'Ana Silva',
        email: ACCOUNT_EMAIL,
        locale: 'en',
        timezone: 'Europe/Berlin',
        country: 'DE',
    },
    onboarding: { basicsDone: false, preferencesSaved: false },
    paused: false,
    sendingApplicationId: null,
    currentStage: null,
    currentSubStep: null,
    failNextSend: false,
    waitStartedAt: null,
    window: {
        start: '09:00',
        end: '18:00',
        weekdaysOnly: true,
        timezone: 'Europe/Berlin',
    },
};

const store = createFixtureStore(initial);

// ---- Matching ---------------------------------------------------------------

const fold = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

// Blank needles never match: an unnormalised draft must not count every job.
// Approximates the backend's whole-word rule (WordPattern): a boundary only
// applies on a side where the term starts/ends with a letter or digit, so
// "C++" or ".NET" still match without requiring a word boundary on that side.
const escapeRegExp = (value: string): string =>
    value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const ALNUM = /[\p{L}\p{N}]/u;

const wordPattern = (needle: string): RegExp => {
    const trimmed = needle.trim();
    const left = ALNUM.test(trimmed.charAt(0)) ? '\\b' : '';
    const right = ALNUM.test(trimmed.charAt(trimmed.length - 1)) ? '\\b' : '';

    return new RegExp(`${left}${escapeRegExp(trimmed)}${right}`);
};

const containsAny = (haystack: string, needles: string[]): boolean =>
    needles.some((needle) => {
        const folded = fold(needle).trim();

        return folded !== '' && wordPattern(folded).test(haystack);
    });

// Inside a field any value can match; between fields all must match. An empty
// list is "no constraint", and a job with an unclear level always passes the
// seniority rule.
export function jobMatchesPreferences(
    job: JobDetail,
    prefs: Preferences,
): boolean {
    const title = fold(job.title);
    const location = fold(job.location ?? '');
    const stack = job.stack.map(fold);
    const locationMatch = containsAny(location, prefs.locations);

    if (prefs.titles.length > 0 && !containsAny(title, prefs.titles)) {
        return false;
    }

    if (
        prefs.seniorities.length > 0 &&
        job.seniority !== 'unknown' &&
        !prefs.seniorities.includes(job.seniority)
    ) {
        return false;
    }

    if (
        prefs.stack.length > 0 &&
        !prefs.stack.some((item) => stack.includes(fold(item)))
    ) {
        return false;
    }

    // Exclude words match the title by whole word, but the stack by exact
    // tag (same "whole item" comparison as the stack filter above, not a
    // substring/word match inside a single normalized tag).
    if (
        containsAny(title, prefs.excludeWords) ||
        prefs.excludeWords.some((word) => stack.includes(fold(word)))
    ) {
        return false;
    }

    if (prefs.remoteMode === 'remote_only') {
        return job.isRemote === true;
    }

    if (prefs.remoteMode === 'remote_or_locations') {
        return (
            job.isRemote === true ||
            prefs.locations.length === 0 ||
            locationMatch
        );
    }

    return locationMatch;
}

// ---- Selectors ------------------------------------------------------------------

const planConfig = (plan: PlanKey) => PLAN_CONFIG[plan];

const limit = (): number => planConfig(getDevState().plan).dailyLimit;

const activeLanguages = (): JobLanguage[] =>
    store
        .get()
        .profiles.filter((profile) => profile.active && profile.complete)
        .map((profile) => profile.language);

const gmailNeedsReauth = (): boolean =>
    getDevState().gmail === 'needs_reconnection';

const quota = (): Quota => {
    const usedToday = countedToday(store.get().applications);
    const tomorrow = new Date();
    tomorrow.setHours(24, 0, 0, 0);

    return {
        usedToday,
        limit: limit(),
        remaining: Math.max(0, limit() - usedToday),
        resetsAt: tomorrow.toISOString(),
    };
};

const accountStatus = (): AccountStatus => {
    const dev = getDevState();
    const config = planConfig(dev.plan);
    const data = store.get();
    const reauth = gmailNeedsReauth();
    const gmailState =
        dev.gmail === 'needs_reconnection'
            ? 'reauthorization_required'
            : dev.gmail;
    const account = data.account;
    const forced = dev.onboardingComplete;
    const flags = {
        basics: forced || data.onboarding.basicsDone,
        gmail: forced || dev.gmail === 'connected',
        profile: forced || activeLanguages().length > 0,
        preferences: forced || data.onboarding.preferencesSaved,
    };
    const done = Object.values(flags).every(Boolean);

    return {
        plan: {
            key: dev.plan,
            name: PLAN_NAMES[dev.plan],
            mode: config.mode,
            dailyLimit: config.dailyLimit,
        },
        quota: quota(),
        gmail: {
            state: gmailState,
            accountEmail: dev.gmail === 'disconnected' ? null : ACCOUNT_EMAIL,
        },
        sending: {
            paused: data.paused || reauth,
            autoPausedReason: reauth ? 'reauthorization_required' : null,
        },
        onboarding: {
            complete: done,
            steps: [
                { key: 'basics', done: flags.basics },
                { key: 'gmail', done: flags.gmail },
                { key: 'profile', done: flags.profile },
                { key: 'preferences', done: flags.preferences },
            ],
        },
        profiles: { activeLanguages: activeLanguages() },
        region: regionForCountry(account.country),
        country: account.country,
        timezone: account.timezone,
        unreadNotifications: data.notifications.filter(
            (row) => row.readAt === null,
        ).length,
    };
};

const insideWindow = (): boolean => {
    const win = store.get().window;
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: win.timezone,
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date());
    const part = (type: string) =>
        parts.find((row) => row.type === type)?.value ?? '';
    const clock = `${part('hour')}:${part('minute')}`;

    if (win.weekdaysOnly && ['Sat', 'Sun'].includes(part('weekday'))) {
        return false;
    }

    return clock >= win.start && clock < win.end;
};

const queuedRows = (): ApplicationItem[] =>
    store
        .get()
        .applications.map((row) => row.item)
        .filter((item) => item.status === 'queued')
        .sort(
            (a, b) =>
                new Date(a.scheduledFor ?? a.queuedAt).getTime() -
                new Date(b.scheduledFor ?? b.queuedAt).getTime(),
        );

const sendingItem = (): ApplicationItem | null =>
    store.get().applications.find((row) => row.item.status === 'sending')
        ?.item ?? null;

const liveSending = (): LiveSending => {
    const data = store.get();
    const queue = queuedRows();
    const current = sendingItem();
    const max = limit();
    const used = sentTodayCount(data.applications);
    let state: LiveSending['state'] = 'idle';

    if (data.paused || gmailNeedsReauth()) {
        state = 'paused';
    } else if (Math.max(0, max - used) === 0) {
        state = 'limit_reached';
    } else if (current) {
        state = 'sending';
    } else if (queue.length > 0) {
        state = 'waiting';
    } else if (!insideWindow()) {
        state = 'outside_window';
    }

    const last = queue[queue.length - 1];

    return {
        state,
        current,
        // Set whenever the daily limit is above zero, clamped to it.
        progress:
            max > 0 ? { index: Math.min(used + 1, max), total: max } : null,
        queue: queue.slice(0, 3),
        queuedCount: queue.length,
        nextSendAt: queue[0]?.scheduledFor ?? null,
        waitStartedAt: queue.length > 0 ? data.waitStartedAt : null,
        estimatedFinishAt: last?.scheduledFor
            ? new Date(
                  new Date(last.scheduledFor).getTime() + SEND_DURATION_MS,
              ).toISOString()
            : null,
        spacing: SPACING,
        window: { ...data.window },
    };
};

// Jobs the client already applied to today (any status) or that are in flight.
const takenJobIds = (): Set<number> => {
    const rows = store.get().applications;

    return new Set(
        rows
            .filter(
                (row) =>
                    isToday(row.item.queuedAt) ||
                    row.item.status === 'queued' ||
                    row.item.status === 'sending',
            )
            .map((row) => row.jobId),
    );
};

// Jobs the preferences match and that are not taken today, newest first.
const preferenceMatches = (): JobDetail[] => {
    const data = store.get();
    const taken = takenJobIds();

    return data.jobs
        .filter(
            (job) =>
                !taken.has(job.id) &&
                jobMatchesPreferences(job, data.preferences),
        )
        .sort(
            (a, b) =>
                new Date(b.firstSeenAt).getTime() -
                new Date(a.firstSeenAt).getTime(),
        );
};

const matches = (): JobDetail[] => {
    const active = activeLanguages();

    return preferenceMatches().filter((job) => active.includes(job.language));
};

const lockedByLanguage = (): { language: JobLanguage; count: number }[] => {
    const active = activeLanguages();

    return LANGUAGES.map((language) => ({
        language,
        count: preferenceMatches().filter(
            (job) => job.language === language && !active.includes(language),
        ).length,
    })).filter((row) => row.count > 0);
};

// ---- Mutations --------------------------------------------------------------------

const randomSpacingMs = (): number =>
    (SPACING.minSeconds +
        Math.random() * (SPACING.maxSeconds - SPACING.minSeconds)) *
    1000;

function queueJobs(jobIds: number[], origin: 'auto' | 'manual'): QueueResult {
    const result: QueueResult = { queued: [], rejected: [], quota: quota() };
    const active = activeLanguages();
    let nextId = Math.max(0, ...store.get().applications.map((r) => r.item.id));
    let slot = Math.max(
        Date.now(),
        ...queuedRows().map((item) =>
            new Date(item.scheduledFor ?? item.queuedAt).getTime(),
        ),
    );

    for (const jobId of jobIds) {
        const data = store.get();
        const job = data.jobs.find((row) => row.id === jobId);

        if (!job) {
            result.rejected.push({
                jobId,
                reason: 'This job is no longer available.',
            });
            continue;
        }

        if (countedToday(data.applications) >= limit()) {
            result.rejected.push({
                jobId,
                reason: "You have reached today's limit.",
            });
            continue;
        }

        const companyTaken = todayRows(data.applications).some(
            (row) =>
                row.item.company.id === job.company.id &&
                ['queued', 'sending', 'sent', 'ambiguous'].includes(
                    row.item.status,
                ),
        );

        if (companyTaken) {
            result.rejected.push({
                jobId,
                reason: 'You already applied to this company today.',
            });
            continue;
        }

        if (!active.includes(job.language)) {
            result.rejected.push({
                jobId,
                reason: 'You have no application profile in this language.',
            });
            continue;
        }

        nextId += 1;
        slot += randomSpacingMs();

        const item: ApplicationItem = {
            id: nextId,
            company: {
                id: job.company.id,
                name: job.company.name,
                initials: job.company.initials,
            },
            title: job.title,
            language: job.language,
            origin,
            status: 'queued',
            stage: null,
            subStep: null,
            lastError: null,
            queuedAt: new Date().toISOString(),
            scheduledFor: new Date(slot).toISOString(),
            sentAt: null,
            jobUrl: null,
        };

        store.set((s) => ({
            ...s,
            applications: [...s.applications, { item, jobId }],
        }));
        result.queued.push({
            jobId,
            applicationId: item.id,
            scheduledFor: item.scheduledFor as string,
        });
    }

    result.quota = quota();

    return result;
}

function patchApplication(
    id: number,
    patch: Partial<ApplicationItem>,
): ApplicationItem | null {
    let updated: ApplicationItem | null = null;

    store.set((s) => ({
        ...s,
        applications: s.applications.map((row) => {
            if (row.item.id !== id) {
                return row;
            }

            updated = { ...row.item, ...patch };

            return { ...row, item: updated };
        }),
    }));

    return updated;
}

function addNotification(
    type: NotificationItem['type'],
    data: NotificationItem['data'],
): NotificationItem {
    const next =
        Math.max(
            0,
            ...store
                .get()
                .notifications.map((row) => Number(row.id.replace(/\D/g, ''))),
        ) + 1;
    const notification: NotificationItem = {
        id: `n-${next}`,
        type,
        data,
        readAt: null,
        createdAt: new Date().toISOString(),
    };

    store.set((s) => ({
        ...s,
        notifications: [notification, ...s.notifications],
    }));

    return notification;
}

const account = (): Account => {
    const data = store.get().account;

    return { ...data, region: regionForCountry(data.country) };
};

function queueRandom(): QueueResult {
    const pool = [...matches()];

    for (let i = pool.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));

        [pool[i], pool[j]] = [pool[j], pool[i]];
    }

    const result: QueueResult = { queued: [], rejected: [], quota: quota() };

    for (const job of pool) {
        if (quota().remaining === 0) {
            break;
        }

        const one = queueJobs([job.id], 'manual');

        result.queued.push(...one.queued);
        result.rejected.push(...one.rejected);
    }

    result.quota = quota();

    return result;
}

export const fixtureState = {
    ...store,
    account,
    planConfig,
    quota,
    accountStatus,
    liveSending,
    matches,
    lockedByLanguage,
    queue: queueJobs,
    queueRandom,
    queuedItems: queuedRows,
    patchApplication,
    addNotification,
};
