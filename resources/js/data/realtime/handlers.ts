import type { InfiniteData, QueryClient } from '@tanstack/react-query';
import { toast } from '@/components/ui/toast';
import { keys } from '@/data/keys';
import type { Params } from '@/i18n/translate';
import type { JobsChannelEvents, UserChannelEvents } from '@/types/realtime';
import type {
    AccountStatus,
    ApplicationItem,
    ApplicationStatus,
    DashboardData,
    LiveSending,
    NotificationItem,
    NotificationType,
    Paginated,
} from '@/types/contracts';

const ACTIVITY_CAP = 5;
const JOBS_DEBOUNCE_MS = 2000;
const SETTLED: ApplicationStatus[] = ['sent', 'failed', 'ambiguous'];

let jobsTimer: ReturnType<typeof setTimeout> | undefined;

// Only replaces items that are already cached; never adds to a list.
const replaceById = (
    items: ApplicationItem[],
    item: ApplicationItem,
): ApplicationItem[] =>
    items.map((existing) => (existing.id === item.id ? item : existing));

export function applicationProgressed(
    { application }: UserChannelEvents['application.progressed'],
    qc: QueryClient,
): void {
    qc.setQueriesData<InfiniteData<Paginated<ApplicationItem>>>(
        { queryKey: [...keys.applications.all(), 'list'] },
        (data) =>
            data && {
                ...data,
                pages: data.pages.map((page) => ({
                    ...page,
                    data: replaceById(page.data, application),
                })),
            },
    );

    // Counters, the open detail and the tab a row now belongs to follow the status.
    void qc.invalidateQueries({ queryKey: keys.applications.all() });

    qc.setQueriesData<DashboardData>(
        { queryKey: keys.dashboards() },
        (data) => {
            if (!data) {
                return data;
            }

            const known = data.activity.some(({ id }) => id === application.id);

            return {
                ...data,
                activity: known
                    ? replaceById(data.activity, application)
                    : [application, ...data.activity].slice(0, ACTIVITY_CAP),
            };
        },
    );

    // The pre-checks record stages while the row is still queued, so the first
    // stage event of an application is what puts it in the live panel: waiting
    // for `sending.updated` would skip every step but the last.
    const inProgress =
        application.status === 'sending' ||
        (application.status === 'queued' && application.stage !== null);

    qc.setQueryData<LiveSending>(keys.sending(), (data) => {
        if (!data) {
            return data;
        }

        if (data.current?.id === application.id) {
            return { ...data, current: application };
        }

        if (!inProgress) {
            return data;
        }

        return {
            ...data,
            current: application,
            queue: data.queue.filter(({ id }) => id !== application.id),
            queuedCount: Math.max(0, data.queuedCount - 1),
        };
    });

    // The panel's queue, countdown and state come from the server: re-read them
    // whenever an application takes the panel or settles, so a dropped
    // `sending.updated` can never leave the card stuck.
    const takesPanel =
        inProgress &&
        qc.getQueryData<LiveSending>(keys.sending())?.current?.id ===
            application.id;

    if (takesPanel && application.stage === 'validating_recipient') {
        void qc.invalidateQueries({ queryKey: keys.sending() });
    }

    if (SETTLED.includes(application.status)) {
        void qc.invalidateQueries({ queryKey: keys.sending() });
        void qc.invalidateQueries({ queryKey: keys.dashboards() });
        void qc.invalidateQueries({ queryKey: keys.charts() });
        void qc.invalidateQueries({ queryKey: keys.account.status() });
    }
}

export const sendingUpdated = (
    { sending }: UserChannelEvents['sending.updated'],
    qc: QueryClient,
): void => {
    qc.setQueryData(keys.sending(), sending);
};

export const accountUpdated = (
    { status }: UserChannelEvents['account.updated'],
    qc: QueryClient,
): void => {
    qc.setQueryData(keys.account.status(), status);
    void qc.invalidateQueries({ queryKey: keys.plansAll() });
    void qc.invalidateQueries({ queryKey: keys.billing() });
};

export function notificationCreated(
    { notification }: UserChannelEvents['notification.created'],
    qc: QueryClient,
): void {
    qc.setQueryData<NotificationItem[]>(
        keys.notifications(),
        (data) => data && [notification, ...data],
    );
    qc.setQueryData<AccountStatus>(
        keys.account.status(),
        (data) =>
            data && {
                ...data,
                unreadNotifications: data.unreadNotifications + 1,
            },
    );
}

// Types that also surface as a toast when they arrive over the channel.
const TOAST_TONES: Partial<Record<NotificationType, 'error' | 'info'>> = {
    gmail_reauthorization_required: 'error',
    sending_auto_paused: 'error',
    payment_failed: 'error',
    daily_limit_reached: 'info',
};

// `notificationCreated` is a plain handler (no React tree), so it cannot call
// `useT()`; the caller (a hook, `useRealtimeCache`) supplies `t` here instead.
export function notifyToast(
    notification: NotificationItem,
    t: (key: string, params?: Params) => string,
): void {
    const tone = TOAST_TONES[notification.type];

    if (!tone) {
        return;
    }

    const message = t(
        `notifications.${notification.type}`,
        notification.data as Params,
    );

    toast[tone](message);
}

// Bursts of collection runs collapse into a single refetch.
export function jobsCollected(
    _payload: JobsChannelEvents['jobs.collected'],
    qc: QueryClient,
): void {
    clearTimeout(jobsTimer);
    jobsTimer = setTimeout(() => {
        void qc.invalidateQueries({ queryKey: keys.jobs.all() });
        void qc.invalidateQueries({ queryKey: keys.dashboards() });
        void qc.invalidateQueries({ queryKey: keys.preferences.previews() });
    }, JOBS_DEBOUNCE_MS);
}
