import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ApiError, apiFetch } from '@/data/api';
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

type Variables = {
    drafts: { jobId: number; subject: string; body: string }[];
};

export function useQueueReviewedBatch() {
    const queryClient = useQueryClient();
    const real = ({ drafts }: Variables) => {
        const e = endpoints.queueReviewedBatch();

        return apiFetch<QueueResult>(e.url, {
            method: e.method,
            body: { drafts },
        });
    };
    const fixture = ({ drafts }: Variables) =>
        fixtureCall(async () => {
            const queued: QueueResult['queued'] = [];
            const rejected: QueueResult['rejected'] = [];
            let quota: QueueResult['quota'] | undefined;

            drafts.forEach(({ jobId, subject, body }) => {
                try {
                    validateReviewed(subject, body);
                } catch (error) {
                    if (error instanceof ApiError) {
                        rejected.push({
                            jobId,
                            reason:
                                Object.values(error.errors ?? {})[0]?.[0] ??
                                error.message,
                        });

                        return;
                    }

                    throw error;
                }

                const result = fixtureState.queue([jobId], 'manual');

                result.queued.forEach((row) => {
                    reviewedOverrides.set(row.applicationId, {
                        subject: subject.trim(),
                        body: body.trim(),
                    });
                });
                queued.push(...result.queued);
                rejected.push(...result.rejected);
                quota = result.quota;
            });

            if (queued.length > 0) {
                const { engine } = await import('@/data/fixtures/engine');

                engine.start();
            }

            return {
                queued,
                rejected,
                quota: quota ?? fixtureState.queue([], 'manual').quota,
            };
        });

    return useMutation<QueueResult, ApiError, Variables>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () => invalidateAfterQueue(queryClient),
    });
}
