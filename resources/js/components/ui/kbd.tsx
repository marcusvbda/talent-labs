import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Kbd({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <kbd
            className={cn(
                'inline-flex h-6 min-w-6 items-center justify-center rounded-checkbox bg-tile px-1.5 text-chip text-muted',
                className,
            )}
        >
            {children}
        </kbd>
    );
}
