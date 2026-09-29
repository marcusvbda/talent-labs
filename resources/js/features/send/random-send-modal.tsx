import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { toast } from '@/components/ui/toast';
import { useQueueRandom } from '@/data/hooks/use-queue-random';
import { useT } from '@/i18n/i18n-provider';

export function RandomSendModal({
    open,
    onClose,
    count,
    gmail,
}: {
    open: boolean;
    onClose: () => void;
    count: number;
    gmail: string;
}) {
    const { t, plural } = useT();
    const queue = useQueueRandom();

    const onConfirm = () => {
        queue.reset();
        queue.mutate(undefined, {
            onSuccess: (result) => {
                const parts = [
                    plural('send.confirm.queued', result.queued.length),
                ];

                if (result.rejected.length > 0) {
                    parts.push(
                        plural('send.random.skipped', result.rejected.length),
                    );
                }

                toast.success(parts.join(' · '));
                onClose();
            },
            onError: (error) => {
                if (error.status === 403) {
                    toast.error(error.message);
                } else if (error.status !== 422) {
                    toast.error(t('send.confirm.failed'));
                }
            },
        });
    };

    const error = queue.error?.status === 422 ? queue.error.message : null;

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={plural('send.random.title', count)}
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
                        {t('send.random.confirm')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <p className="text-body text-muted">
                    {t('send.random.body', { gmail })}
                </p>
                {error && (
                    <p role="alert" className="text-label-sm text-danger-text">
                        {error}
                    </p>
                )}
            </div>
        </Modal>
    );
}
