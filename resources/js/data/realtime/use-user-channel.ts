import { usePage } from '@inertiajs/react';
import { echo, echoIsConfigured } from '@laravel/echo-react';
import type { QueryClient } from '@tanstack/react-query';
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import { devEmitter } from '@/data/realtime/dev-emitter';
import { useFixtures } from '@/data/source';
import type { SharedProps } from '@/types/shared';

export type ChannelHandlers<E extends Record<string, unknown>> = {
    [K in keyof E]?: (payload: E[K], qc: QueryClient) => void;
};

type AnyHandlers = Record<
    string,
    ((payload: unknown, qc: QueryClient) => void) | undefined
>;

function useLatest(handlers: AnyHandlers) {
    const ref = useRef(handlers);

    useEffect(() => {
        ref.current = handlers;
    });

    return ref;
}

// Fixtures mode: events come from the dev emitter, no socket is opened.
function useFixtureChannel(handlers: AnyHandlers, enabled: boolean) {
    const qc = useQueryClient();
    const latest = useLatest(handlers);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        return devEmitter.subscribe((event, payload) => {
            latest.current[event]?.(payload, qc);
        });
    }, [enabled, qc, latest]);
}

// Real mode: listens on the private App.Models.User.{id} channel.
function useRealChannel(
    handlers: AnyHandlers,
    userId: number | undefined,
    enabled: boolean,
) {
    const qc = useQueryClient();
    const latest = useLatest(handlers);
    const eventKey = Object.keys(handlers).join('|');

    useEffect(() => {
        if (!enabled || userId === undefined || !echoIsConfigured()) {
            return;
        }

        const name = `App.Models.User.${userId}`;
        const instance = echo().private(name);
        const events = eventKey.split('|');
        const listeners = events.map((event) => {
            const listener = (payload: unknown) =>
                latest.current[event]?.(payload, qc);

            instance.listen(`.${event}`, listener);

            return [event, listener] as const;
        });

        return () => {
            listeners.forEach(([event, listener]) =>
                instance.stopListening(`.${event}`, listener),
            );
        };
    }, [enabled, userId, eventKey, qc, latest]);
}

// Chosen once per build, so the hook order never changes at runtime.
const useTransport = useFixtures
    ? (handlers: AnyHandlers, _userId: number | undefined, enabled: boolean) =>
          useFixtureChannel(handlers, enabled)
    : useRealChannel;

export function useUserChannel<E extends Record<string, unknown>>(
    handlers: ChannelHandlers<E>,
): void {
    const user = usePage<SharedProps>().props.auth.user;
    const enabled = user !== null && Object.keys(handlers).length > 0;

    useTransport(handlers as AnyHandlers, user?.id, enabled);
}
