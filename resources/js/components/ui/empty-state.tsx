import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center gap-3 px-4 py-10 text-center',
                className,
            )}
        >
            {Icon && (
                <span className="flex size-14 items-center justify-center rounded-full bg-tile text-ink">
                    <Icon aria-hidden="true" strokeWidth={1.8} size={24} />
                </span>
            )}
            <h3 className="text-card-title-sm text-ink">{title}</h3>
            {description && (
                <p className="max-w-sm text-body text-muted">{description}</p>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
