import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type NavPillItem = {
    key: string;
    label: string;
    href?: string;
    active?: boolean;
    disabled?: boolean;
    /** Renders a plain `<a>` (in-page anchor) instead of an Inertia link. */
    anchor?: boolean;
};

const BASE =
    'inline-flex items-center rounded-full px-4 py-3 text-label whitespace-nowrap transition-colors focus-visible:focus-ring';

const TONES = {
    active: 'bg-ink text-white',
    normal: 'text-ink hover:bg-tile',
    disabled: 'cursor-not-allowed text-muted',
};

/** Shared by NavPills and MobileNav: whether the item is inert, and its tone. */
export const navItemState = (
    item: NavPillItem,
): { inactive: boolean; tone: keyof typeof TONES } => {
    const inactive = item.disabled === true || item.href === undefined;

    return {
        inactive,
        tone: item.active ? 'active' : inactive ? 'disabled' : 'normal',
    };
};

export function NavPills({ items }: { items: NavPillItem[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <ul className="flex h-control-lg [scrollbar-width:none] items-center gap-1 overflow-x-auto rounded-full bg-card p-1.5 [&::-webkit-scrollbar]:hidden">
            {items.map((item) => {
                const state = navItemState(item);
                const inactive = state.inactive;
                const tone = TONES[state.tone];

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
                        ) : item.anchor ? (
                            <a
                                href={item.href}
                                aria-current={item.active ? 'page' : undefined}
                                className={cn(BASE, tone)}
                            >
                                {item.label}
                            </a>
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
