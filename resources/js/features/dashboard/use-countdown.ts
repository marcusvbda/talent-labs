import { useEffect, useState } from 'react';

const clock = (ms: number): string => {
    const total = Math.max(0, Math.ceil(ms / 1000));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
};

/** Display-only countdown to an instant: ticks locally, never fetches. `null` when there is no target. */
export function useCountdown(target: string | null): string | null {
    const [now, setNow] = useState(() => Date.now());
    const end = target ? new Date(target).getTime() : NaN;
    const valid = Number.isFinite(end);
    const finished = !valid || now >= end;

    useEffect(() => {
        if (finished) {
            return;
        }

        const id = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(id);
    }, [finished, end]);

    return valid ? clock(end - now) : null;
}
