import { cn } from '@/lib/utils';

type SkeletonShape = 'block' | 'line' | 'circle';

const SHAPES: Record<SkeletonShape, string> = {
    block: 'h-24 w-full rounded-tile',
    line: 'h-4 w-full rounded-full',
    circle: 'size-12 rounded-full',
};

export function Skeleton({
    shape,
    className,
}: {
    shape: SkeletonShape;
    className?: string;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'block animate-shimmer bg-hairline',
                SHAPES[shape],
                className,
            )}
        />
    );
}
