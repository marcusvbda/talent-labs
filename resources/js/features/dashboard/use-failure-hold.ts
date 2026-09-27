import { useEffect, useState } from 'react';
import type { ApplicationItem } from '@/types/contracts';

const HOLD_MS = 6000;

/**
 * Keeps a failed send on screen for a few seconds. The live payload drops the
 * item the moment it settles, so the last seen `current` is remembered and its
 * settled state is looked up in `settled` (recent activity). Display-only
 * timer, never fetches.
 */
export function useFailureHold(
    current: ApplicationItem | null,
    settled: ApplicationItem[],
    active = true,
): ApplicationItem | null {
    const [tracked, setTracked] = useState(current);
    const [held, setHeld] = useState<ApplicationItem | null>(null);

    if ((current?.id ?? null) !== (tracked?.id ?? null)) {
        const outcome = tracked
            ? settled.find((item) => item.id === tracked.id)
            : undefined;

        setTracked(current);

        if (outcome?.status === 'failed') {
            setHeld(outcome);
        }
    }

    // A stale failure must not reappear when sending resumes.
    if (!active && held) {
        setHeld(null);
    }

    const heldId = held?.id;

    useEffect(() => {
        if (heldId === undefined) {
            return;
        }

        const id = setTimeout(() => setHeld(null), HOLD_MS);

        return () => clearTimeout(id);
    }, [heldId]);

    return held;
}
