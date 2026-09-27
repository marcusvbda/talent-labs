import { ActivityRow } from '@/components/patterns/activity-row';
import type { ActivityStatus } from '@/components/patterns/activity-row';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type { ApplicationItem, ApplicationStatus } from '@/types/contracts';
import { StatusBadge } from './status-badge';

const ACTIVITY: Record<ApplicationStatus, ActivityStatus> = {
    queued: 'waiting',
    sending: 'sending',
    sent: 'done',
    failed: 'failed',
    ambiguous: 'failed',
};

const timeOf = (item: ApplicationItem) =>
    item.sentAt ?? item.scheduledFor ?? item.queuedAt;

export function ApplicationsTable({
    items,
    onOpen,
    hasMore,
    loadingMore,
    onLoadMore,
}: {
    items: ApplicationItem[];
    onOpen: (item: ApplicationItem) => void;
    hasMore: boolean;
    loadingMore: boolean;
    onLoadMore: () => void;
}) {
    const { t } = useT();
    const format = useFormat();
    const role = (item: ApplicationItem) =>
        item.title ?? t('applications.no_title');

    return (
        <div className="flex flex-col gap-3">
            <div className="hidden md:block">
                <table className="w-full table-fixed text-left text-body">
                    <thead>
                        <tr className="text-label-sm text-muted">
                            <th className="w-[26%] px-4 py-2 font-medium">
                                {t('applications.col.company')}
                            </th>
                            <th className="w-[26%] px-4 py-2 font-medium">
                                {t('applications.col.role')}
                            </th>
                            <th className="w-[10%] px-4 py-2 font-medium">
                                {t('applications.col.language')}
                            </th>
                            <th className="w-[20%] px-4 py-2 font-medium">
                                {t('applications.col.status')}
                            </th>
                            <th className="w-[18%] px-4 py-2 font-medium">
                                {t('applications.col.time')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr
                                key={item.id}
                                onClick={() => onOpen(item)}
                                className="cursor-pointer border-t border-hairline transition-colors hover:bg-tile"
                            >
                                <td className="px-4 py-3">
                                    <button
                                        type="button"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onOpen(item);
                                        }}
                                        className="flex max-w-full items-center gap-3 rounded-tile text-left focus-visible:focus-ring"
                                    >
                                        <CompanyLogo
                                            name={item.company.name}
                                            size="sm"
                                        />
                                        <span className="truncate text-label text-ink">
                                            {item.company.name}
                                        </span>
                                    </button>
                                </td>
                                <td className="truncate px-4 py-3 text-ink">
                                    {role(item)}
                                </td>
                                <td className="px-4 py-3 text-muted uppercase">
                                    {item.language}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-col items-start gap-1">
                                        <StatusBadge status={item.status} />
                                        {item.status === 'sending' &&
                                            item.stage && (
                                                <span className="text-label-sm text-muted">
                                                    {t(
                                                        `applications.stage.${item.stage}`,
                                                    )}
                                                </span>
                                            )}
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-muted">
                                    <time dateTime={timeOf(item)}>
                                        {format.date(timeOf(item), {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        })}
                                    </time>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <ul className="flex flex-col gap-3 md:hidden">
                {items.map((item) => (
                    <li key={item.id}>
                        <button
                            type="button"
                            onClick={() => onOpen(item)}
                            className="flex w-full flex-col gap-2 rounded-tile bg-tile p-card-sm text-left focus-visible:focus-ring"
                        >
                            <ActivityRow
                                status={ACTIVITY[item.status]}
                                title={item.company.name}
                                subtitle={role(item)}
                                time={timeOf(item)}
                            />
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge status={item.status} />
                                {item.status === 'sending' && item.stage && (
                                    <span className="text-label-sm text-muted">
                                        {t(`applications.stage.${item.stage}`)}
                                    </span>
                                )}
                            </div>
                        </button>
                    </li>
                ))}
            </ul>
            {hasMore && (
                <div className="flex justify-center pt-2">
                    <Button
                        variant="secondary-tile"
                        loading={loadingMore}
                        onClick={onLoadMore}
                    >
                        {t('applications.load_more')}
                    </Button>
                </div>
            )}
        </div>
    );
}
