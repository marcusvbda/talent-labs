import { Pause, Radio } from 'lucide-react';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { CountdownBar } from '@/components/patterns/countdown-bar';
import { DarkCard } from '@/components/patterns/dark-card';
import { LiveStepper } from '@/components/patterns/live-stepper';
import { QueueRow } from '@/components/patterns/queue-row';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { LiveDot } from '@/components/ui/live-dot';
import { useT } from '@/i18n/i18n-provider';
import type { Locale } from '@/types/shared';

export type LiveQueueItem = {
    company: string;
    title: string;
    meta: string;
    language: Locale;
    eta: string;
};

const STEP_KEYS = ['validating', 'adapting', 'attaching', 'sending', 'sent'];

export function LiveSendingCard({
    company,
    title,
    meta,
    language,
    position,
    limit,
    activeStep,
    startsAt,
    endsAt,
    spacing,
    queue,
    queuedCount,
    minutes,
}: {
    company: string;
    title: string;
    meta: string;
    language: Locale;
    position: number;
    limit: number;
    activeStep: number;
    startsAt: string;
    endsAt: string;
    spacing: { min: number; max: number };
    queue: LiveQueueItem[];
    queuedCount: number;
    minutes: number;
}) {
    const { t } = useT();
    const steps = STEP_KEYS.map((key) => ({
        key,
        label: t(`dashboard.live.step.${key}`),
    }));

    return (
        <DarkCard
            icon={Radio}
            title={t('dashboard.live.title')}
            subtitle={t('dashboard.live.subtitle')}
            actions={
                <>
                    <span className="inline-flex items-center gap-2.5 rounded-full bg-dark-2 px-4 py-2.5 text-label-sm font-medium">
                        <LiveDot />
                        {t('dashboard.live.badge')}
                    </span>
                    <Button
                        variant="ghost-on-dark"
                        size="sm"
                        iconLeft={Pause}
                        className="border border-dark-line"
                    >
                        {t('dashboard.live.pause')}
                    </Button>
                </>
            }
        >
            <div className="rounded-panel bg-dark-2 p-5">
                <div className="flex items-center gap-4">
                    <CompanyLogo
                        name={company}
                        size="lg"
                        tint="bg-disc-orange"
                    />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-row-title">{title}</p>
                        <p className="truncate text-body text-dark-muted">
                            {meta}
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-3 max-md:hidden">
                        <Chip variant="language">{language.toUpperCase()}</Chip>
                        <span className="text-label-sm text-dark-muted">
                            {t('dashboard.live.position', {
                                position,
                                limit,
                            })}
                        </span>
                    </div>
                </div>
                <div className="mt-7">
                    <LiveStepper
                        steps={steps}
                        activeIndex={activeStep}
                        subStep={t('dashboard.live.sub_step')}
                    />
                </div>
                <div className="mt-6">
                    <CountdownBar
                        startsAt={startsAt}
                        endsAt={endsAt}
                        trailing={t('dashboard.live.spacing', spacing)}
                    />
                </div>
            </div>
            <div className="mx-0.5 mt-7 mb-3 flex items-center justify-between gap-4 text-body text-dark-muted">
                <span>
                    <b className="font-medium text-white">
                        {t('dashboard.live.up_next')}
                    </b>{' '}
                    · {t('dashboard.live.queued', { count: queuedCount })}
                </span>
                <span className="text-right">
                    {t('dashboard.live.pace', { minutes })}
                </span>
            </div>
            <div className="flex flex-col gap-2.5">
                {queue.map((item) => (
                    <QueueRow
                        key={item.title}
                        {...item}
                        eta={t('dashboard.live.eta', { time: item.eta })}
                    />
                ))}
            </div>
        </DarkCard>
    );
}
