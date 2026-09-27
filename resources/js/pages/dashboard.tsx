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
import { DataCard } from '@/components/patterns/data-card';
import { HeroCard } from '@/components/patterns/hero-card';
import { PageHeader } from '@/components/patterns/page-header';
import { StatTile } from '@/components/patterns/stat-tile';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import { LiveDot } from '@/components/ui/live-dot';
import { Pill } from '@/components/ui/pill';
import { Segmented } from '@/components/ui/segmented';
import { useAccountStatus } from '@/data/hooks/use-account-status';
import { useDashboard } from '@/data/hooks/use-dashboard';
import { useLiveSending } from '@/data/hooks/use-live-sending';
import { useFixturePlan } from '@/data/hooks/use-fixture-plan';
import { ChartCard } from '@/features/dashboard/chart-card';
import { DashboardGrid } from '@/features/dashboard/dashboard-grid';
import {
    LiveSendingCard,
    LiveSendingPlaceholder,
} from '@/features/dashboard/live-sending-card';
import { NewMatchesCard } from '@/features/dashboard/new-matches-card';
import { RichText } from '@/features/dashboard/rich-text';
import { useCountdown } from '@/features/dashboard/use-countdown';
import { useT } from '@/i18n/i18n-provider';
import { AppLayout } from '@/layouts/app-layout';
import { useFormat } from '@/lib/format';
import { jobs } from '@/routes';
import type { DashboardPeriod } from '@/types/contracts';
import type { Locale, SharedProps } from '@/types/shared';

const MINUTE = 60_000;
const METER_TICKS = 20;

/** Demo numbers from the design mockup; replaced by real data in later phases. */
const DEMO = {
    sent: 18,
    limit: 50,
    matches: 61,
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
            language: 'pt' as Locale,
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
    };
};

const greetingKey = (hour: number) =>
    hour < 12
        ? 'dashboard.greeting.morning'
        : hour < 18
          ? 'dashboard.greeting.afternoon'
          : 'dashboard.greeting.evening';

const dayOfMonth = (date: string) => String(Number(date.slice(8, 10)));

export default function Dashboard() {
    const { t } = useT();
    const format = useFormat();
    const { auth } = usePage<SharedProps>().props;
    const plan = useFixturePlan() ?? 'free';
    const [clock] = useState(buildClock);
    const [period, setPeriod] = useState<DashboardPeriod>('today');
    const [selected, setSelected] = useState<string[]>(['cobalt', 'estrela']);
    const status = useAccountStatus();
    const dashboard = useDashboard(period);
    const sending = useLiveSending();
    const nextSend = useCountdown(dashboard.data?.hero.nextSendAt ?? null);

    const name = auth.user?.name.trim().split(/\s+/)[0] ?? '';
    const ago = (minutes: number) =>
        format.relativeTime(clock.now - minutes * MINUTE);
    const at = (minutes: number) =>
        new Date(clock.now - minutes * MINUTE).toISOString();
    const left = DEMO.limit - DEMO.sent;

    const quota = status.data?.quota;
    const data = dashboard.data;
    const heroLoading = !data || !status.data;
    const date = format.date(clock.now, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    });
    const eyebrow = status.data
        ? `${date} · ${t(`dashboard.mode.${status.data.plan.mode}`)}`
        : date;
    const delta = (current: number, previous: number) => ({
        direction: current >= previous ? ('up' as const) : ('down' as const),
        label: format.number(Math.abs(current - previous)),
        context: t(
            period === 'today'
                ? 'dashboard.stat.vs_yesterday'
                : 'dashboard.stat.vs_previous',
        ),
    });
    const filled =
        quota && quota.limit > 0
            ? Math.min(
                  METER_TICKS,
                  Math.round((quota.usedToday / quota.limit) * METER_TICKS),
              )
            : 0;

    return (
        <AppLayout>
            <Head title={t('dashboard.title')} />
            <PageHeader
                eyebrow={eyebrow}
                title={t(greetingKey(new Date().getHours()), { name })}
                summary={
                    data ? (
                        <RichText
                            id="dashboard.summary"
                            values={{
                                sent: data.hero.sentToday,
                                matches: data.matches.total,
                            }}
                        />
                    ) : undefined
                }
                actions={
                    <>
                        <Segmented<DashboardPeriod>
                            className="bg-card"
                            value={period}
                            onChange={setPeriod}
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
                        <Button size="lg" iconLeft={Plus} href={jobs().url}>
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
                        loading={heroLoading}
                        value={String(data?.hero.sentToday ?? 0)}
                        suffix={`/ ${quota?.limit ?? 0}`}
                        bars={[
                            ...(data?.hero.lastDays.map((day) => day.count) ??
                                []),
                            data?.hero.sentToday ?? 0,
                        ]}
                        barLabels={[
                            ...(data?.hero.lastDays.map((day) =>
                                dayOfMonth(day.date),
                            ) ?? []),
                            t('dashboard.hero.now'),
                        ]}
                        caption={t('dashboard.hero.caption', {
                            left: quota?.remaining ?? 0,
                            plan: status.data?.plan.name ?? '',
                        })}
                        stats={[
                            {
                                label: t('dashboard.hero.queued'),
                                value: format.number(data?.hero.queued ?? 0),
                            },
                            {
                                label: t('dashboard.hero.not_delivered'),
                                value: format.number(
                                    data?.hero.failedToday ?? 0,
                                ),
                            },
                            {
                                label: t('dashboard.hero.next_send'),
                                value: nextSend ?? t('dashboard.hero.no_next'),
                            },
                        ]}
                    />
                }
                kpi={
                    <DataCard
                        title={t(`dashboard.period.${period}`)}
                        state={dashboard.isError ? 'error' : 'ready'}
                        onRetry={() => void dashboard.refetch()}
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
                                loading={!data}
                                value={format.number(
                                    data?.kpis.collected.value ?? 0,
                                )}
                                delta={
                                    data
                                        ? delta(
                                              data.kpis.collected.value,
                                              data.kpis.collected.previous,
                                          )
                                        : undefined
                                }
                            />
                            <StatTile
                                label={t('dashboard.stat.sent_week')}
                                icon={Send}
                                tint="neutral"
                                loading={!data}
                                value={format.number(
                                    data?.kpis.sent.value ?? 0,
                                )}
                                delta={
                                    data
                                        ? delta(
                                              data.kpis.sent.value,
                                              data.kpis.sent.previous,
                                          )
                                        : undefined
                                }
                            />
                            <StatTile
                                label={t('dashboard.stat.total')}
                                icon={Check}
                                tint="green"
                                loading={!data}
                                value={format.number(data?.kpis.totalSent ?? 0)}
                                context={
                                    data?.kpis.firstSentAt
                                        ? t('dashboard.stat.since', {
                                              date: format.date(
                                                  data.kpis.firstSentAt,
                                                  {
                                                      month: 'short',
                                                      day: 'numeric',
                                                  },
                                              ),
                                          })
                                        : undefined
                                }
                            />
                            <StatTile
                                label={t('dashboard.stat.limit')}
                                icon={Clock}
                                tint="orange"
                                loading={!quota}
                                value={format.number(quota?.usedToday ?? 0)}
                                unit={`/ ${format.number(quota?.limit ?? 0)}`}
                                context={t('dashboard.stat.left', {
                                    count: quota?.remaining ?? 0,
                                })}
                                meter={{ total: METER_TICKS, filled }}
                            />
                        </div>
                    </DataCard>
                }
                live={
                    sending.data ? (
                        <LiveSendingCard
                            sending={sending.data}
                            mode={status.data?.plan.mode ?? 'select'}
                            activity={data?.activity ?? []}
                        />
                    ) : (
                        <LiveSendingPlaceholder
                            isError={sending.isError}
                            onRetry={() => void sending.refetch()}
                        />
                    )
                }
                chart={<ChartCard />}
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
