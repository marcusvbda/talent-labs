import type { CSSProperties, RefObject } from 'react';
import { useSyncExternalStore } from 'react';
import { useInView } from './use-in-view';

const noopSubscribe = () => () => {};

/** True only in a browser that supports observers and has no reduced-motion preference. */
const canAnimate = (): boolean =>
    typeof IntersectionObserver !== 'undefined' &&
    !matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Fade/translate reveal on first view. Content stays visible on the server,
 * without IntersectionObserver, and under prefers-reduced-motion.
 */
export function useReveal<T extends Element>(
    index = 0,
): { ref: RefObject<T | null>; className: string; style: CSSProperties } {
    const [ref, inView] = useInView<T>();
    const armed = useSyncExternalStore(noopSubscribe, canAnimate, () => false);

    return {
        ref,
        className: !armed ? '' : inView ? 'animate-reveal' : 'opacity-0',
        style: {
            animationDelay: `calc(${index} * var(--reveal-stagger))`,
        },
    };
}
