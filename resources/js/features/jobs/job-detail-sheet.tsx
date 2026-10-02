import { ArrowUpRight } from 'lucide-react';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { ErrorState } from '@/components/ui/error-state';
import { Sheet } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useJob } from '@/data/hooks/use-job';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type { JobCard } from '@/types/contracts';

const Fact = ({ label, value }: { label: string; value: string | null }) =>
    value ? (
        <div className="flex flex-col gap-1">
            <dt className="text-label-sm text-muted">{label}</dt>
            <dd className="text-body text-ink">{value}</dd>
        </div>
    ) : null;

const DetailBody = ({
    job,
    selectable,
    selected,
    onToggle,
}: {
    job: JobCard;
    selectable: boolean;
    selected: boolean;
    onToggle: (job: JobCard) => void;
}) => {
    const { t } = useT();
    const format = useFormat();
    const { data, isError, refetch } = useJob(job.id);

    // The card already carries the header; the rest arrives with the detail.
    const locations = data?.locations.length
        ? format.list(data.locations, 'unit')
        : job.location;
    const place = [locations, job.isRemote ? t('jobs.remote') : null]
        .filter(Boolean)
        .join(' · ');

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-center gap-4">
                <CompanyLogo name={job.company.name} size="lg" />
                <div className="min-w-0">
                    <p className="text-row-title text-ink">
                        {job.company.name}
                    </p>
                    <p className="text-body text-muted">{place}</p>
                </div>
            </div>
            <dl className="grid grid-cols-2 gap-4">
                <Fact
                    label={t('jobs.detail.seniority')}
                    value={t(`jobs.seniority.${job.seniority}`)}
                />
                <Fact
                    label={t('jobs.detail.language')}
                    value={t(`jobs.language.${job.language}`)}
                />
                {data && (
                    <>
                        <Fact
                            label={t('jobs.detail.employment_type')}
                            value={data.employmentType}
                        />
                        <Fact
                            label={t('jobs.detail.department')}
                            value={data.department}
                        />
                        <Fact
                            label={t('jobs.detail.published')}
                            value={
                                data.publishedAt
                                    ? format.date(data.publishedAt)
                                    : null
                            }
                        />
                    </>
                )}
            </dl>
            {job.stack.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {job.stack.map((item) => (
                        <Chip key={item} variant="stack">
                            {item}
                        </Chip>
                    ))}
                </div>
            )}
            {job.summary && <p className="text-body text-ink">{job.summary}</p>}
            {isError ? (
                <ErrorState onRetry={() => void refetch()} />
            ) : data ? (
                <p className="text-label-sm text-muted">
                    {t('jobs.detail.source', { label: data.sourceLabel })}
                </p>
            ) : (
                <Skeleton shape="line" className="w-1/2" />
            )}
            {data?.jobUrl && (
                <Button
                    variant="secondary-tile"
                    className="w-full"
                    href={data.jobUrl}
                    external
                    iconRight={ArrowUpRight}
                >
                    {t('applications.open_job_page')}
                </Button>
            )}
            <Button
                variant={selected ? 'secondary-tile' : 'primary-ink'}
                className="w-full"
                disabled={!selectable}
                onClick={() => onToggle(job)}
            >
                {selected ? t('jobs.detail.deselect') : t('jobs.detail.select')}
            </Button>
        </div>
    );
};

export function JobDetailSheet({
    job,
    onClose,
    selectable,
    selected,
    onToggle,
}: {
    job: JobCard | null;
    onClose: () => void;
    selectable: boolean;
    selected: boolean;
    onToggle: (job: JobCard) => void;
}) {
    return (
        <Sheet
            open={job !== null}
            onClose={onClose}
            side="right"
            title={job?.title ?? ''}
        >
            {job && (
                <DetailBody
                    job={job}
                    selectable={selectable}
                    selected={selected}
                    onToggle={onToggle}
                />
            )}
        </Sheet>
    );
}
