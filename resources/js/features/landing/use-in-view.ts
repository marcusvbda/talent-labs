import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';

/**
 * Reports whether the element has entered the viewport. Latches on first
 * sight unless `latch` is false, then it tracks entering and leaving.
 */
export function useInView<T extends Element>(
    options?: IntersectionObserverInit & { latch?: boolean },
): [RefObject<T | null>, boolean] {
    const ref = useRef<T | null>(null);
    const [inView, setInView] = useState(false);
    const { root = null, rootMargin, threshold, latch = true } = options ?? {};

    useEffect(() => {
        const node = ref.current;

        if (!node || typeof IntersectionObserver === 'undefined') {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry) {
                    return;
                }

                if (!latch) {
                    setInView(entry.isIntersecting);

                    return;
                }

                if (entry.isIntersecting) {
                    setInView(true);
                    observer.disconnect();
                }
            },
            { root, rootMargin, threshold },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [root, rootMargin, threshold, latch]);

    return [ref, inView];
}
