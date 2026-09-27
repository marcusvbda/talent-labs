import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export const FilterBar = ({
    wrap = false,
    children,
}: {
    /** Wrap onto several lines instead of scrolling sideways. */
    wrap?: boolean;
    children: ReactNode;
}) => (
    <div
        className={cn(
            'flex max-w-full gap-2',
            wrap ? 'flex-wrap items-start' : 'items-center overflow-x-auto',
        )}
    >
        {children}
    </div>
);
