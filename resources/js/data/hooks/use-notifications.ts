import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import type { ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import {
    markAllNotificationsRead,
    sortedNotifications,
} from '@/data/fixtures/handlers/account';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { NotificationItem } from '@/types/contracts';

type Empty = Record<string, never>;

export function useNotifications() {
    const real = () => {
        const e = endpoints.notifications();

        return apiFetch<NotificationItem[]>(e.url, { method: e.method });
    };
    const fixture = () =>
        fixtureCall(() => sortedNotifications(), {
            empty: (): NotificationItem[] => [],
        });

    return useQuery({
        queryKey: keys.notifications(),
        queryFn: fromSource({ real, fixture }),
    });
}

export function useMarkAllRead() {
    const queryClient = useQueryClient();
    const real = () => {
        const e = endpoints.markAllNotificationsRead();

        return apiFetch<Empty>(e.url, { method: e.method });
    };
    const fixture = () => fixtureCall(() => markAllNotificationsRead());

    return useMutation<Empty, ApiError, void>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () =>
            Promise.all([
                queryClient.invalidateQueries({
                    queryKey: keys.notifications(),
                }),
                queryClient.invalidateQueries({
                    queryKey: keys.account.status(),
                }),
            ]),
    });
}
