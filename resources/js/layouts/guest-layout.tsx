import type { ReactNode } from 'react';
import { Logo } from '@/components/patterns/logo';
import { Card } from '@/components/ui/card';
import { useFlashToasts } from '@/lib/use-flash-toasts';

export function GuestLayout({ children }: { children: ReactNode }) {
    useFlashToasts();

    return (
        <div className="flex min-h-screen w-full items-center justify-center bg-shell px-4 py-8 text-ink">
            <main className="flex w-full max-w-md min-w-0 flex-col gap-gap">
                <Card tone="light" className="flex flex-col gap-6">
                    <Logo size="md" />
                    {children}
                </Card>
            </main>
        </div>
    );
}
