import { useState } from 'react';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { toast } from '@/components/ui/toast';
import { useLiveSending } from '@/data/hooks/use-live-sending';
import { useQueueApplications } from '@/data/hooks/use-queue-applications';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type { JobCard } from '@/types/contracts';

const ConfirmBody = ({
    selected,
    gmail,
    error,
}: {
    selected: JobCard[];
    gmail: string;
    error: string | null;
}) => {
    const { t } = useT();
    const format = useFormat();
    const { data } = useLiveSending();
    // Captured once per opening; the modal body is unmounted while closed.
    const [now] = useState(() => Date.now());
    const minSeconds = data?.spacing.minSeconds ?? 0;
    const maxSeconds = data?.spacing.maxSeconds ?? 0;
    const averageMs = ((minSeconds + maxSeconds) / 2) * 1000;
    const finish =
        now + ((data?.queuedCount ?? 0) + selected.length) * averageMs;

    return (
        <div className="flex flex-col gap-5">
            <ul className="flex flex-col gap-2">
                {selected.map((job) => (
                    <li
                        key={job.id}
                        className="flex items-center gap-3 rounded-row bg-tile px-4 py-3"
                    >
                        <CompanyLogo name={job.company.name} size="sm" />
                        <span className="min-w-0 truncate text-body text-ink">
                            {job.company.name}
                        </span>
                    </li>
                ))}
            </ul>
            <p className="text-body text-muted">
                {t('send.confirm.body', {
                    gmail,
                    min: minSeconds,
                    max: maxSeconds,
                    time: format.date(finish, { timeStyle: 'short' }),
                })}
            </p>
            {error && (
                <p role="alert" className="text-label-sm text-danger-text">
                    {error}
                </p>
            )}
        </div>
    );
};

export function ConfirmSendModal({
    open,
    onClose,
    selected,
    gmail,
    onQueued,
}: {
    open: boolean;
    onClose: () => void;
    selected: JobCard[];
    gmail: string;
    onQueued: () => void;
}) {
    const { t, plural } = useT();
    const queue = useQueueApplications();

    const onConfirm = () => {
        queue.reset();
        queue.mutate(
            { jobIds: selected.map((job) => job.id) },
            {
                onSuccess: (result) => {
                    const rejected = result.rejected.map((row) => {
                        const company = selected.find(
                            (job) => job.id === row.jobId,
                        )?.company.name;

                        return `${company ?? ''}: ${row.reason}`;
                    });

                    toast.success(
                        [
                            plural('send.confirm.queued', result.queued.length),
                            ...rejected,
                        ].join(' · '),
                    );
                    onQueued();
                },
                onError: (error) => {
                    if (error.status === 403) {
                        toast.error(error.message);
                    } else if (error.status !== 422) {
                        toast.error(t('send.confirm.failed'));
                    }
                },
            },
        );
    };

    const error =
        queue.error?.status === 422
            ? (Object.values(queue.error.errors ?? {})[0]?.[0] ??
              queue.error.message)
            : null;

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={plural('send.confirm.title', selected.length)}
            footer={
                <>
                    <Button
                        variant="secondary-tile"
                        disabled={queue.isPending}
                        onClick={onClose}
                    >
                        {t('send.confirm.cancel')}
                    </Button>
                    <Button loading={queue.isPending} onClick={onConfirm}>
                        {t('send.confirm.send')}
                    </Button>
                </>
            }
        >
            <ConfirmBody selected={selected} gmail={gmail} error={error} />
        </Modal>
    );
}
