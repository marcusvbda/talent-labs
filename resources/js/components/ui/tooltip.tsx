import { useId } from 'react';
import type { ReactElement, ReactNode } from 'react';
import { cloneElement } from 'react';

export function Tooltip({
    content,
    children,
}: {
    content: ReactNode;
    children: ReactElement<{ 'aria-describedby'?: string }>;
}) {
    const id = useId();

    return (
        <span className="group/tooltip relative inline-flex">
            {cloneElement(children, { 'aria-describedby': id })}
            <span
                id={id}
                role="tooltip"
                className="pointer-events-none absolute bottom-full left-1/2 z-40 mb-2 w-max max-w-64 -translate-x-1/2 rounded-checkbox bg-ink px-3 py-2 text-chip text-white opacity-0 transition-opacity duration-150 group-focus-within/tooltip:opacity-100 group-hover/tooltip:opacity-100"
            >
                {content}
            </span>
        </span>
    );
}
