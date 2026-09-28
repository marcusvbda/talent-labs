import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { QueryClient } from '@tanstack/react-query';
import { apiFetch, type ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { QueueResult } from '@/types/contracts';

// Everything a queued application can change on screen.
export const invalidateAfterQueue = (queryClient: QueryClient) =>
    Promise.all([
        queryClient.invalidateQueries({ queryKey: keys.jobs.all() }),
        queryClient.invalidateQueries({ queryKey: keys.applications.all() }),
        queryClient.invalidateQueries({ queryKey: keys.dashboards() }),
        queryClient.invalidateQueries({ queryKey: keys.charts() }),
        queryClient.invalidateQueries({ queryKey: keys.sending() }),
        queryClient.invalidateQueries({ queryKey: keys.account.status() }),
    ]);

export function useQueueApplications() {
    const queryClient = useQueryClient();
    const real = ({ jobIds }: { jobIds: number[] }) => {
        const e = endpoints.queueApplications();

        return apiFetch<QueueResult>(e.url, {
            method: e.method,
            body: { jobIds },
        });
    };
    const fixture = ({ jobIds }: { jobIds: number[] }) =>
        fixtureCall(async () => {
            const result = fixtureState.queue(jobIds, 'manual');

            if (result.queued.length > 0) {
                const { engine } = await import('@/data/fixtures/engine');

                engine.start();
            }

            return result;
        });

    return useMutation<QueueResult, ApiError, { jobIds: number[] }>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () => invalidateAfterQueue(queryClient),
    });
}
