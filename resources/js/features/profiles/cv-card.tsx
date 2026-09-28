import { FileText } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { FileDrop } from '@/components/ui/file-drop';
import { Modal } from '@/components/ui/modal';
import { toast } from '@/components/ui/toast';
import { useDeleteCv, useUploadCv } from '@/data/hooks/use-cv';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type { ApplicationProfile, JobLanguage } from '@/types/contracts';

const BYTES_PER_MB = 1024 * 1024;

export const CvCard = ({
    language,
    cv,
}: {
    language: JobLanguage;
    cv: ApplicationProfile['cv'];
}) => {
    const { t } = useT();
    const format = useFormat();
    const upload = useUploadCv();
    const remove = useDeleteCv();
    const [replacing, setReplacing] = useState(false);
    const [confirming, setConfirming] = useState(false);

    const onFile = (file: File) => {
        upload.mutate(
            { language, file },
            {
                onSuccess: () => {
                    setReplacing(false);
                    toast.success(t('profiles.cv.uploaded'));
                },
            },
        );
    };

    const onRemove = () =>
        remove.mutate(
            { language },
            {
                onSuccess: () => {
                    setConfirming(false);
                    toast.success(t('profiles.cv.removed'));
                },
                onError: () => toast.error(t('profiles.cv.remove_failed')),
            },
        );

    return (
        <div className="flex flex-col gap-3">
            <h3 className="text-label-sm text-ink">{t('profiles.cv.title')}</h3>
            {cv ? (
                <div className="flex flex-col gap-3 rounded-tile bg-tile p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex min-w-0 items-center gap-3">
                        <FileText
                            aria-hidden="true"
                            strokeWidth={1.8}
                            className="size-5 shrink-0 text-muted"
                        />
                        <div className="min-w-0">
                            <p className="truncate text-body text-ink">
                                {cv.fileName}
                            </p>
                            <p className="text-chip text-muted">
                                {t('profiles.cv.meta', {
                                    size: format.number(
                                        cv.sizeBytes / BYTES_PER_MB,
                                        { maximumFractionDigits: 1 },
                                    ),
                                    date: format.date(cv.uploadedAt),
                                })}
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <Button
                            variant="secondary-tile"
                            size="sm"
                            className="bg-card"
                            onClick={() => setReplacing((value) => !value)}
                        >
                            {replacing
                                ? t('profiles.cancel')
                                : t('profiles.cv.replace')}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-danger-text"
                            onClick={() => setConfirming(true)}
                        >
                            {t('profiles.cv.remove')}
                        </Button>
                    </div>
                </div>
            ) : null}
            {!cv || replacing ? (
                <FileDrop
                    accept="application/pdf"
                    maxSizeMb={5}
                    progress={
                        upload.isPending ? (upload.progress ?? 0) : undefined
                    }
                    error={
                        upload.error?.errors?.cv?.[0] ?? upload.error?.message
                    }
                    onFile={onFile}
                />
            ) : null}
            <Modal
                open={confirming}
                onClose={() => setConfirming(false)}
                size="sm"
                title={t('profiles.cv.remove_title')}
                description={t('profiles.cv.remove_body')}
                footer={
                    <>
                        <Button
                            variant="secondary-tile"
                            onClick={() => setConfirming(false)}
                        >
                            {t('profiles.cancel')}
                        </Button>
                        <Button loading={remove.isPending} onClick={onRemove}>
                            {t('profiles.cv.remove')}
                        </Button>
                    </>
                }
            />
        </div>
    );
};
