import { JobRow } from '@/components/patterns/job-row';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import type { JobCard } from '@/types/contracts';
import { jobMeta } from './job-meta';

export function JobsList({
    jobs,
    selectable,
    isSelected,
    onToggle,
    onOpen,
    hasMore,
    loadingMore,
    onLoadMore,
}: {
    jobs: JobCard[];
    selectable: boolean;
    isSelected: (id: number) => boolean;
    onToggle: (job: JobCard) => void;
    onOpen: (job: JobCard) => void;
    hasMore: boolean;
    loadingMore: boolean;
    onLoadMore: () => void;
}) {
    const { t } = useT();

    return (
        <div className="flex flex-col gap-3">
            {jobs.map((job) => (
                <JobRow
                    key={job.id}
                    selected={selectable && isSelected(job.id)}
                    onSelectedChange={() => onToggle(job)}
                    company={job.company.name}
                    title={job.title}
                    meta={jobMeta(job, t('jobs.remote'))}
                    stack={job.stack}
                    language={job.language}
                    selectable={selectable}
                    onOpen={() => onOpen(job)}
                />
            ))}
            {hasMore && (
                <div className="flex justify-center pt-2">
                    <Button
                        variant="secondary-tile"
                        loading={loadingMore}
                        onClick={onLoadMore}
                    >
                        {t('jobs.load_more')}
                    </Button>
                </div>
            )}
        </div>
    );
}
