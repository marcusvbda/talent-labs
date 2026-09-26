import type { LucideIcon } from 'lucide-react';
import { forwardRef } from 'react';
import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type IconButtonSize = 60 | 46;
type IconButtonBg = 'tile' | 'white' | 'dark';

const RINGS: Record<IconButtonBg, string> = {
    tile: 'focus-visible:focus-ring',
    white: 'focus-visible:focus-ring',
    dark: 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white',
};

const SIZES: Record<IconButtonSize, string> = {
    60: 'size-control-lg',
    46: 'size-control-sm',
};

const BGS: Record<IconButtonBg, string> = {
    tile: 'bg-tile hover:bg-hairline active:bg-hairline',
    white: 'bg-card hover:bg-tile active:bg-tile',
    dark: 'bg-dark-2 text-white hover:bg-dark-line active:bg-dark-line',
};

type IconButtonProps = Omit<
    ButtonHTMLAttributes<HTMLButtonElement>,
    'children' | 'aria-label'
> & {
    icon: LucideIcon;
    label: string;
    size?: IconButtonSize;
    bg?: IconButtonBg;
    dot?: boolean;
};

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(
    function IconButton(
        {
            icon: Icon,
            label,
            size = 46,
            bg = 'tile',
            dot = false,
            className,
            type = 'button',
            ...props
        },
        ref,
    ) {
        return (
            <button
                ref={ref}
                type={type}
                aria-label={label}
                className={cn(
                    'relative inline-grid shrink-0 place-items-center rounded-full text-ink transition-colors disabled:pointer-events-none disabled:opacity-50',
                    SIZES[size],
                    BGS[bg],
                    RINGS[bg],
                    className,
                )}
                {...props}
            >
                <Icon size={20} strokeWidth={1.8} aria-hidden="true" />
                {dot ? (
                    <span
                        aria-hidden="true"
                        className="absolute top-4 right-4 size-2.5 rounded-full border-2 border-card bg-accent"
                    />
                ) : null}
            </button>
        );
    },
);
