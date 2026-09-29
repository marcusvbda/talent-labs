import { Pause, Play, Radio } from 'lucide-react';
import type { ReactNode } from 'react';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { CountdownBar } from '@/components/patterns/countdown-bar';
import { DarkCard } from '@/components/patterns/dark-card';
import { LiveStepper } from '@/components/patterns/live-stepper';
import { QueueRow } from '@/components/patterns/queue-row';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { LiveDot } from '@/components/ui/live-dot';
import { Pill } from '@/components/ui/pill';
import { Skeleton } from '@/components/ui/skeleton';
import {
    usePauseSending,
    useResumeSending,
} from '@/data/hooks/use-pause-sending';
import { useFailureHold } from '@/features/dashboard/use-failure-hold';
import { useT } from '@/i18n/i18n-provider';
import { intlLocale } from '@/i18n/locale';
import { useFormat } from '@/lib/format';
import { jobs, plans } from '@/routes';
import type {
    ApplicationItem,
    LiveSending,
    SendMode,
    SendStage,
    SubStep,
} from '@/types/contracts';

const STAGES: SendStage[] = [
    'validating_recipient',
    'adapting_template',
    'attaching_cv',
    'sending',
    'sent',
];

const SUB_STAGE: Record<SubStep, SendStage> = {
    checking_company: 'validating_recipient',
    confirming_recipient: 'validating_recipient',
    checking_gmail: 'validating_recipient',
    filling_variables: 'adapting_template',
    building_html: 'adapting_template',
    opening_cv: 'attaching_cv',
    checking_pdf: 'attaching_cv',
    attaching_file: 'attaching_cv',
    connecting_gmail: 'sending',
    delivering: 'sending',
};

const stageIndex = (stage: SendStage | null): number =>
    Math.max(0, stage ? STAGES.indexOf(stage) : 0);

const failedStageIndex = (item: ApplicationItem): number =>
    stageIndex(item.subStep ? SUB_STAGE[item.subStep] : 'sending');

const useHeaderAction = (sending: LiveSending) => {
    const { t } = useT();
    const pause = usePauseSending();
    const resume = useResumeSending();

    if (sending.state === 'sending' || sending.state === 'waiting') {
        return (
            <Button
                variant="ghost-on-dark"
                size="sm"
                iconLeft={Pause}
                className="border border-dark-line"
                onClick={() => pause.mutate()}
            >
                {t('dashboard.live.pause')}
            </Button>
        );
    }

    if (sending.state === 'paused') {
        return (
            <Button
                variant="ghost-on-dark"
                size="sm"
                iconLeft={Play}
                className="border border-dark-line"
                onClick={() => resume.mutate()}
            >
                {t('dashboard.live.resume')}
            </Button>
        );
    }

    return null;
};

const Empty = ({
    title,
    children,
}: {
    title: string;
    children?: ReactNode;
}) => (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 rounded-panel bg-dark-2 px-5 py-10 text-center">
        <p className="text-row-title">{title}</p>
        {children}
    </div>
);

const CurrentPanel = ({
    item,
    progress,
    failed,
}: {
    item: ApplicationItem;
    progress: LiveSending['progress'];
    failed: boolean;
}) => {
    const { t } = useT();
    const steps = STAGES.map((stage) => ({
        key: stage,
        label: t(`sending.stage.${stage}`),
    }));
    const title = item.title ?? item.company.name;

    return (
        <>
            <div className="flex items-center gap-4">
                <CompanyLogo
                    name={item.company.name}
                    size="lg"
                    tint="bg-disc-orange"
                />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-row-title">{title}</p>
                    <p className="truncate text-body text-dark-muted">
                        {item.company.name}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-3 max-md:hidden">
                    <Chip variant="language">
                        {item.language.toUpperCase()}
                    </Chip>
                    {progress && (
                        <span className="text-label-sm text-dark-muted">
                            {t('dashboard.live.position', {
                                position: progress.index,
                                limit: progress.total,
                            })}
                        </span>
                    )}
                </div>
            </div>
            <div className="mt-7">
                <LiveStepper
                    steps={steps}
                    activeIndex={
                        failed
                            ? failedStageIndex(item)
                            : item.stage === 'sent' || item.status === 'sent'
                              ? steps.length
                              : stageIndex(item.stage)
                    }
                    failedIndex={failed ? failedStageIndex(item) : undefined}
                    subStep={
                        !failed && item.subStep
                            ? t(`sending.sub.${item.subStep}`)
                            : undefined
                    }
                />
                {failed && item.lastError && (
                    <p
                        role="alert"
                        className="mt-4 text-center text-body text-danger"
                    >
                        {item.lastError}
                    </p>
                )}
            </div>
        </>
    );
};

const DAY_MS = 24 * 60 * 60 * 1000;

/** How many days from now until the window's next eligible day (0 = today). */
const daysUntilNextWindow = (
    win: NonNullable<LiveSending['window']>,
): number => {
    const now = new Date();
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: win.timezone,
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(now);
    const part = (type: string) =>
        parts.find((row) => row.type === type)?.value ?? '';
    const isWeekend = (weekday: string) => ['Sat', 'Sun'].includes(weekday);

    const resumesLaterToday =
        (!win.weekdaysOnly || !isWeekend(part('weekday'))) &&
        `${part('hour')}:${part('minute')}` < win.start;

    if (resumesLaterToday) {
        return 0;
    }

    let daysAhead = 1;

    while (daysAhead <= 7) {
        const weekday = new Intl.DateTimeFormat('en-US', {
            timeZone: win.timezone,
            weekday: 'short',
        }).format(new Date(now.getTime() + daysAhead * DAY_MS));

        if (!win.weekdaysOnly || !isWeekend(weekday)) {
            break;
        }

        daysAhead += 1;
    }

    return daysAhead;
};

export function LiveSendingCard({
    sending,
    mode,
    activity,
}: {
    sending: LiveSending;
    mode: SendMode;
    /** Recently settled applications, used to show why a send failed. */
    activity: ApplicationItem[];
}) {
    const { t, plural, locale } = useT();
    const format = useFormat();
    const action = useHeaderAction(sending);
    const { state } = sending;
    const running = state === 'sending' || state === 'waiting';
    const failedItem = useFailureHold(sending.current, activity, running);
    // Between two sends of a run the panel keeps the application the countdown
    // started from (all steps done). A send from an earlier run is not shown.
    const latestSent = activity.find((item) => item.status === 'sent');
    const lastSent =
        latestSent?.sentAt &&
        sending.waitStartedAt &&
        Math.abs(
            Date.parse(latestSent.sentAt) - Date.parse(sending.waitStartedAt),
        ) < 1000
            ? latestSent
            : null;
    const shown = failedItem ?? sending.current ?? lastSent;
    const clock = (hhmm: string) => {
        const [hours, minutes] = hhmm.split(':').map(Number);
        const date = new Date();

        date.setHours(hours ?? 0, minutes ?? 0, 0, 0);

        return format.date(date, { hour: '2-digit', minute: '2-digit' });
    };
    const windowResumeMessage = (win: LiveSending['window']): string => {
        if (!win) {
            return t('dashboard.live.window', { time: clock('00:00') });
        }

        const time = clock(win.start);
        const daysAhead = daysUntilNextWindow(win);

        if (daysAhead === 0) {
            return t('dashboard.live.window', { time });
        }

        if (daysAhead === 1) {
            return t('dashboard.live.window_tomorrow', { time });
        }

        const weekday = new Intl.DateTimeFormat(intlLocale(locale), {
            timeZone: win.timezone,
            weekday: 'long',
        }).format(new Date(Date.now() + daysAhead * DAY_MS));

        return t('dashboard.live.window_weekday', { weekday, time });
    };

    return (
        <DarkCard
            icon={Radio}
            title={t('dashboard.live.title')}
            subtitle={t('dashboard.live.subtitle')}
            actions={
                <>
                    {running && (
                        <Pill tone="dark" className="gap-2.5 py-2.5">
                            <LiveDot />
                            {t('dashboard.live.badge')}
                        </Pill>
                    )}
                    {action}
                </>
            }
        >
            {running && (
                <>
                    {(shown || sending.nextSendAt) && (
                        <div className="rounded-panel bg-dark-2 p-5">
                            {shown && (
                                <CurrentPanel
                                    item={shown}
                                    progress={
                                        shown === sending.current
                                            ? sending.progress
                                            : null
                                    }
                                    failed={failedItem !== null}
                                />
                            )}
                            {sending.waitStartedAt && sending.nextSendAt && (
                                <div className={shown ? 'mt-6' : undefined}>
                                    <CountdownBar
                                        startsAt={sending.waitStartedAt}
                                        endsAt={sending.nextSendAt}
                                        trailing={t('dashboard.live.spacing', {
                                            min: sending.spacing.minSeconds,
                                            max: sending.spacing.maxSeconds,
                                        })}
                                    />
                                </div>
                            )}
                        </div>
                    )}
                    {sending.queuedCount > 0 && (
                        <>
                            <div className="mx-0.5 mt-7 mb-3 flex items-center justify-between gap-4 text-body text-dark-muted">
                                <span>
                                    <b className="font-medium text-white">
                                        {t('dashboard.live.up_next')}
                                    </b>{' '}
                                    ·{' '}
                                    {plural(
                                        'dashboard.live.queued',
                                        sending.queuedCount,
                                    )}
                                </span>
                                {sending.estimatedFinishAt && (
                                    <span className="text-right">
                                        {t('dashboard.live.finish', {
                                            time: format.date(
                                                sending.estimatedFinishAt,
                                                {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                },
                                            ),
                                        })}
                                    </span>
                                )}
                            </div>
                            <div className="flex flex-col gap-2.5">
                                {sending.queue.slice(0, 3).map((item) => (
                                    <QueueRow
                                        key={item.id}
                                        company={item.company.name}
                                        title={item.title ?? item.company.name}
                                        meta={item.company.name}
                                        language={item.language}
                                        eta={
                                            item.scheduledFor
                                                ? format.relativeTime(
                                                      item.scheduledFor,
                                                  )
                                                : ''
                                        }
                                    />
                                ))}
                            </div>
                        </>
                    )}
                </>
            )}
            {state === 'idle' && (
                <Empty title={t('dashboard.live.idle.title')}>
                    {mode === 'random' ? (
                        <p className="text-body text-dark-muted">
                            {t('dashboard.live.idle.random')}
                        </p>
                    ) : (
                        <Button
                            variant="on-accent-white"
                            size="sm"
                            href={jobs().url}
                        >
                            {t('dashboard.live.idle.choose')}
                        </Button>
                    )}
                </Empty>
            )}
            {state === 'paused' && <Empty title={t('dashboard.live.paused')} />}
            {state === 'limit_reached' && (
                <Empty
                    title={t('dashboard.live.limit', {
                        time: clock(sending.window?.start ?? '00:00'),
                    })}
                >
                    <Button
                        variant="on-accent-white"
                        size="sm"
                        href={plans().url}
                    >
                        {t('dashboard.live.upgrade')}
                    </Button>
                </Empty>
            )}
            {state === 'outside_window' && (
                <Empty title={windowResumeMessage(sending.window)} />
            )}
        </DarkCard>
    );
}

/** Loading skeleton or error state, mirroring the card shell. */
export function LiveSendingPlaceholder({
    isError,
    onRetry,
}: {
    isError: boolean;
    onRetry: () => void;
}) {
    const { t } = useT();

    return (
        <DarkCard
            icon={Radio}
            title={t('dashboard.live.title')}
            subtitle={t('dashboard.live.subtitle')}
        >
            {isError ? (
                <Empty title={t('states.error.title')}>
                    <Button
                        variant="ghost-on-dark"
                        size="sm"
                        className="border border-dark-line"
                        onClick={onRetry}
                    >
                        {t('states.error.retry')}
                    </Button>
                </Empty>
            ) : (
                <div
                    role="status"
                    aria-busy="true"
                    aria-label={t('common.loading')}
                    className="flex flex-col gap-3"
                >
                    <Skeleton shape="block" className="h-44 bg-dark-2" />
                    <Skeleton shape="line" className="bg-dark-2" />
                    <Skeleton shape="line" className="w-2/3 bg-dark-2" />
                </div>
            )}
        </DarkCard>
    );
}
