import { cn } from '@/lib/utils';

const TINTS = [
    'bg-disc-orange',
    'bg-disc-neutral',
    'bg-disc-green',
    'bg-disc-red',
] as const;

const SIZES = {
    sm: 'size-11 rounded-logo-sm text-label-sm',
    md: 'size-13 rounded-logo text-label',
    lg: 'size-14 rounded-logo-lg text-label',
} as const;

const hash = (value: string) => {
    let result = 0;

    for (const char of value) {
        result = (result * 31 + (char.codePointAt(0) ?? 0)) >>> 0;
    }

    return result;
};

const initials = (name: string) => {
    const words = name.trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    const letters =
        words.length === 1
            ? Array.from(words[0]).slice(0, 2)
            : [Array.from(words[0])[0], Array.from(words[1])[0]];

    return letters.join('').toUpperCase();
};

export function CompanyLogo({
    name,
    size = 'md',
    tint: forcedTint,
    className,
}: {
    name: string;
    size?: keyof typeof SIZES;
    tint?: (typeof TINTS)[number];
    className?: string;
}) {
    const clean = name.trim();
    const tint = forcedTint ?? TINTS[hash(clean.toLowerCase()) % TINTS.length];

    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-flex shrink-0 items-center justify-center font-semibold select-none',
                tint,
                SIZES[size],
                'text-ink',
                className,
            )}
        >
            {initials(clean)}
        </span>
    );
}
