import { Clock, Radio, Send } from 'lucide-react';
import { ActivityRow } from '@/components/patterns/activity-row';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { CountdownBar } from '@/components/patterns/countdown-bar';
import { DarkCard } from '@/components/patterns/dark-card';
import { DataCard } from '@/components/patterns/data-card';
import { HeroCard } from '@/components/patterns/hero-card';
import { LiveStepper } from '@/components/patterns/live-stepper';
import { QueueRow } from '@/components/patterns/queue-row';
import { StatTile } from '@/components/patterns/stat-tile';
import { Chip } from '@/components/ui/chip';
import { LiveDot } from '@/components/ui/live-dot';
import { Pill } from '@/components/ui/pill';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import {
    DEMO_DAILY_LIMIT,
    DEMO_JOBS,
    DEMO_STAGES,
    DEMO_WEEK_BARS,
    DEMO_WEEK_LABELS,
    QUEUE_GAP_MS,
} from './demo-data';
import { useDemoLoopState, useRegisterDemoInView } from './demo-loop-context';
import { useInView } from './use-in-view';

const LIMIT_TICKS = 25;

const clockOf = (ms: number): string => {
    const total = Math.max(0, Math.ceil(ms / 1000));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
};

const HeroStats = () => {
    const { t } = useT();
    const format = useFormat();
    const { sentToday, queue, countdown, now } = useDemoLoopState();

    return (
        <HeroCard
            label={t('dashboard.hero.label')}
            icon={Send}
            value={String(sentToday)}
            suffix={`/ ${DEMO_DAILY_LIMIT}`}
            bars={[...DEMO_WEEK_BARS, sentToday]}
            barLabels={[...DEMO_WEEK_LABELS, t('dashboard.hero.now')]}
            caption={t('dashboard.hero.caption', {
                queued: queue.length,
                left: DEMO_DAILY_LIMIT - sentToday,
            })}
            stats={[
                {
                    label: t('dashboard.hero.queued'),
                    value: format.number(queue.length),
                },
                {
                    label: t('dashboard.hero.not_delivered'),
                    value: format.number(0),
                },
                {
                    label: t('dashboard.hero.next_send'),
                    value: countdown
                        ? clockOf(countdown.endsAt - now)
                        : t('dashboard.hero.no_next'),
                },
            ]}
        />
    );
};

const LiveSending = () => {
    const { t } = useT();
    const format = useFormat();
    const { job, stageIndex, subStep, queue, countdown, now, cycle } =
        useDemoLoopState();
    const steps = DEMO_STAGES.map((stage) => ({
        key: stage,
        label: t(`sending.stage.${stage}`),
    }));

    return (
        <DarkCard
            icon={Radio}
            title={t('dashboard.live.title')}
            subtitle={t('dashboard.live.subtitle')}
            actions={
                <Pill tone="dark" className="gap-2.5 py-2.5">
                    <LiveDot />
                    {t('dashboard.live.badge')}
                </Pill>
            }
        >
            <div className="rounded-panel bg-dark-2 p-5">
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
                        <Chip variant="language">
                            {job.language.toUpperCase()}
                        </Chip>
                        <span className="text-label-sm text-dark-muted">
                            {t('dashboard.live.position', {
                                position: cycle + 1,
                                limit: DEMO_JOBS.length,
                            })}
                        </span>
                    </div>
                </div>
                <div className="mt-7">
                    <LiveStepper
                        steps={steps}
                        activeIndex={
                            stageIndex === steps.length - 1
                                ? steps.length
                                : stageIndex
                        }
                        subStep={
                            subStep ? t(`sending.sub.${subStep}`) : undefined
                        }
                    />
                </div>
                <div className="mt-6 min-h-6">
                    {countdown && (
                        <CountdownBar
                            now={now}
                            startsAt={new Date(
                                countdown.startsAt,
                            ).toISOString()}
                            endsAt={new Date(countdown.endsAt).toISOString()}
                        />
                    )}
                </div>
            </div>
            <div className="mt-7 flex flex-col gap-2.5">
                {queue.map((item, index) => (
                    <QueueRow
                        key={item.id}
                        company={item.company}
                        title={item.title}
                        meta={item.company}
                        language={item.language}
                        eta={format.date(now + (index + 1) * QUEUE_GAP_MS, {
                            hour: '2-digit',
                            minute: '2-digit',
                            hourCycle: 'h23',
                        })}
                    />
                ))}
            </div>
        </DarkCard>
    );
};

const LimitTile = () => {
    const { t } = useT();
    const format = useFormat();
    const { sentToday } = useDemoLoopState();

    return (
        <StatTile
            label={t('dashboard.stat.limit')}
            icon={Clock}
            tint="orange"
            value={format.number(sentToday)}
            unit={`/ ${format.number(DEMO_DAILY_LIMIT)}`}
            context={t('dashboard.stat.left', {
                count: DEMO_DAILY_LIMIT - sentToday,
            })}
            meter={{
                total: LIMIT_TICKS,
                filled: Math.round(
                    (sentToday / DEMO_DAILY_LIMIT) * LIMIT_TICKS,
                ),
            }}
        />
    );
};

const RecentActivity = () => {
    const { t } = useT();
    const { activity } = useDemoLoopState();

    return (
        <DataCard
            title={t('dashboard.activity.title')}
            subtitle={t('dashboard.activity.subtitle')}
            actions={
                <Pill tone="tile">
                    <LiveDot className="size-2" />
                    {t('dashboard.live.badge')}
                </Pill>
            }
        >
            <div className="flex flex-col">
                {activity.slice(0, 2).map((item) => (
                    <div
                        key={item.id}
                        className="border-b border-hairline py-4 first:pt-0 last:border-b-0 last:pb-0"
                    >
                        <ActivityRow
                            status="done"
                            title={t('dashboard.activity.sent')}
                            subtitle={`${item.job.title} · ${item.job.company}`}
                            time={new Date(item.at).toISOString()}
                            timeFormat="clock"
                        />
                    </div>
                ))}
            </div>
        </DataCard>
    );
};

/** Hero demo: real presentational patterns fed by the shared demo loop. */
export function HeroDemo() {
    const { t } = useT();
    const [ref, inView] = useInView<HTMLDivElement>({ latch: false });

    useRegisterDemoInView(inView);

    return (
        <div>
            <div
                ref={ref}
                aria-hidden="true"
                inert
                className="grid grid-cols-1 gap-gap md:grid-cols-5 xl:demo-compact"
            >
                <div className="md:col-span-5">
                    <HeroStats />
                </div>
                <div className="md:col-span-3">
                    <LiveSending />
                </div>
                <div className="flex flex-col gap-gap md:col-span-2">
                    <LimitTile />
                    <RecentActivity />
                </div>
            </div>
            <p className="mt-4 text-label-sm text-muted">
                {t('landing.demo.caption')}
            </p>
        </div>
    );
}
