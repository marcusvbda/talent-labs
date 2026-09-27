import { Head, usePage } from '@inertiajs/react';
import {
    Check,
    Clock,
    Layers,
    Plus,
    Send,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { HeroCard } from '@/components/patterns/hero-card';
import { LockOverlay } from '@/components/patterns/lock-overlay';
import { PageHeader } from '@/components/patterns/page-header';
import { StatTile } from '@/components/patterns/stat-tile';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import { Segmented } from '@/components/ui/segmented';
import { useAccountStatus } from '@/data/hooks/use-account-status';
import { useDashboard } from '@/data/hooks/use-dashboard';
import { useLiveSending } from '@/data/hooks/use-live-sending';
import { ActivityCard } from '@/features/dashboard/activity-card';
import { ChartCard } from '@/features/dashboard/chart-card';
import { DashboardGrid } from '@/features/dashboard/dashboard-grid';
import { GmailBanner } from '@/features/dashboard/gmail-banner';
import {
    LiveSendingCard,
    LiveSendingPlaceholder,
} from '@/features/dashboard/live-sending-card';
import { NewMatchesCard } from '@/features/dashboard/new-matches-card';
import { RichText } from '@/features/dashboard/rich-text';
import { SetupCard } from '@/features/dashboard/setup-card';
import { useCountdown } from '@/features/dashboard/use-countdown';
import { useT } from '@/i18n/i18n-provider';
import { AppLayout } from '@/layouts/app-layout';
import { useFormat } from '@/lib/format';
import { jobs } from '@/routes';
import type {
    ChartData,
    DashboardData,
    DashboardPeriod,
} from '@/types/contracts';
import type { SharedProps } from '@/types/shared';

type DashboardPageProps = SharedProps & {
    dashboard: DashboardData;
    chart: ChartData;
};

const METER_TICKS = 20;

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
    const {
        auth,
        dashboard: dashboardProp,
        chart: chartProp,
    } = usePage<DashboardPageProps>().props;
    const [period, setPeriod] = useState<DashboardPeriod>('today');
    const status = useAccountStatus();
    const dashboard = useDashboard(
        period,
        period === 'today' ? dashboardProp : undefined,
    );
    const sending = useLiveSending();
    const nextSend = useCountdown(dashboard.data?.hero.nextSendAt ?? null);

    const name = auth.user?.name.trim().split(/\s+/)[0] ?? '';

    const quota = status.data?.quota;
    const data = dashboard.data;
    const heroLoading = !data || !status.data;
    const onboardingComplete = status.data?.onboarding.complete;
    const setupIncomplete = onboardingComplete === false;
    const gmailExpired =
        status.data?.gmail.state === 'reauthorization_required';
    const date = format.date(Date.now(), {
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
            {gmailExpired && <GmailBanner />}
            <DashboardGrid
                setup={
                    setupIncomplete && status.data ? (
                        <SetupCard onboarding={status.data.onboarding} />
                    ) : undefined
                }
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
                    <LockOverlay
                        locked={setupIncomplete}
                        title={t('dashboard.setup.locked')}
                    >
                        {sending.data ? (
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
                        )}
                    </LockOverlay>
                }
                chart={<ChartCard initial={chartProp} />}
                matches={
                    <LockOverlay
                        locked={setupIncomplete}
                        title={t('dashboard.setup.locked')}
                    >
                        <NewMatchesCard
                            matches={
                                data?.matches ?? {
                                    total: 0,
                                    newToday: 0,
                                    items: [],
                                }
                            }
                            mode={status.data?.plan.mode ?? 'select'}
                            accountEmail={status.data?.gmail.accountEmail ?? ''}
                            remaining={status.data?.quota.remaining ?? 0}
                        />
                    </LockOverlay>
                }
                activity={<ActivityCard activity={data?.activity ?? []} />}
            />
        </AppLayout>
    );
}
