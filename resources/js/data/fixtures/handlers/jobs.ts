import { toJobCard } from '@/data/fixtures/handlers/cards';
import { fixtureState } from '@/data/fixtures/state';
import type {
    JobDetail,
    JobFilters,
    JobLanguage,
    JobsPage,
} from '@/types/contracts';

export const JOBS_PAGE_SIZE = 12;

const LANGUAGES: JobLanguage[] = ['en', 'pt'];

const fold = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

const newestFirst = (a: JobDetail, b: JobDetail): number =>
    new Date(b.firstSeenAt).getTime() - new Date(a.firstSeenAt).getTime() ||
    b.id - a.id;

const matchesQuery = (job: JobDetail, q: string): boolean => {
    const terms = fold(q).split(/\s+/).filter(Boolean);
    const haystack = fold(
        [job.title, job.company.name, ...job.stack].join(' '),
    );

    return terms.every((term) => haystack.includes(term));
};

export const emptyJobsPage = (): JobsPage => ({
    data: [],
    meta: { nextCursor: null, total: 0 },
    summary: { total: 0, collectedToday: 0, lockedByLanguage: [] },
});

export function listJobs(
    filters: JobFilters,
    cursor: string | null = null,
): JobsPage {
    const { jobs, profiles } = fixtureState.get();
    const active = profiles
        .filter((profile) => profile.active && profile.complete)
        .map((profile) => profile.language);
    const explicit =
        filters.language !== undefined && filters.language !== 'all'
            ? filters.language
            : null;

    // 1. Language rule.
    const pool = jobs
        .filter((job) =>
            explicit
                ? job.language === explicit
                : active.includes(job.language),
        )
        .sort(newestFirst);

    const summaryPool = jobs.filter((job) => active.includes(job.language));
    const summary: JobsPage['summary'] = {
        total: summaryPool.length,
        collectedToday: summaryPool.filter((job) => job.collectedToday).length,
        lockedByLanguage: LANGUAGES.filter(
            (language) => !active.includes(language),
        )
            .map((language) => ({
                language,
                count: jobs.filter((job) => job.language === language).length,
            }))
            .filter((row) => row.count > 0),
    };

    const seniority = filters.seniority ?? [];
    const stack = (filters.stack ?? []).map(fold);
    const q = filters.q?.trim() ?? '';

    const filtered = pool.filter((job) => {
        if (q !== '' && !matchesQuery(job, q)) {
            return false;
        }

        if (filters.today && !job.collectedToday) {
            return false;
        }

        if (seniority.length > 0 && !seniority.includes(job.seniority)) {
            return false;
        }

        if (filters.remote === 'remote' && job.isRemote !== true) {
            return false;
        }

        if (filters.remote === 'not_remote' && job.isRemote === true) {
            return false;
        }

        if (
            stack.length > 0 &&
            !job.stack.some((s) => stack.includes(fold(s)))
        ) {
            return false;
        }

        return true;
    });

    const parsed = cursor === null ? 0 : Number.parseInt(cursor, 10);
    const offset = Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    const end = offset + JOBS_PAGE_SIZE;

    return {
        data: filtered.slice(offset, end).map(toJobCard),
        meta: {
            nextCursor: end < filtered.length ? String(end) : null,
            total: filtered.length,
        },
        summary,
    };
}

export function findJob(id: number): JobDetail | null {
    return fixtureState.get().jobs.find((job) => job.id === id) ?? null;
}
