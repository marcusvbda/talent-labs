import type { MouseEvent } from 'react';

export const MOUNT_ALL_EVENT = 'landing:mount-all';

/** Asks every lazy section to mount now, so anchors resolve against real heights. */
export const requestMountAll = (): void => {
    window.dispatchEvent(new Event(MOUNT_ALL_EVENT));
};

/** Smooth (or instant, under reduced motion) in-page scroll; falls through when the target is missing. */
export const scrollToHash = (hash: string): boolean => {
    const target = document.getElementById(hash.slice(1));

    if (!target) {
        return false;
    }

    requestMountAll();

    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

    target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth' });
    history.pushState(null, '', hash);

    return true;
};

export const anchorHash = (event: MouseEvent<HTMLElement>): string | null => {
    if (!(event.target instanceof Element)) {
        return null;
    }

    return event.target.closest('a[href^="#"]')?.getAttribute('href') ?? null;
};
