import { Suspense, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { MOUNT_ALL_EVENT } from './scroll-to-hash';
import { useInView } from './use-in-view';

/** Resolves the `--lazy-margin` token; IntersectionObserver rejects `var()`. */
const lazyMargin = (): string => {
    const value =
        typeof document === 'undefined'
            ? ''
            : getComputedStyle(document.documentElement)
                  .getPropertyValue('--lazy-margin')
                  .trim();

    return `${value || '0px'} 0px`;
};

/**
 * Mounts its (React.lazy) children when it nears the viewport or when an
 * anchor asks for every section. The wrapper reserves a fixed min-height and
 * carries the anchor `id`, so nav links work and nothing shifts on mount.
 */
export function LazySection({
    id,
    className,
    children,
}: {
    id?: string;
    /** Min-height tokens for the placeholder. */
    className?: string;
    children: ReactNode;
}) {
    const [rootMargin] = useState(lazyMargin);
    const [ref, near] = useInView<HTMLDivElement>({ rootMargin });
    const [forced, setForced] = useState(false);

    useEffect(() => {
        const mount = () => setForced(true);

        window.addEventListener(MOUNT_ALL_EVENT, mount);

        return () => window.removeEventListener(MOUNT_ALL_EVENT, mount);
    }, []);

    return (
        <div
            ref={ref}
            id={id}
            className={cn('scroll-mt-anchor-offset', className)}
        >
            {near || forced ? (
                <Suspense fallback={null}>{children}</Suspense>
            ) : null}
        </div>
    );
}
