import { Pause, Play } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { LiveDot } from '@/components/ui/live-dot';
import { useLiveSending } from '@/data/hooks/use-live-sending';
import {
    usePauseSending,
    useResumeSending,
} from '@/data/hooks/use-pause-sending';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';

/** Free plan: sending is automatic, the client can only pause and resume. */
export function AutoBanner({
    left,
    paused,
}: {
    left: number;
    paused: boolean;
}) {
    const { t, plural } = useT();
    const format = useFormat();
    const { data } = useLiveSending();
    const pause = usePauseSending();
    const resume = useResumeSending();
    const isPaused = data ? data.state === 'paused' : paused;
    const next = !isPaused && data?.nextSendAt ? data.nextSendAt : null;

    return (
        <div
            role="status"
            className="flex flex-wrap items-center justify-between gap-3 rounded-tile bg-accent-soft p-card-sm text-body text-accent-deep"
        >
            <span className="flex items-center gap-3">
                {!isPaused && <LiveDot />}
                {isPaused
                    ? plural('jobs.auto_banner_paused', left)
                    : next
                      ? plural('jobs.auto_banner', left, {
                            time: format.date(next, { timeStyle: 'short' }),
                        })
                      : plural('jobs.auto_banner_idle', left)}
            </span>
            {isPaused ? (
                <Button
                    size="sm"
                    iconLeft={Play}
                    loading={resume.isPending}
                    onClick={() => resume.mutate()}
                >
                    {t('jobs.resume')}
                </Button>
            ) : (
                <Button
                    size="sm"
                    variant="secondary-tile"
                    iconLeft={Pause}
                    loading={pause.isPending}
                    onClick={() => pause.mutate()}
                >
                    {t('jobs.pause')}
                </Button>
            )}
        </div>
    );
}
