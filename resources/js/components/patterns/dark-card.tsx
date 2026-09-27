import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';

export function DarkCard({
    title,
    subtitle,
    actions,
    icon: Icon,
    className,
    children,
}: {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
    icon?: LucideIcon;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card
            tone="dark"
            as="section"
            className={`flex h-full flex-col ${className ?? ''}`}
        >
            <div className="mb-5 flex items-center justify-between gap-4">
                {Icon && (
                    <span className="inline-grid size-control-sm shrink-0 place-items-center rounded-full bg-white text-ink">
                        <Icon size={20} strokeWidth={1.8} aria-hidden="true" />
                    </span>
                )}
                <div className="min-w-0 flex-1">
                    <h2 className="text-card-title-sm md:text-card-title">
                        {title}
                    </h2>
                    {subtitle && (
                        <p className="mt-1 text-body text-dark-muted">
                            {subtitle}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="flex shrink-0 items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
            <div className="flex flex-1 flex-col">{children}</div>
        </Card>
    );
}
