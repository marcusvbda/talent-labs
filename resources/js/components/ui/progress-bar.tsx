import { cn } from '@/lib/utils';

export type MeterTone = 'accent' | 'ink' | 'on-dark';

const TRACKS: Record<MeterTone, string> = {
    accent: 'bg-accent-soft',
    ink: 'bg-hairline',
    'on-dark': 'bg-dark-line',
};

const FILLS: Record<MeterTone, string> = {
    accent: 'bg-accent',
    ink: 'bg-ink',
    'on-dark': 'bg-white',
};

export function ProgressBar({
    value,
    max = 100,
    tone = 'accent',
    label,
    className,
}: {
    value: number;
    max?: number;
    tone?: MeterTone;
    label: string;
    className?: string;
}) {
    const percent =
        max > 0 ? Math.min(100, Math.max(0, (value / max) * 100)) : 0;

    return (
        <div
            role="progressbar"
            aria-label={label}
            aria-valuenow={value}
            aria-valuemin={0}
            aria-valuemax={max}
            className={cn(
                'h-1.5 w-full overflow-hidden rounded-full',
                TRACKS[tone],
                className,
            )}
        >
            <div
                className={cn('h-full rounded-full', FILLS[tone])}
                style={{ width: `${percent}%` }}
            />
        </div>
    );
}

export { FILLS as METER_FILLS, TRACKS as METER_TRACKS };
