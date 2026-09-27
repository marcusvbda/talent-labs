import type { ReactNode } from 'react';
import { TopBar } from '@/components/patterns/top-bar';
import { useRealtimeCache } from '@/data/realtime/use-realtime-cache';
import { useFixturePlan } from '@/data/hooks/use-fixture-plan';
import { useMainNav } from '@/lib/navigation';
import { useFlashToasts } from '@/lib/use-flash-toasts';
import { cn } from '@/lib/utils';

/** 12-column grid: 2 columns at tablet, 1 on mobile. */
export function AppGrid({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'grid grid-cols-1 gap-gap md:grid-cols-2 lg:grid-cols-12',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function AppLayout({
    children,
    nav: showNav = true,
}: {
    children: ReactNode;
    /** false leaves only logo, bell and user menu (onboarding). */
    nav?: boolean;
}) {
    useFlashToasts();
    useRealtimeCache();
    const mainNav = useMainNav();
    const nav = showNav ? mainNav : [];
    const plan = useFixturePlan();

    return (
        <div className="min-h-screen w-full bg-shell text-ink lg:bg-canvas">
            <div className="mx-auto flex min-h-screen w-full flex-col gap-gap bg-shell px-4 py-5 md:px-8 lg:min-h-0 lg:px-7 lg:py-6 lg:shadow-shell desk:px-shell-x desk:pt-shell-t desk:pb-shell-b">
                <TopBar nav={nav} plan={plan} />
                <main className="flex min-w-0 flex-col gap-gap">
                    {children}
                </main>
            </div>
        </div>
    );
}
