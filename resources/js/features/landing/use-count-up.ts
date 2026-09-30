import type { RefObject } from 'react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { useInView } from './use-in-view';

const COUNT_UP_MS = 900;

const noopSubscribe = () => () => {};

const canAnimate = (): boolean =>
    typeof IntersectionObserver !== 'undefined' &&
    !matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Counts a numeral up from zero on first view. It scales the live `target`
 * by progress, so a value driven by the demo loop is never fought: once the
 * animation ends the value is the target itself. Skipped under reduced motion.
 */
export function useCountUp<T extends Element>(
    target: number,
): [RefObject<T | null>, number] {
    const [ref, inView] = useInView<T>();
    const armed = useSyncExternalStore(noopSubscribe, canAnimate, () => false);
    const [progress, setProgress] = useState(0);

    useEffect(() => {
        if (!armed || !inView) {
            return;
        }

        let frame = 0;
        const start = performance.now();

        const tick = (time: number) => {
            const next = Math.min(1, (time - start) / COUNT_UP_MS);

            setProgress(next);

            if (next < 1) {
                frame = requestAnimationFrame(tick);
            }
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [armed, inView]);

    if (!armed || progress >= 1) {
        return [ref, target];
    }

    return [ref, Math.round(target * progress)];
}
