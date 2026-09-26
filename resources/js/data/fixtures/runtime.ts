import { ApiError } from '@/data/api';
import { getDevState, setDevState } from '@/data/fixtures/dev-state';

export const FIXTURE_LATENCY_MS = 350;

// Latency of +-40% around the base value, so loading states are visible.
const latency = () => FIXTURE_LATENCY_MS * (0.6 + Math.random() * 0.8);

const wait = (ms: number) =>
    new Promise<void>((resolve) => setTimeout(resolve, ms));

export async function fixtureCall<T>(
    resolve: () => T | Promise<T>,
    opts?: { empty?: () => T },
): Promise<T> {
    await wait(latency());

    const { state, failNext } = getDevState();

    if (state === 'loading') {
        return new Promise<T>(() => {});
    }

    if (failNext) {
        setDevState({ failNext: false });
        throw new ApiError(500, 'Simulated fixture failure');
    }

    if (state === 'error') {
        throw new ApiError(500, 'Simulated fixture failure');
    }

    if (state === 'empty') {
        return opts?.empty?.() ?? resolve();
    }

    return resolve();
}
