import { useEffect, useReducer, useState } from 'react';
import type { SubStep } from '@/types/contracts';
import {
    COUNTDOWN_MS,
    DEMO_EPOCH,
    DEMO_INITIAL_ACTIVITY,
    DEMO_JOBS,
    DEMO_START_SENT,
    DEMO_STEPS,
    STEP_MS,
    TICK_MS,
} from './demo-data';
import type { DemoActivity, DemoJob } from './demo-data';

const LAST_STEP = DEMO_STEPS.length - 1;
const QUEUE_SIZE = 2;
const ACTIVITY_SIZE = 3;

export type DemoLoopState = {
    job: DemoJob;
    step: number;
    /** 0-4 over the five real stages. */
    stageIndex: number;
    subStep: SubStep | null;
    queue: DemoJob[];
    sentToday: number;
    activity: DemoActivity[];
    /** Epoch ms on the demo clock, advanced by the loop. */
    now: number;
    countdown: { startsAt: number; endsAt: number } | null;
    /** Jobs completed in the current pass. */
    cycle: number;
};

type Action = { type: 'advance' } | { type: 'tick' };

const queueAfter = (cycle: number): DemoJob[] =>
    Array.from(
        { length: QUEUE_SIZE },
        (_, offset) => DEMO_JOBS[(cycle + 1 + offset) % DEMO_JOBS.length]!,
    );

const frame = (cycle: number): DemoLoopState => {
    const first = DEMO_STEPS[0]!;

    return {
        job: DEMO_JOBS[cycle]!,
        step: 0,
        stageIndex: first.stageIndex,
        subStep: first.subStep,
        queue: queueAfter(cycle),
        sentToday: DEMO_START_SENT + cycle,
        activity: DEMO_INITIAL_ACTIVITY,
        now: DEMO_EPOCH,
        countdown: null,
        cycle,
    };
};

export const initialDemoState = (): DemoLoopState => frame(0);

const reducer = (state: DemoLoopState, action: Action): DemoLoopState => {
    if (action.type === 'advance') {
        if (state.step >= LAST_STEP) {
            return state;
        }

        const step = state.step + 1;
        const next = DEMO_STEPS[step]!;
        const now = state.now + STEP_MS;
        const base = { ...state, step, now };

        if (step < LAST_STEP) {
            return {
                ...base,
                stageIndex: next.stageIndex,
                subStep: next.subStep,
            };
        }

        return {
            ...base,
            stageIndex: next.stageIndex,
            subStep: null,
            sentToday: state.sentToday + 1,
            activity: [
                { id: `sent-${state.cycle}`, job: state.job, at: now },
                ...state.activity,
            ].slice(0, ACTIVITY_SIZE),
            countdown: { startsAt: now, endsAt: now + COUNTDOWN_MS },
        };
    }

    if (!state.countdown) {
        return state;
    }

    const now = state.now + TICK_MS;

    if (now < state.countdown.endsAt) {
        return { ...state, now };
    }

    const cycle = state.cycle + 1;

    if (cycle >= DEMO_JOBS.length) {
        return initialDemoState();
    }

    return {
        ...frame(cycle),
        sentToday: state.sentToday,
        activity: state.activity,
        now,
    };
};

const prefersReducedMotion = (): boolean =>
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Whether the tab is hidden; the loop pauses while it is. */
const useDocumentHidden = (): boolean => {
    const [hidden, setHidden] = useState(false);

    useEffect(() => {
        const sync = () => setHidden(document.hidden);

        sync();
        document.addEventListener('visibilitychange', sync);

        return () => document.removeEventListener('visibilitychange', sync);
    }, []);

    return hidden;
};

/**
 * Passive demo state machine: `setTimeout` chain only, all cleared on
 * unmount. Never runs under reduced motion, while hidden or when not asked to.
 */
export function useDemoLoop({ running }: { running: boolean }): DemoLoopState {
    const [state, dispatch] = useReducer(reducer, undefined, initialDemoState);
    const hidden = useDocumentHidden();
    const active = running && !hidden;
    const counting = state.countdown !== null;

    useEffect(() => {
        if (!active || prefersReducedMotion()) {
            return;
        }

        const id = setTimeout(
            () => dispatch({ type: counting ? 'tick' : 'advance' }),
            counting ? TICK_MS : STEP_MS,
        );

        return () => clearTimeout(id);
    }, [active, counting, state]);

    return state;
}
