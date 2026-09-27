import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const RADII = {
    tile: 'rounded-tile',
    card: 'rounded-card',
    'card-sm': 'rounded-card-sm',
} as const;

export function LockOverlay({
    locked,
    title,
    description,
    actions,
    radius = 'tile',
    children,
}: {
    locked: boolean;
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
    radius?: keyof typeof RADII;
    children: ReactNode;
}) {
    if (!locked) {
        return <>{children}</>;
    }

    // Both layers share one grid cell, so the overlay can grow the card
    // instead of overflowing it.
    return (
        <div className="grid">
            <div
                inert
                aria-hidden="true"
                className="col-start-1 row-start-1 min-w-0"
            >
                {children}
            </div>
            <div
                role="group"
                aria-label={title}
                className={cn(
                    'col-start-1 row-start-1 flex min-w-0 flex-col items-center justify-center gap-2 bg-scrim p-4 text-center backdrop-blur-xs',
                    RADII[radius],
                )}
            >
                <span className="flex size-control-sm shrink-0 items-center justify-center rounded-full bg-ink text-white">
                    <Lock aria-hidden="true" size={20} strokeWidth={1.8} />
                </span>
                <p className="text-label font-medium">{title}</p>
                <p className="text-label-sm text-muted">{description}</p>
                {actions && (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
        </div>
    );
}

export type LockOverlayRadius = keyof typeof RADII;
