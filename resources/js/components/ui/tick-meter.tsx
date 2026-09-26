import { METER_FILLS, METER_TRACKS } from '@/components/ui/progress-bar';
import type { MeterTone } from '@/components/ui/progress-bar';
import { cn } from '@/lib/utils';

export function TickMeter({
    total,
    filled,
    tone = 'accent',
    label,
    className,
}: {
    total: number;
    filled: number;
    tone?: MeterTone;
    label: string;
    className?: string;
}) {
    return (
        <div
            role="meter"
            aria-label={label}
            aria-valuenow={filled}
            aria-valuemin={0}
            aria-valuemax={total}
            className={cn('flex h-6 items-end gap-0.5', className)}
        >
            {Array.from({ length: total }, (_, index) => (
                <i
                    key={index}
                    aria-hidden="true"
                    className={cn(
                        'h-full flex-1 rounded-full',
                        index < filled ? METER_FILLS[tone] : METER_TRACKS[tone],
                    )}
                />
            ))}
        </div>
    );
}
