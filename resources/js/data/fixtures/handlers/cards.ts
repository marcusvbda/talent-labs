import type { JobCard, JobDetail } from '@/types/contracts';

// Drops the detail-only fields of a stored job.
export const toJobCard = (job: JobDetail): JobCard => ({
    id: job.id,
    company: job.company,
    title: job.title,
    location: job.location,
    isRemote: job.isRemote,
    language: job.language,
    seniority: job.seniority,
    stack: job.stack,
    summary: job.summary,
    firstSeenAt: job.firstSeenAt,
    collectedToday: job.collectedToday,
    jobUrl: job.jobUrl,
});
