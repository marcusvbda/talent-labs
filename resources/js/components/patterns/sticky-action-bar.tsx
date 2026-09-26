import type { ReactNode } from 'react';

export const StickyActionBar = ({ children }: { children: ReactNode }) => (
    <div className="fixed inset-x-0 bottom-0 z-30 flex items-center justify-end gap-3 border-t border-hairline bg-card px-card-sm pt-3 pb-[env(safe-area-inset-bottom)] md:static md:z-auto md:border-t-0 md:bg-transparent md:p-0">
        {children}
    </div>
);
