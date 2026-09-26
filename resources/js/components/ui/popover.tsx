import {
    Popover as HeadlessPopover,
    PopoverButton,
    PopoverPanel,
} from '@headlessui/react';
import { Fragment } from 'react';
import type { ReactElement, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export const POPOVER_PANEL =
    'z-30 w-72 max-w-[calc(100vw-2rem)] rounded-row bg-card p-card-sm text-body text-ink shadow-shell outline-2 outline-hairline transition duration-150 focus:outline-hairline data-closed:opacity-0';

export function Popover({
    trigger,
    children,
    className,
}: {
    /** A single focusable element (e.g. Button); it receives the ARIA wiring. */
    trigger: ReactElement;
    children: ReactNode;
    className?: string;
}) {
    return (
        <HeadlessPopover>
            <PopoverButton as={Fragment}>{trigger}</PopoverButton>
            <PopoverPanel
                transition
                anchor={{ to: 'bottom start', gap: 8, padding: 16 }}
                className={cn(POPOVER_PANEL, className)}
            >
                {children}
            </PopoverPanel>
        </HeadlessPopover>
    );
}
