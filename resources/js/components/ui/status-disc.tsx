import { Check, Clock, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

type DiscStatus = 'sending' | 'done' | 'failed' | 'waiting' | 'icon';
type DiscTint = 'orange' | 'neutral' | 'green' | 'red';
type DiscSize = 'sm' | 'md' | 'lg';

const TINTS: Record<DiscTint, string> = {
    orange: 'bg-disc-orange text-accent-deep',
    neutral: 'bg-disc-neutral text-muted',
    green: 'bg-disc-green text-success-text',
    red: 'bg-disc-red text-danger-text',
};

const SIZES: Record<DiscSize, string> = {
    sm: 'size-8',
    md: 'size-10',
    lg: 'size-11',
};

const STATUS_ICONS: Record<'done' | 'failed' | 'waiting', LucideIcon> = {
    done: Check,
    failed: X,
    waiting: Clock,
};

export function StatusDisc({
    status,
    icon,
    tint,
    size = 'md',
    label,
    className,
}: {
    status: DiscStatus;
    icon?: LucideIcon;
    tint: DiscTint;
    size?: DiscSize;
    label?: string;
    className?: string;
}) {
    const { t } = useT();
    const Icon =
        status === 'icon'
            ? icon
            : status === 'sending'
              ? null
              : STATUS_ICONS[status];
    const accessibleLabel =
        label ?? (status === 'icon' ? undefined : t(`status.${status}`));

    return (
        <span
            role={accessibleLabel ? 'img' : undefined}
            aria-label={accessibleLabel}
            aria-hidden={accessibleLabel ? undefined : true}
            className={cn(
                'inline-grid shrink-0 place-items-center rounded-full',
                TINTS[tint],
                SIZES[size],
                className,
            )}
        >
            {status === 'sending' ? (
                <span aria-hidden="true">
                    <Spinner size="sm" tone="accent" />
                </span>
            ) : (
                Icon && (
                    <Icon
                        aria-hidden="true"
                        strokeWidth={1.8}
                        className="size-5"
                    />
                )
            )}
        </span>
    );
}
