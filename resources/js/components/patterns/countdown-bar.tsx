import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { ProgressBar } from '@/components/ui/progress-bar';
import { useT } from '@/i18n/i18n-provider';

const formatClock = (ms: number): string => {
    const total = Math.max(0, Math.ceil(ms / 1000));
    const seconds = String(total % 60).padStart(2, '0');

    return `${Math.floor(total / 60)}:${seconds}`;
};

/** Display-only countdown: ticks locally, never fetches. */
export function CountdownBar({
    startsAt,
    endsAt,
    trailing,
}: {
    startsAt: string;
    endsAt: string;
    trailing?: ReactNode;
}) {
    const { t } = useT();
    const [now, setNow] = useState(() => Date.now());

    const start = new Date(startsAt).getTime();
    const end = new Date(endsAt).getTime();
    const valid = Number.isFinite(start) && Number.isFinite(end);
    const finished = !valid || now >= end;

    useEffect(() => {
        if (finished) {
            return;
        }

        const id = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(id);
    }, [finished]);

    const span = valid ? Math.max(1, end - start) : 1;
    const elapsed = valid ? Math.min(span, Math.max(0, now - start)) : 0;
    const time = formatClock(valid ? end - now : 0);
    const text = t('live.next_in', { time });
    const [before, after] = text.split(time);

    return (
        <div className="flex items-center gap-4 text-body text-dark-muted">
            <span>
                {before}
                <b className="font-semibold text-white">{time}</b>
                {after}
            </span>
            <ProgressBar
                tone="on-dark-accent"
                value={elapsed}
                max={span}
                label={text}
                className="flex-1"
            />
            {trailing ? <span className="shrink-0">{trailing}</span> : null}
        </div>
    );
}
