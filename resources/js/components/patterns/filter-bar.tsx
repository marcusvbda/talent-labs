import type { ReactNode } from 'react';

export const FilterBar = ({ children }: { children: ReactNode }) => (
    <div className="flex max-w-full items-center gap-2 overflow-x-auto">
        {children}
    </div>
);
