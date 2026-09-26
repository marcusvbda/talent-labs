import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types/shared';
import { LogoMark } from './logo-mark';

type LogoVariant = 'full' | 'mark';
type LogoTone = 'default' | 'inverse' | 'on-accent';
type LogoSize = 'sm' | 'md' | 'lg';

const toneClasses: Record<LogoTone, string> = {
    default: 'text-ink',
    inverse: 'text-card',
    'on-accent': 'text-ink [--logo-dot:var(--color-card)]',
};

const markSizes: Record<LogoSize, string> = {
    sm: 'size-6',
    md: 'size-9',
    lg: 'size-14',
};

const wordmarkSizes: Record<LogoSize, string> = {
    sm: 'text-label',
    md: 'text-card-title-sm',
    lg: 'text-card-title',
};

export function Logo({
    variant = 'full',
    tone = 'default',
    size = 'md',
    className,
}: {
    variant?: LogoVariant;
    tone?: LogoTone;
    size?: LogoSize;
    className?: string;
}) {
    const { app } = usePage<SharedProps>().props;
    const [regular, bold] = app.brand.wordmark;

    return (
        <span
            role="img"
            aria-label={app.brand.name}
            className={cn(
                'inline-flex items-center gap-2',
                toneClasses[tone],
                className,
            )}
        >
            <LogoMark className={cn('shrink-0', markSizes[size])} />
            {variant === 'full' && (
                <span
                    aria-hidden="true"
                    className={cn(
                        'font-normal tracking-wordmark',
                        wordmarkSizes[size],
                    )}
                >
                    {regular}
                    <span className="font-wordmark">{bold}</span>
                </span>
            )}
        </span>
    );
}
