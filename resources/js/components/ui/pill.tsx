import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type PillTone = 'white-on-accent' | 'tile' | 'dark';

const TONES: Record<PillTone, string> = {
    'white-on-accent': 'bg-white text-ink',
    tile: 'bg-tile text-ink',
    dark: 'bg-dark-2 text-white',
};

export function Pill({
    icon: Icon,
    tone,
    className,
    children,
}: {
    icon?: LucideIcon;
    tone: PillTone;
    className?: string;
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 rounded-full px-4 py-2 text-label-sm font-medium',
                TONES[tone],
                className,
            )}
        >
            {Icon && (
                <Icon
                    aria-hidden="true"
                    strokeWidth={1.8}
                    className="size-4 shrink-0"
                />
            )}
            {children}
        </span>
    );
}
