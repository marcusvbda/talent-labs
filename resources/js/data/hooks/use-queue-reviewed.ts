import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch, type ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import {
    reviewedOverrides,
    validateReviewed,
} from '@/data/fixtures/handlers/drafts';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { invalidateAfterQueue } from '@/data/hooks/use-queue-applications';
import { fromSource } from '@/data/source';
import type { QueueResult } from '@/types/contracts';

type Variables = { jobId: number; subject: string; body: string };

export function useQueueReviewed() {
    const queryClient = useQueryClient();
    const real = ({ jobId, subject, body }: Variables) => {
        const e = endpoints.queueReviewed();

        return apiFetch<QueueResult>(e.url, {
            method: e.method,
            body: { jobId, subject, body },
        });
    };
    const fixture = ({ jobId, subject, body }: Variables) =>
        fixtureCall(async () => {
            validateReviewed(subject, body);

            const result = fixtureState.queue([jobId], 'manual');

            result.queued.forEach((row) => {
                reviewedOverrides.set(row.applicationId, {
                    subject: subject.trim(),
                    body: body.trim(),
                });
            });

            if (result.queued.length > 0) {
                const { engine } = await import('@/data/fixtures/engine');

                engine.start();
            }

            return result;
        });

    return useMutation<QueueResult, ApiError, Variables>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () => invalidateAfterQueue(queryClient),
    });
}
