import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/i18n/i18n-provider';

type DataCardState = 'ready' | 'loading' | 'empty' | 'error';

export function DataCard({
    title,
    subtitle,
    actions,
    footer,
    state = 'ready',
    onRetry,
    className,
    children,
}: {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
    footer?: ReactNode;
    state?: DataCardState;
    onRetry?: () => void;
    className?: string;
    children?: ReactNode;
}) {
    const { t } = useT();

    return (
        <Card tone="light" as="section" className={className}>
            <div className="mb-5 flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <h2 className="text-card-title-sm md:text-card-title">
                        {title}
                    </h2>
                    {subtitle && (
                        <p className="mt-1 text-body text-muted">{subtitle}</p>
                    )}
                </div>
                {actions && (
                    <div className="flex shrink-0 items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
            {state === 'loading' && (
                <div
                    className="flex flex-col gap-3"
                    role="status"
                    aria-busy="true"
                    aria-label={t('common.loading')}
                >
                    <Skeleton shape="block" />
                    <Skeleton shape="line" />
                    <Skeleton shape="line" className="w-2/3" />
                </div>
            )}
            {state === 'empty' && (
                <EmptyState icon={Inbox} title={t('states.empty.title')} />
            )}
            {state === 'error' && <ErrorState onRetry={onRetry} />}
            {state === 'ready' && children}
            {footer && state === 'ready' && (
                <div className="mt-5 border-t border-hairline pt-4">
                    {footer}
                </div>
            )}
        </Card>
    );
}
