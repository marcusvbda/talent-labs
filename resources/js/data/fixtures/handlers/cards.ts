import { fixtureState } from '@/data/fixtures/state';
import type { JobCard, JobDetail } from '@/types/contracts';

// Drops the detail-only fields of a stored job; matching stack tags come first.
export const toJobCard = (job: JobDetail): JobCard => {
    const wanted = new Set(
        fixtureState.get().preferences.stack.map((s) => s.trim().toLowerCase()),
    );
    const isMatch = (s: string) => wanted.has(s.trim().toLowerCase());
    const matches = job.stack.filter(isMatch);
    const rest = job.stack.filter((s) => !isMatch(s));

    return {
        id: job.id,
        company: job.company,
        title: job.title,
        location: job.location,
        isRemote: job.isRemote,
        language: job.language,
        seniority: job.seniority,
        stack: [...matches, ...rest].slice(0, 6),
        stackMatches: matches.slice(0, 6),
        summary: job.summary,
        firstSeenAt: job.firstSeenAt,
        collectedToday: job.collectedToday,
        jobUrl: job.jobUrl,
    };
};
