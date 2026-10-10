import { ArrowUpRight } from 'lucide-react';
import { ActivityRow } from '@/components/patterns/activity-row';
import type { ActivityStatus } from '@/components/patterns/activity-row';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { LiveDot } from '@/components/ui/live-dot';
import { Pill } from '@/components/ui/pill';
import { useT } from '@/i18n/i18n-provider';
import { applications } from '@/routes';
import type { ApplicationItem, ApplicationStatus } from '@/types/contracts';

const ACTIVITY: Record<ApplicationStatus, ActivityStatus> = {
    queued: 'waiting',
    sending: 'sending',
    sent: 'done',
    failed: 'failed',
    ambiguous: 'failed',
    // Closed without being sent; no neutral finished bucket exists, so it shares failed's.
    cancelled: 'failed',
};

const timeOf = (item: ApplicationItem) =>
    item.sentAt ?? item.scheduledFor ?? item.queuedAt;

export function ActivityCard({ activity }: { activity: ApplicationItem[] }) {
    const { t } = useT();

    return (
        <DataCard
            title={t('dashboard.activity.title')}
            subtitle={t('dashboard.activity.subtitle')}
            state={activity.length === 0 ? 'empty' : 'ready'}
            actions={
                <Pill tone="tile">
                    <LiveDot className="size-2" />
                    {t('dashboard.live.badge')}
                </Pill>
            }
            className="flex flex-col"
        >
            <div className="flex flex-col">
                {activity.map((item) => {
                    const line = `${item.title ?? ''} · ${item.company.name}`;
                    const title =
                        item.status === 'sending'
                            ? t('status.sending')
                            : item.status === 'sent'
                              ? t('dashboard.activity.sent')
                              : item.status === 'ambiguous'
                                ? t('dashboard.activity.needs_review')
                                : item.status === 'queued'
                                  ? t('dashboard.hero.queued')
                                  : item.status === 'cancelled'
                                    ? t('applications.status.cancelled')
                                    : t('dashboard.activity.failed');
                    const subtitle =
                        item.status === 'failed' || item.status === 'ambiguous'
                            ? (item.lastError ?? line)
                            : line;

                    return (
                        <div
                            key={item.id}
                            className="border-b border-hairline py-4 first:pt-0 last:border-b-0"
                        >
                            <ActivityRow
                                status={ACTIVITY[item.status]}
                                title={title}
                                subtitle={subtitle}
                                time={timeOf(item)}
                                timeFormat={
                                    item.status === 'sending'
                                        ? 'relative'
                                        : 'clock'
                                }
                            />
                        </div>
                    );
                })}
            </div>
            <div className="mt-auto pt-2">
                <Button
                    variant="secondary-tile"
                    size="lg"
                    fullWidth
                    iconRight={ArrowUpRight}
                    href={applications().url}
                >
                    {t('dashboard.activity.view_all')}
                </Button>
            </div>
        </DataCard>
    );
}
