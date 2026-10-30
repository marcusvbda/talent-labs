import type { JobCard } from '@/types/contracts';

export const collectedLabel = (
    job: JobCard,
    t: (key: string) => string,
    plural: (key: string, count: number) => string,
): string =>
    job.collectedDaysAgo <= 0
        ? t('jobs.collected.today')
        : plural('jobs.collected', job.collectedDaysAgo);

export const jobMeta = (job: JobCard, remoteLabel: string): string =>
    [job.company.name, job.location, job.isRemote ? remoteLabel : null]
        .filter(Boolean)
        .join(' · ');
