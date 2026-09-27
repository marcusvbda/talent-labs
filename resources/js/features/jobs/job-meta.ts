import type { JobCard } from '@/types/contracts';

export const jobMeta = (job: JobCard, remoteLabel: string): string =>
    [job.company.name, job.location, job.isRemote ? remoteLabel : null]
        .filter(Boolean)
        .join(' · ');
