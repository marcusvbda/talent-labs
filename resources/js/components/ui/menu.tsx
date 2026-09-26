import {
    Menu as HeadlessMenu,
    MenuButton,
    MenuItem,
    MenuItems,
} from '@headlessui/react';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { TriangleAlert } from 'lucide-react';
import { Fragment } from 'react';
import type { ReactElement } from 'react';
import { cn } from '@/lib/utils';

export type MenuEntry = {
    label: string;
    icon?: LucideIcon;
    danger?: boolean;
} & (
    | { onSelect?: () => void; href?: never }
    | { href: string; onSelect?: never }
);

const ITEM_BASE =
    'flex w-full cursor-pointer items-center gap-3 rounded-checkbox px-4 py-3 text-left text-body select-none focus-visible:focus-ring';

const ITEM_TONES = {
    default: 'text-ink data-focus:bg-tile',
    danger: 'text-danger-text data-focus:bg-danger-bg',
};

export function Menu({
    trigger,
    items,
}: {
    /** A single focusable element (e.g. Button); it receives the ARIA wiring. */
    trigger: ReactElement;
    items: MenuEntry[];
}) {
    return (
        <HeadlessMenu>
            <MenuButton as={Fragment}>{trigger}</MenuButton>
            <MenuItems
                transition
                anchor={{ to: 'bottom start', gap: 8, padding: 16 }}
                className="z-30 w-56 max-w-[calc(100vw-2rem)] rounded-row bg-card p-2 shadow-shell outline-2 outline-hairline transition duration-150 focus:outline-hairline data-closed:opacity-0"
            >
                {items.map((item, index) => {
                    const Icon =
                        item.icon ?? (item.danger ? TriangleAlert : null);
                    const className = cn(
                        ITEM_BASE,
                        item.danger ? ITEM_TONES.danger : ITEM_TONES.default,
                    );
                    const content = (
                        <>
                            {Icon ? (
                                <Icon
                                    aria-hidden="true"
                                    size={20}
                                    strokeWidth={1.8}
                                    className="shrink-0"
                                />
                            ) : null}
                            <span className="truncate">{item.label}</span>
                        </>
                    );

                    return (
                        <MenuItem key={`${item.label}-${index}`}>
                            {item.href !== undefined ? (
                                <Link href={item.href} className={className}>
                                    {content}
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    onClick={item.onSelect}
                                    className={className}
                                >
                                    {content}
                                </button>
                            )}
                        </MenuItem>
                    );
                })}
            </MenuItems>
        </HeadlessMenu>
    );
}
