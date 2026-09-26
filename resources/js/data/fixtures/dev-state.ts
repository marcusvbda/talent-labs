import { useSyncExternalStore } from 'react';
import { queryClient } from '@/data/query-client';
import type { PlanKey } from '@/types/plans';

export type DevState = {
    plan: PlanKey;
    state: 'normal' | 'loading' | 'empty' | 'error';
    gmail: 'connected' | 'needs_reconnection' | 'disconnected';
    onboardingComplete: boolean;
    failNext: boolean;
};

const DEFAULT_STATE: DevState = {
    plan: 'starter',
    state: 'normal',
    gmail: 'connected',
    onboardingComplete: true,
    failNext: false,
};

// Changing any of these makes cached reads stale, so they are refetched.
const INVALIDATING_KEYS: (keyof DevState)[] = [
    'plan',
    'state',
    'gmail',
    'onboardingComplete',
];

let current: DevState = DEFAULT_STATE;
const listeners = new Set<() => void>();

export const getDevState = (): DevState => current;

export function setDevState(patch: Partial<DevState>): void {
    const next = { ...current, ...patch };
    const changed = (Object.keys(next) as (keyof DevState)[]).filter(
        (key) => next[key] !== current[key],
    );

    if (changed.length === 0) {
        return;
    }

    current = next;
    listeners.forEach((listener) => listener());

    if (changed.some((key) => INVALIDATING_KEYS.includes(key))) {
        void queryClient.invalidateQueries();
    }
}

const subscribe = (listener: () => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

export const useDevState = (): DevState =>
    useSyncExternalStore(subscribe, getDevState, getDevState);

// Console access, e.g. setDevState({ state: 'error' }). Dev builds only.
if (import.meta.env.DEV) {
    Object.assign(window, {
        setDevState,
        __devState: { get: getDevState, set: setDevState },
    });
}
