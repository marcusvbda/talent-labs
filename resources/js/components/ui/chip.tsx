import { TrendingDown, TrendingUp } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ChipVariant = 'stack' | 'language' | 'plan' | 'delta-up' | 'delta-down';

const VARIANTS: Record<ChipVariant, string> = {
    stack: 'bg-tile text-muted',
    language: 'bg-ink text-white',
    plan: 'bg-accent-soft text-accent-deep',
    'delta-up': 'bg-success-bg text-success-text',
    'delta-down': 'bg-danger-bg text-danger-text',
};

export function Chip({
    variant,
    className,
    children,
}: {
    variant: ChipVariant;
    className?: string;
    children: ReactNode;
}) {
    const Icon =
        variant === 'delta-up'
            ? TrendingUp
            : variant === 'delta-down'
              ? TrendingDown
              : null;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-chip',
                VARIANTS[variant],
                className,
            )}
        >
            {Icon && (
                <Icon
                    aria-hidden="true"
                    strokeWidth={1.8}
                    className="size-3.5 shrink-0"
                />
            )}
            {children}
        </span>
    );
}
