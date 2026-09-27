import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import type { ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import type { Endpoint } from '@/data/endpoints';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { LiveSending } from '@/types/contracts';

type Context = { previous: LiveSending | undefined };

// Shared optimistic plumbing: `next` derives the optimistic value from the
// cached one; nothing is written when there is no cached value.
const useSendingMutation = (
    real: () => Promise<LiveSending>,
    fixture: () => Promise<LiveSending>,
    next: (previous: LiveSending) => LiveSending,
) => {
    const queryClient = useQueryClient();

    return useMutation<LiveSending, ApiError, void, Context>({
        mutationFn: fromSource({ real, fixture }),
        onMutate: async () => {
            await queryClient.cancelQueries({ queryKey: keys.sending() });

            const previous = queryClient.getQueryData<LiveSending>(
                keys.sending(),
            );

            if (previous !== undefined) {
                queryClient.setQueryData<LiveSending>(
                    keys.sending(),
                    next(previous),
                );
            }

            return { previous };
        },
        onError: (_error, _variables, context) => {
            if (context?.previous !== undefined) {
                queryClient.setQueryData<LiveSending>(
                    keys.sending(),
                    context.previous,
                );
            }
        },
        onSuccess: (data) => {
            queryClient.setQueryData<LiveSending>(keys.sending(), data);
        },
    });
};

const post = (e: Endpoint) => () =>
    apiFetch<LiveSending>(e.url, { method: e.method });

export function usePauseSending() {
    const fixture = () =>
        fixtureCall(async () => {
            const { engine } = await import('@/data/fixtures/engine');

            engine.pause();

            return fixtureState.liveSending();
        });

    return useSendingMutation(
        post(endpoints.pauseSending()),
        fixture,
        (previous) => ({ ...previous, state: 'paused' }),
    );
}

export function useResumeSending() {
    const fixture = () =>
        fixtureCall(async () => {
            const { engine } = await import('@/data/fixtures/engine');

            engine.resume();

            return fixtureState.liveSending();
        });

    return useSendingMutation(
        post(endpoints.resumeSending()),
        fixture,
        (previous) => ({
            ...previous,
            state: previous.queuedCount > 0 ? 'waiting' : 'idle',
        }),
    );
}
