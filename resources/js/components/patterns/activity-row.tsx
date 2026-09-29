import type { ReactNode } from 'react';
import { StatusDisc } from '@/components/ui/status-disc';
import { useFormat } from '@/lib/format';

export type ActivityStatus = 'sending' | 'done' | 'failed' | 'waiting';

const TINTS = {
    sending: 'orange',
    done: 'green',
    failed: 'red',
    waiting: 'neutral',
} as const;

export function ActivityRow({
    status,
    title,
    subtitle,
    time,
    timeFormat = 'relative',
    trailingAction,
}: {
    status: ActivityStatus;
    title: string;
    subtitle: string;
    time: string;
    timeFormat?: 'relative' | 'clock';
    trailingAction?: ReactNode;
}) {
    const format = useFormat();
    const date = new Date(time);
    const valid = Number.isFinite(date.getTime());

    return (
        <div className="flex items-center gap-3">
            <span key={status} className="animate-fade-in">
                <StatusDisc status={status} tint={TINTS[status]} />
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-label text-ink">{title}</p>
                <p className="truncate text-body text-muted">{subtitle}</p>
            </div>
            <time
                dateTime={valid ? date.toISOString() : undefined}
                className="shrink-0 text-label-sm text-muted"
            >
                {!valid
                    ? ''
                    : timeFormat === 'clock'
                      ? format.date(time, {
                            hour: '2-digit',
                            minute: '2-digit',
                            hourCycle: 'h23',
                        })
                      : format.relativeTime(time)}
            </time>
            {trailingAction ? (
                <div className="shrink-0">{trailingAction}</div>
            ) : null}
        </div>
    );
}
