import { cn } from '@/lib/utils';

type AvatarSize = 'sm' | 'md' | 'lg';

const SIZES: Record<AvatarSize, string> = {
    sm: 'size-8 text-chip',
    md: 'size-10 text-label-sm',
    lg: 'size-12 text-label',
};

export function Avatar({
    initials,
    size = 'md',
    className,
}: {
    initials: string;
    size?: AvatarSize;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-grid shrink-0 place-items-center rounded-full bg-ink font-medium text-white uppercase',
                SIZES[size],
                className,
            )}
        >
            {initials}
        </span>
    );
}
