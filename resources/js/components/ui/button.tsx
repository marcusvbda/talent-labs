import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { forwardRef } from 'react';
import type { ButtonHTMLAttributes, MouseEvent, ReactNode, Ref } from 'react';
import { cn } from '@/lib/utils';
import { Spinner } from './spinner';

type ButtonVariant =
    | 'primary-ink'
    | 'secondary-tile'
    | 'ghost'
    | 'ghost-on-dark'
    | 'on-accent-white';
type ButtonSize = 'lg' | 'md' | 'sm';

const RING_INK = 'focus-visible:focus-ring';
const RING_WHITE =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white';

const RINGS: Record<ButtonVariant, string> = {
    'primary-ink': RING_INK,
    'secondary-tile': RING_INK,
    ghost: RING_INK,
    'ghost-on-dark': RING_WHITE,
    'on-accent-white': RING_WHITE,
};

const VARIANTS: Record<ButtonVariant, string> = {
    'primary-ink': 'bg-ink text-white hover:bg-dark-2 active:bg-dark',
    'secondary-tile': 'bg-tile text-ink hover:bg-hairline active:bg-hairline',
    ghost: 'bg-transparent text-ink hover:bg-tile active:bg-hairline',
    'ghost-on-dark':
        'bg-transparent text-white hover:bg-dark-2 active:bg-dark-line',
    'on-accent-white': 'bg-card text-ink hover:bg-tile active:bg-hairline',
};

const SIZES: Record<ButtonSize, string> = {
    lg: 'h-control-lg px-7',
    md: 'h-control-md px-6',
    sm: 'h-control-xs px-5',
};

const SPINNER_TONES = {
    'primary-ink': 'white',
    'secondary-tile': 'ink',
    ghost: 'ink',
    'ghost-on-dark': 'white',
    'on-accent-white': 'accent',
} as const;

const isButtonEvent = (
    event: MouseEvent<Element>,
): event is MouseEvent<HTMLButtonElement> =>
    event.currentTarget instanceof HTMLElement;

type AnchorAttrs = Record<string, string | undefined>;

const pickAnchorAttrs = (props: object): AnchorAttrs =>
    Object.fromEntries(
        Object.entries(props).filter(([key]) => /^(aria|data)-/.test(key)),
    );

type ButtonProps = {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    iconLeft?: LucideIcon;
    iconRight?: LucideIcon;
    fullWidth?: boolean;
    href?: string;
    disabled?: boolean;
    className?: string;
    children: ReactNode;
} & Omit<
    ButtonHTMLAttributes<HTMLButtonElement>,
    'children' | 'className' | 'disabled'
>;

export const Button = forwardRef<HTMLElement, ButtonProps>(function Button(
    {
        variant = 'primary-ink',
        size = 'md',
        loading = false,
        iconLeft: IconLeft,
        iconRight: IconRight,
        fullWidth = false,
        href,
        disabled = false,
        className,
        children,
        type = 'button',
        onClick,
        ...props
    },
    ref,
) {
    const inactive = disabled || loading;

    const classes = cn(
        'inline-flex items-center justify-center gap-2.5 rounded-full text-label font-medium whitespace-nowrap transition-colors',
        RINGS[variant],
        SIZES[size],
        VARIANTS[variant],
        fullWidth && 'w-full',
        inactive && 'pointer-events-none opacity-50',
        className,
    );

    const content = (
        <>
            {loading ? (
                <Spinner size="sm" tone={SPINNER_TONES[variant]} />
            ) : IconLeft ? (
                <IconLeft size={20} strokeWidth={1.8} aria-hidden="true" />
            ) : null}
            {children}
            {IconRight ? (
                <IconRight size={20} strokeWidth={1.8} aria-hidden="true" />
            ) : null}
        </>
    );

    if (href !== undefined) {
        const anchorProps = pickAnchorAttrs(props);

        const guard = (event: MouseEvent<Element>) => {
            if (inactive) {
                event.preventDefault();

                return;
            }

            if (isButtonEvent(event)) {
                onClick?.(event);
            }
        };

        return (
            <Link
                {...anchorProps}
                ref={ref as Ref<HTMLAnchorElement>}
                href={href}
                aria-disabled={inactive || undefined}
                aria-busy={loading || undefined}
                tabIndex={inactive ? -1 : undefined}
                className={classes}
                onClick={guard}
            >
                {content}
            </Link>
        );
    }

    return (
        <button
            ref={ref as Ref<HTMLButtonElement>}
            type={type}
            aria-disabled={inactive || undefined}
            aria-busy={loading || undefined}
            className={classes}
            onClick={(event) => {
                if (inactive) {
                    event.preventDefault();

                    return;
                }

                onClick?.(event);
            }}
            {...props}
        >
            {content}
        </button>
    );
});
