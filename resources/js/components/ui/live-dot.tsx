import { cn } from '@/lib/utils';

export function LiveDot({ className }: { className?: string }) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-block size-2.5 shrink-0 animate-live-pulse rounded-full bg-accent',
                className,
            )}
        />
    );
}
