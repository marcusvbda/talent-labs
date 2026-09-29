import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch, type ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { invalidateAfterQueue } from '@/data/hooks/use-queue-applications';
import { fromSource } from '@/data/source';
import type { QueueResult } from '@/types/contracts';

export function useQueueRandom() {
    const queryClient = useQueryClient();
    const real = () => {
        const e = endpoints.queueRandom();

        return apiFetch<QueueResult>(e.url, { method: e.method });
    };
    const fixture = () =>
        fixtureCall(async () => {
            const result = fixtureState.queueRandom();

            if (result.queued.length > 0) {
                const { engine } = await import('@/data/fixtures/engine');

                engine.start();
            }

            return result;
        });

    return useMutation<QueueResult, ApiError, void>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () => invalidateAfterQueue(queryClient),
    });
}
