import type { ReactNode } from 'react';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { CountdownBar } from '@/components/patterns/countdown-bar';
import { LiveStepper } from '@/components/patterns/live-stepper';
import { Card } from '@/components/ui/card';
import { Chip } from '@/components/ui/chip';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import { cn } from '@/lib/utils';
import { BetaCta } from './beta-cta';
import { DEMO_JOBS, DEMO_STAGES } from './demo-data';
import { useDemoLoopState, useRegisterDemoInView } from './demo-loop-context';
import { useCountUp } from './use-count-up';
import { useInView } from './use-in-view';
import { useReveal } from './use-reveal';

const Reveal = ({
    index,
    className,
    children,
}: {
    index: number;
    className?: string;
    children: ReactNode;
}) => {
    const reveal = useReveal<HTMLDivElement>(index);

    return (
        <div
            ref={reveal.ref}
            style={reveal.style}
            className={cn(reveal.className, className)}
        >
            {children}
        </div>
    );
};

const DarkTile = ({ label, value }: { label: string; value: number }) => {
    const format = useFormat();
    const [ref, shown] = useCountUp<HTMLSpanElement>(value);

    return (
        <div className="flex flex-col gap-3 rounded-tile bg-dark-2 p-4">
            <span className="text-label-sm text-dark-muted">{label}</span>
            <span ref={ref} className="text-numeral-lg-sm">
                {format.number(shown)}
            </span>
        </div>
    );
};

const LiveDemo = () => {
    const { t } = useT();
    const {
        job,
        stageIndex,
        subStep,
        queue,
        sentToday,
        countdown,
        now,
        cycle,
    } = useDemoLoopState();
    const steps = DEMO_STAGES.map((stage) => ({
        key: stage,
        label: t(`sending.stage.${stage}`),
    }));

    return (
        <div className="flex flex-col gap-6 rounded-panel bg-dark-2 p-5">
            <div className="flex items-center gap-4">
                <CompanyLogo
                    name={job.company}
                    size="lg"
                    tint="bg-disc-orange"
                />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-row-title">{job.title}</p>
                    <p className="truncate text-body text-dark-muted">
                        {job.company}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-3 max-md:hidden">
                    <Chip variant="language">{job.language.toUpperCase()}</Chip>
                    <span className="text-label-sm text-dark-muted">
                        {t('dashboard.live.position', {
                            position: cycle + 1,
                            limit: DEMO_JOBS.length,
                        })}
                    </span>
                </div>
            </div>
            <LiveStepper
                steps={steps}
                activeIndex={
                    stageIndex === steps.length - 1 ? steps.length : stageIndex
                }
                subStep={subStep ? t(`sending.sub.${subStep}`) : undefined}
            />
            <div className="min-h-6">
                {countdown && (
                    <CountdownBar
                        now={now}
                        startsAt={new Date(countdown.startsAt).toISOString()}
                        endsAt={new Date(countdown.endsAt).toISOString()}
                    />
                )}
            </div>
            <div className="grid grid-cols-3 gap-3">
                <DarkTile label={t('dashboard.hero.label')} value={sentToday} />
                <DarkTile
                    label={t('dashboard.hero.queued')}
                    value={queue.length}
                />
                <DarkTile label={t('dashboard.hero.not_delivered')} value={0} />
            </div>
        </div>
    );
};

export function LiveBand({ betaClosed }: { betaClosed: boolean }) {
    const { t } = useT();
    const [ref, inView] = useInView<HTMLDivElement>({ latch: false });

    useRegisterDemoInView(inView);

    return (
        <section aria-labelledby="how-title" className="py-12 md:py-16">
            <Card tone="dark">
                <div className="grid grid-cols-1 items-center gap-gap xl:grid-cols-2">
                    <div className="flex flex-col items-start gap-5">
                        <Reveal index={0}>
                            <p className="text-label text-accent">
                                {t('landing.live.eyebrow')}
                            </p>
                        </Reveal>
                        <Reveal index={1}>
                            <h2
                                id="how-title"
                                className="text-landing-section-sm md:text-landing-section"
                            >
                                {t('landing.live.title')}
                            </h2>
                        </Reveal>
                        <Reveal index={2}>
                            <p className="max-w-2xl text-landing-body text-dark-soft">
                                {t('landing.live.text')}
                            </p>
                        </Reveal>
                        <Reveal index={3}>
                            <BetaCta
                                betaClosed={betaClosed}
                                variant="on-accent-white"
                                size="lg"
                            />
                        </Reveal>
                    </div>
                    <div>
                        <div ref={ref} aria-hidden="true" inert>
                            <LiveDemo />
                        </div>
                        <p className="mt-4 text-label-sm text-dark-muted">
                            {t('landing.demo.caption')}
                        </p>
                    </div>
                </div>
            </Card>
        </section>
    );
}
