import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type CardTone = 'light' | 'dark' | 'hero-accent';
type CardPadding = 'default' | 'none';
type CardElement = 'div' | 'section' | 'article' | 'li' | 'aside';

const TONES: Record<CardTone, string> = {
    light: 'bg-card text-ink',
    dark: 'bg-dark text-white',
    'hero-accent': 'relative isolate overflow-hidden bg-hero text-ink',
};

const PADDINGS: Record<CardPadding, string> = {
    default: 'p-card-sm md:p-card',
    none: 'p-0',
};

export function Card({
    tone,
    padding = 'default',
    as: Component = 'div',
    className,
    children,
}: {
    tone: CardTone;
    padding?: CardPadding;
    as?: CardElement;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Component
            className={cn(
                'rounded-card-sm md:rounded-card',
                TONES[tone],
                PADDINGS[padding],
                className,
            )}
        >
            {tone === 'hero-accent' && (
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-16 -right-16 -z-10 size-56 hero-circle"
                />
            )}
            {children}
        </Component>
    );
}
