import { Head, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Check,
    Clock,
    Layers,
    Plus,
    Send,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import { ActivityRow } from '@/components/patterns/activity-row';
import type { ActivityStatus } from '@/components/patterns/activity-row';
import { BarChart } from '@/components/patterns/bar-chart';
import { DataCard } from '@/components/patterns/data-card';
import { HeroCard } from '@/components/patterns/hero-card';
import { PageHeader } from '@/components/patterns/page-header';
import { StatTile } from '@/components/patterns/stat-tile';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import { LiveDot } from '@/components/ui/live-dot';
import { Pill } from '@/components/ui/pill';
import { Segmented } from '@/components/ui/segmented';
import { useFixturePlan } from '@/data/hooks/use-fixture-plan';
import { DashboardGrid } from '@/features/dashboard/dashboard-grid';
import { LiveSendingCard } from '@/features/dashboard/live-sending-card';
import { NewMatchesCard } from '@/features/dashboard/new-matches-card';
import { RichText } from '@/features/dashboard/rich-text';
import { useT } from '@/i18n/i18n-provider';
import { AppLayout } from '@/layouts/app-layout';
import { useFormat } from '@/lib/format';
import type { Locale, SharedProps } from '@/types/shared';

type Range = 'today' | 'week' | 'month';
type ChartRange = 14 | 30;

const MINUTE = 60_000;
const DAY = 86_400_000;

/** Demo numbers from the design mockup; replaced by real data in later phases. */
const DEMO = {
    plan: 'Starter',
    sent: 18,
    limit: 50,
    matches: 61,
    queued: 12,
    notDelivered: 1,
    heroBars: [46, 41, 49, 36, 47, 18],
    weekdayValues: [34, 41, 50, 46, 50, 43, 50, 38, 49, 42, 45, 40],
    liveJob: {
        company: 'Klarwerk',
        title: 'Senior Backend Engineer, PHP / Laravel',
        meta: 'Klarwerk · Berlin, Remote (EU)',
        language: 'en' as Locale,
    },
    queue: [
        {
            company: 'Lumen Health',
            title: 'Full-stack Engineer',
            meta: 'Lumen Health · Lisbon',
            language: 'en' as Locale,
            eta: '0:42',
        },
        {
            company: 'Estrela Pay',
            title: 'Desenvolvedor Backend Sênior',
            meta: 'Estrela Pay · São Paulo, Remoto',
            language: 'pt' as Locale,
            eta: '2:05',
        },
        {
            company: 'Nuvia',
            title: 'Ingeniero Backend (Go)',
            meta: 'Nuvia · Madrid',
            language: 'es' as Locale,
            eta: '3:18',
        },
    ],
    jobs: [
        {
            id: 'cobalt',
            company: 'Cobalt Freight',
            title: 'Frontend Developer, React',
            place: 'Remote, EU',
            minutesAgo: 12,
            stack: ['React', 'TypeScript'],
            language: 'en' as Locale,
        },
        {
            id: 'estrela',
            company: 'Estrela Pay',
            title: 'Desenvolvedor Full-stack Pleno',
            place: 'São Paulo',
            minutesAgo: 60,
            stack: ['Laravel', 'React'],
            language: 'pt' as Locale,
        },
        {
            id: 'lumen',
            company: 'Lumen Health',
            title: 'Senior Backend Engineer',
            place: 'Lisbon',
            minutesAgo: 120,
            stack: ['Python', 'PostgreSQL'],
            language: 'en' as Locale,
        },
        {
            id: 'nuvia',
            company: 'Nuvia',
            title: 'Ingeniero de Software Full-stack',
            place: 'Madrid, Híbrido',
            minutesAgo: 180,
            stack: ['Node.js', 'React'],
            language: 'es' as Locale,
        },
    ],
    activity: [
        {
            status: 'sending' as ActivityStatus,
            title: 'Senior Backend Engineer',
            company: 'Klarwerk',
            minutesAgo: 0,
        },
        {
            status: 'done' as ActivityStatus,
            title: 'Full-stack Engineer',
            company: 'Lumen Health',
            minutesAgo: 8,
        },
        {
            status: 'done' as ActivityStatus,
            title: 'Frontend Developer',
            company: 'Cobalt Freight',
            minutesAgo: 14,
        },
        {
            status: 'failed' as ActivityStatus,
            title: '',
            company: '',
            minutesAgo: 21,
        },
        {
            status: 'done' as ActivityStatus,
            title: 'Desenvolvedor Backend',
            company: 'Estrela Pay',
            minutesAgo: 25,
        },
    ],
};

/** Demo timestamps are computed relative to mount time. */
const buildClock = () => {
    const now = Date.now();

    return {
        now,
        startsAt: new Date(now - 18_000).toISOString(),
        endsAt: new Date(now + 42_000).toISOString(),
    };
};

const buildChart = (now: number, days: number) => {
    let cursor = 0;

    return Array.from({ length: days }, (_, index) => {
        const offset = days - 1 - index;
        const date = new Date(now - offset * DAY);
        const weekend = date.getDay() === 0 || date.getDay() === 6;
        const isToday = offset === 0;
        const value = isToday
            ? DEMO.sent
            : weekend
              ? 0
              : DEMO.weekdayValues[cursor++ % DEMO.weekdayValues.length];

        return { label: String(date.getDate()), value, isToday };
    });
};

export default function Dashboard() {
    const { t } = useT();
    const format = useFormat();
    const { auth } = usePage<SharedProps>().props;
    const plan = useFixturePlan() ?? 'free';
    const [clock] = useState(buildClock);
    const [range, setRange] = useState<Range>('today');
    const [chartRange, setChartRange] = useState<ChartRange>(14);
    const [selected, setSelected] = useState<string[]>(['cobalt', 'estrela']);

    const name = auth.user?.name.trim().split(/\s+/)[0] ?? '';
    const dayLabel = (offset: number) =>
        String(new Date(clock.now - offset * DAY).getDate());
    const ago = (minutes: number) =>
        format.relativeTime(clock.now - minutes * MINUTE);
    const at = (minutes: number) =>
        new Date(clock.now - minutes * MINUTE).toISOString();
    const chart = buildChart(clock.now, chartRange);
    const left = DEMO.limit - DEMO.sent;

    return (
        <AppLayout>
            <Head title={t('dashboard.title')} />
            <PageHeader
                eyebrow={`${format.date(clock.now, {
                    weekday: 'long',
                    month: 'short',
                    day: 'numeric',
                })} · ${t('dashboard.mode')}`}
                title={t('dashboard.greeting.afternoon', { name })}
                summary={
                    <RichText
                        id="dashboard.summary"
                        values={{ sent: DEMO.sent, matches: DEMO.matches }}
                    />
                }
                actions={
                    <>
                        <Segmented<Range>
                            className="bg-card"
                            value={range}
                            onChange={setRange}
                            ariaLabel={t('dashboard.range.label')}
                            options={[
                                {
                                    value: 'today',
                                    label: t('dashboard.range.today'),
                                },
                                {
                                    value: 'week',
                                    label: t('dashboard.range.week'),
                                },
                                {
                                    value: 'month',
                                    label: t('dashboard.range.month'),
                                },
                            ]}
                        />
                        <Button size="lg" iconLeft={Plus}>
                            {t('dashboard.browse')}
                        </Button>
                    </>
                }
            />
            <DashboardGrid
                hero={
                    <HeroCard
                        label={t('dashboard.hero.label')}
                        icon={Send}
                        value={String(DEMO.sent)}
                        suffix={`/ ${DEMO.limit}`}
                        bars={DEMO.heroBars}
                        barLabels={[
                            ...[5, 4, 3, 2, 1].map(dayLabel),
                            t('dashboard.hero.now'),
                        ]}
                        caption={t('dashboard.hero.caption', {
                            left,
                            plan: DEMO.plan,
                        })}
                        stats={[
                            {
                                label: t('dashboard.hero.queued'),
                                value: String(DEMO.queued),
                            },
                            {
                                label: t('dashboard.hero.not_delivered'),
                                value: String(DEMO.notDelivered),
                            },
                            {
                                label: t('dashboard.hero.next_send'),
                                value: '0:42',
                            },
                        ]}
                    />
                }
                kpi={
                    <DataCard
                        title={t('dashboard.today.title')}
                        actions={
                            <IconButton
                                icon={SlidersHorizontal}
                                label={t('dashboard.today.filter')}
                            />
                        }
                        className="flex flex-col"
                    >
                        <div className="grid flex-1 grid-cols-2 gap-4 desk:grid-cols-4">
                            <StatTile
                                label={t('dashboard.stat.collected')}
                                icon={Layers}
                                tint="orange"
                                value="214"
                                delta={{
                                    direction: 'up',
                                    label: '38',
                                    context: t('dashboard.stat.vs_yesterday'),
                                }}
                            />
                            <StatTile
                                label={t('dashboard.stat.sent_week')}
                                icon={Send}
                                tint="neutral"
                                value="199"
                                delta={{
                                    direction: 'down',
                                    label: '22',
                                    context: t('dashboard.stat.vs_previous'),
                                }}
                            />
                            <StatTile
                                label={t('dashboard.stat.total')}
                                icon={Check}
                                tint="green"
                                value="420"
                                context={t('dashboard.stat.since', {
                                    date: format.date(clock.now - 11 * DAY, {
                                        month: 'short',
                                        day: 'numeric',
                                    }),
                                })}
                            />
                            <StatTile
                                label={t('dashboard.stat.limit')}
                                icon={Clock}
                                tint="orange"
                                value={String(DEMO.sent)}
                                unit={`/ ${DEMO.limit}`}
                                context={t('dashboard.stat.left', {
                                    count: left,
                                })}
                                meter={{ total: 36, filled: 13 }}
                            />
                        </div>
                    </DataCard>
                }
                live={
                    <LiveSendingCard
                        {...DEMO.liveJob}
                        position={19}
                        limit={DEMO.limit}
                        activeStep={2}
                        startsAt={clock.startsAt}
                        endsAt={clock.endsAt}
                        spacing={{ min: 45, max: 120 }}
                        queue={DEMO.queue}
                        queuedCount={DEMO.queued}
                        minutes={17}
                    />
                }
                chart={
                    <DataCard
                        title={t('dashboard.chart.title')}
                        subtitle={t('dashboard.chart.subtitle', {
                            days: chartRange,
                        })}
                        actions={
                            <Segmented<ChartRange>
                                value={chartRange}
                                onChange={setChartRange}
                                ariaLabel={t('dashboard.chart.range')}
                                options={[
                                    {
                                        value: 14,
                                        label: t('dashboard.chart.range_14'),
                                    },
                                    {
                                        value: 30,
                                        label: t('dashboard.chart.range_30'),
                                    },
                                ]}
                            />
                        }
                        className="flex flex-col"
                    >
                        <p className="mb-4 flex items-baseline gap-2.5">
                            <span className="text-numeral-lg-sm md:text-numeral-lg">
                                42
                            </span>
                            <span className="text-body text-muted">
                                {t('dashboard.chart.average')}
                            </span>
                        </p>
                        <BarChart
                            data={chart}
                            title={t('dashboard.chart.title')}
                        />
                        <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 pt-4 text-label-sm text-muted">
                            <span className="flex items-center gap-4">
                                <span className="flex items-center gap-2">
                                    <i className="size-2.5 rounded-full bg-bar-cap" />
                                    {t('dashboard.chart.legend_sent')}
                                </span>
                                <span className="flex items-center gap-2">
                                    <i className="size-2.5 rounded-full bg-accent" />
                                    {t('dashboard.chart.legend_today')}
                                </span>
                            </span>
                            <span>
                                {t('dashboard.chart.limit', {
                                    limit: DEMO.limit,
                                })}
                            </span>
                        </div>
                    </DataCard>
                }
                matches={
                    <NewMatchesCard
                        plan={plan}
                        matches={DEMO.matches}
                        left={left}
                        selected={selected}
                        onClear={() => setSelected([])}
                        onToggle={(id, checked) =>
                            setSelected((current) =>
                                checked
                                    ? [...current, id]
                                    : current.filter((item) => item !== id),
                            )
                        }
                        jobs={DEMO.jobs.map((job) => ({
                            id: job.id,
                            company: job.company,
                            title: job.title,
                            meta: `${job.company} · ${job.place} · ${ago(job.minutesAgo)}`,
                            stack: job.stack,
                            language: job.language,
                        }))}
                    />
                }
                activity={
                    <DataCard
                        title={t('dashboard.activity.title')}
                        subtitle={t('dashboard.activity.subtitle')}
                        actions={
                            <Pill tone="tile">
                                <LiveDot className="size-2" />
                                {t('dashboard.live.badge')}
                            </Pill>
                        }
                        className="flex flex-col"
                    >
                        <div className="flex flex-col">
                            {DEMO.activity.map((item, index) => (
                                <div
                                    key={index}
                                    className="border-b border-hairline py-4 first:pt-0 last:border-b-0"
                                >
                                    <ActivityRow
                                        status={item.status}
                                        title={
                                            item.status === 'sending'
                                                ? t('status.sending')
                                                : item.status === 'failed'
                                                  ? t(
                                                        'dashboard.activity.failed',
                                                    )
                                                  : t('dashboard.activity.sent')
                                        }
                                        subtitle={
                                            item.status === 'failed'
                                                ? t(
                                                      'dashboard.activity.failed_detail',
                                                  )
                                                : `${item.title} · ${item.company}`
                                        }
                                        time={at(item.minutesAgo)}
                                        timeFormat={
                                            item.status === 'sending'
                                                ? 'relative'
                                                : 'clock'
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="mt-auto pt-2">
                            <Button
                                variant="secondary-tile"
                                size="lg"
                                fullWidth
                                iconRight={ArrowUpRight}
                            >
                                {t('dashboard.activity.view_all')}
                            </Button>
                        </div>
                    </DataCard>
                }
            />
        </AppLayout>
    );
}
