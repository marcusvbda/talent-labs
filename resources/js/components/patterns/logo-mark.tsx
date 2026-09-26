import type { SVGProps } from 'react';

// The only allowed raw-hex exception: the fallback of --logo-dot (brand accent).
export const LogoMark = (props: SVGProps<SVGSVGElement>) => (
    <svg viewBox="0 0 48 48" aria-hidden="true" {...props}>
        <path
            d="M13 6h8.5v23.2c0 4.3 2 6.3 6.2 6.3H31V44h-4.2C17.7 44 13 39.3 13 30.6Z"
            fill="currentColor"
        />
        <rect
            x="6.5"
            y="15"
            width="23"
            height="7.5"
            rx="1.2"
            fill="currentColor"
        />
        <circle cx="36.5" cy="10" r="6.5" fill="var(--logo-dot, #F26A1B)" />
    </svg>
);
