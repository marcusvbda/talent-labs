import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { useFlashToasts } from '@/lib/use-flash-toasts';
import type { SharedProps } from '@/types/shared';

export function PublicLayout({
    header,
    footer,
    children,
}: {
    header?: ReactNode;
    footer?: ReactNode;
    children: ReactNode;
}) {
    const { locale } = usePage<SharedProps>().props;

    useFlashToasts();

    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);

    return (
        <div className="flex min-h-screen w-full flex-col bg-shell text-ink">
            {header}
            <main className="mx-auto w-full max-w-shell flex-1 px-4 md:px-8 desk:px-shell-x">
                {children}
            </main>
            {footer}
        </div>
    );
}
