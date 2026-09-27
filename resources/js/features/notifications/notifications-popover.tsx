import { router } from '@inertiajs/react';
import {
    Bell,
    Briefcase,
    Inbox,
    Mail,
    PauseCircle,
    TriangleAlert,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';
import { IconButton } from '@/components/ui/icon-button';
import { Popover } from '@/components/ui/popover';
import { Sheet } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { StatusDisc } from '@/components/ui/status-disc';
import {
    useMarkAllRead,
    useNotifications,
} from '@/data/hooks/use-notifications';
import { useT } from '@/i18n/i18n-provider';
import type { Params } from '@/i18n/translate';
import { useFormat } from '@/lib/format';
import { cn } from '@/lib/utils';
import { account, applications, jobs, plans } from '@/routes';
import type { NotificationItem, NotificationType } from '@/types/contracts';

type IconTint = {
    icon: LucideIcon;
    tint: 'orange' | 'red' | 'green' | 'neutral';
};

const TYPE_VISUALS: Record<NotificationType, IconTint> = {
    gmail_reauthorization_required: { icon: Mail, tint: 'orange' },
    application_failed: { icon: TriangleAlert, tint: 'red' },
    daily_limit_reached: { icon: PauseCircle, tint: 'orange' },
    jobs_collected: { icon: Briefcase, tint: 'green' },
    sending_auto_paused: { icon: PauseCircle, tint: 'neutral' },
};

const targetFor = (type: NotificationType): string => {
    switch (type) {
        case 'gmail_reauthorization_required':
            return account().url;
        case 'application_failed':
            return applications().url;
        case 'daily_limit_reached':
            return plans().url;
        case 'jobs_collected':
            return jobs().url;
        case 'sending_auto_paused':
            return account().url;
    }
};

const NotificationRow = ({ row }: { row: NotificationItem }) => {
    const { t } = useT();
    const format = useFormat();
    const visual = TYPE_VISUALS[row.type];
    const unread = row.readAt === null;
    const text = t(`notifications.${row.type}`, row.data as Params);

    return (
        <button
            type="button"
            onClick={() => router.visit(targetFor(row.type))}
            className="flex w-full items-start gap-3 rounded-tile p-2.5 text-left transition-colors hover:bg-tile focus-visible:focus-ring"
        >
            <span className="relative shrink-0">
                <StatusDisc
                    status="icon"
                    icon={visual.icon}
                    tint={visual.tint}
                    size="sm"
                    label={text}
                />
                {unread && (
                    <span
                        aria-hidden="true"
                        className="absolute top-0 right-0 size-2.5 rounded-full border-2 border-card bg-accent"
                    />
                )}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-body text-ink">{text}</span>
                <span className="mt-0.5 block text-label text-faint">
                    {format.relativeTime(row.createdAt)}
                </span>
            </span>
        </button>
    );
};

function NotificationsList({ showTitle = true }: { showTitle?: boolean }) {
    const { t } = useT();
    const notifications = useNotifications();
    const markAllRead = useMarkAllRead();

    return (
        <div className="flex flex-col gap-2">
            <div
                className={cn(
                    'flex items-center gap-2 px-1',
                    showTitle ? 'justify-between' : 'justify-end',
                )}
            >
                {showTitle && (
                    <h2 className="text-card-title-sm text-ink">
                        {t('notifications.title')}
                    </h2>
                )}
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => markAllRead.mutate()}
                    disabled={
                        markAllRead.isPending ||
                        !notifications.data?.some((row) => row.readAt === null)
                    }
                >
                    {t('notifications.mark_all')}
                </Button>
            </div>

            {notifications.isPending && (
                <div
                    role="status"
                    aria-busy="true"
                    aria-label={t('common.loading')}
                    className="flex flex-col gap-2 p-1"
                >
                    <Skeleton shape="line" />
                    <Skeleton shape="line" />
                    <Skeleton shape="line" className="w-2/3" />
                </div>
            )}

            {notifications.isError && (
                <ErrorState onRetry={() => notifications.refetch()} />
            )}

            {notifications.isSuccess && notifications.data.length === 0 && (
                <EmptyState icon={Inbox} title={t('notifications.empty')} />
            )}

            {notifications.isSuccess && notifications.data.length > 0 && (
                <ul className="flex flex-col gap-1">
                    {notifications.data.map((row) => (
                        <li key={row.id}>
                            <NotificationRow row={row} />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function NotificationsPopover({ dot = false }: { dot?: boolean }) {
    const { t } = useT();
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <>
            <div className="hidden md:block">
                <Popover
                    trigger={
                        <IconButton
                            icon={Bell}
                            label={t('topbar.notifications')}
                            size={60}
                            bg="white"
                            dot={dot}
                        />
                    }
                    className="w-80"
                >
                    <NotificationsList />
                </Popover>
            </div>
            <IconButton
                icon={Bell}
                label={t('topbar.notifications')}
                size={60}
                bg="white"
                dot={dot}
                className="md:hidden"
                onClick={() => setMobileOpen(true)}
            />
            <Sheet
                open={mobileOpen}
                onClose={() => setMobileOpen(false)}
                side="bottom"
                title={t('notifications.title')}
            >
                <NotificationsList showTitle={false} />
            </Sheet>
        </>
    );
}
