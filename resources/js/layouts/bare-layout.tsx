import type { ReactNode } from 'react';
import { useFlashToasts } from '@/lib/use-flash-toasts';
import { cn } from '@/lib/utils';

export function BareLayout({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    useFlashToasts();

    return (
        <div className="flex min-h-screen w-full flex-col items-center bg-shell text-ink">
            <main
                className={cn(
                    'w-full max-w-6xl flex-1 px-4 py-8 md:px-8',
                    className,
                )}
            >
                {children}
            </main>
        </div>
    );
}
