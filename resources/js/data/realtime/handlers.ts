import type { InfiniteData, QueryClient } from '@tanstack/react-query';
import { keys } from '@/data/keys';
import type { JobsChannelEvents, UserChannelEvents } from '@/types/realtime';
import type {
    AccountStatus,
    ApplicationItem,
    ApplicationStatus,
    DashboardData,
    LiveSending,
    NotificationItem,
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

    qc.setQueryData<LiveSending>(
        keys.sending(),
        (data) =>
            data && {
                ...data,
                current:
                    data.current?.id === application.id
                        ? application
                        : data.current,
            },
    );

    if (SETTLED.includes(application.status)) {
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
