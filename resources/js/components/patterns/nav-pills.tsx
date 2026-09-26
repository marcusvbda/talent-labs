import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type NavPillItem = {
    key: string;
    label: string;
    href?: string;
    active?: boolean;
    disabled?: boolean;
};

const BASE =
    'inline-flex items-center rounded-full px-5.5 py-3 lg:px-4 text-label whitespace-nowrap transition-colors focus-visible:focus-ring xl:px-5.5';

const TONES = {
    active: 'bg-ink text-white',
    normal: 'text-ink hover:bg-tile',
    disabled: 'cursor-not-allowed text-muted opacity-60',
};

export function NavPills({ items }: { items: NavPillItem[] }) {
    return (
        <ul className="flex h-control-lg [scrollbar-width:none] items-center gap-1 overflow-x-auto rounded-full bg-card p-1.5 lg:overflow-visible [&::-webkit-scrollbar]:hidden">
            {items.map((item) => {
                const inactive = item.disabled || item.href === undefined;
                const tone = item.active
                    ? TONES.active
                    : inactive
                      ? TONES.disabled
                      : TONES.normal;

                return (
                    <li key={item.key} className="shrink-0">
                        {inactive ? (
                            <span
                                aria-disabled="true"
                                aria-current={item.active ? 'page' : undefined}
                                className={cn(BASE, tone)}
                            >
                                {item.label}
                            </span>
                        ) : (
                            <Link
                                href={item.href as string}
                                aria-current={item.active ? 'page' : undefined}
                                className={cn(BASE, tone)}
                            >
                                {item.label}
                            </Link>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
