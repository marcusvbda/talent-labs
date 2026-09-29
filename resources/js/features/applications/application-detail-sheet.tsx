import { ArrowUpRight } from 'lucide-react';
import { Fragment } from 'react';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { ErrorState } from '@/components/ui/error-state';
import { Sheet } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useApplication } from '@/data/hooks/use-application';
import { JOB_URL_TOKEN } from '@/features/review/body-editor';
import { useT } from '@/i18n/i18n-provider';
import type { ApplicationItem } from '@/types/contracts';

// Read-only body: the job link stays a token, shown as a chip.
const SnapshotBody = ({ body }: { body: string }) => {
    const { t } = useT();

    return (
        <p className="text-body whitespace-pre-wrap text-ink">
            {body.split(JOB_URL_TOKEN).map((part, index) => (
                <Fragment key={index}>
                    {index > 0 && (
                        <Chip variant="plan">{t('review.job_link')}</Chip>
                    )}
                    {part}
                </Fragment>
            ))}
        </p>
    );
};

const DetailBody = ({ item }: { item: ApplicationItem }) => {
    const { t } = useT();
    const { data, isError, refetch } = useApplication(item.id);
    // The row is kept live by the cache; the detail adds the snapshot.
    const status = data?.status ?? item.status;
    const lastError = data?.lastError ?? item.lastError;

    return (
        <div className="flex flex-col gap-6">
            {(status === 'failed' || status === 'ambiguous') && (
                <div
                    role="note"
                    className="flex flex-col gap-1 rounded-tile bg-tile p-card-sm text-body text-ink"
                >
                    <p>{t(`applications.${status}.explain`)}</p>
                    {lastError && (
                        <p className="text-muted">
                            {t('applications.reason', { reason: lastError })}
                        </p>
                    )}
                </div>
            )}
            {isError ? (
                <ErrorState onRetry={() => void refetch()} />
            ) : !data ? (
                <div
                    role="status"
                    aria-busy="true"
                    aria-label={t('common.loading')}
                    className="flex flex-col gap-3"
                >
                    <Skeleton shape="line" />
                    <Skeleton shape="block" className="h-32" />
                </div>
            ) : (
                <>
                    {data.jobUrl && (
                        <section className="flex flex-col gap-2">
                            <h3 className="text-label-sm text-muted">
                                {t('applications.detail.strengthen.title')}
                            </h3>
                            <p className="text-body text-muted">
                                {t('applications.detail.strengthen.text')}
                            </p>
                            <Button
                                variant="secondary-tile"
                                href={data.jobUrl}
                                external
                                iconRight={ArrowUpRight}
                            >
                                {t('applications.open_job_page')}
                            </Button>
                        </section>
                    )}
                    <section className="flex flex-col gap-2">
                        <h3 className="text-label-sm text-muted">
                            {t('applications.detail.subject')}
                        </h3>
                        <p className="text-body text-ink">{data.subject}</p>
                    </section>
                    <section className="flex flex-col gap-2">
                        <h3 className="text-label-sm text-muted">
                            {t('applications.detail.body')}
                        </h3>
                        <SnapshotBody body={data.body} />
                    </section>
                    {data.cvFileName && (
                        <section className="flex flex-col gap-2">
                            <h3 className="text-label-sm text-muted">
                                {t('applications.detail.cv')}
                            </h3>
                            <p className="text-body break-all text-ink">
                                {data.cvFileName}
                            </p>
                        </section>
                    )}
                </>
            )}
        </div>
    );
};

export function ApplicationDetailSheet({
    item,
    onClose,
}: {
    item: ApplicationItem | null;
    onClose: () => void;
}) {
    return (
        <Sheet
            open={item !== null}
            onClose={onClose}
            side="right"
            title={item?.company.name ?? ''}
        >
            {item && <DetailBody item={item} />}
        </Sheet>
    );
}
