import { useInfiniteQuery } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { emptyJobsPage, listJobs } from '@/data/fixtures/handlers/jobs';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { JobFilters, JobsPage } from '@/types/contracts';

// The screen reads `summary` from the first page.
export function useJobs(filters: JobFilters, initial?: JobsPage) {
    const real = ({ pageParam }: { pageParam: string | null }) => {
        const e = endpoints.jobs({ ...filters, cursor: pageParam });

        return apiFetch<JobsPage>(e.url, { method: e.method });
    };
    const fixture = ({ pageParam }: { pageParam: string | null }) =>
        fixtureCall(() => listJobs(filters, pageParam), {
            empty: emptyJobsPage,
        });

    return useInfiniteQuery({
        queryKey: keys.jobs.list(filters),
        queryFn: fromSource({ real, fixture }),
        initialPageParam: null as string | null,
        getNextPageParam: (last: JobsPage) => last.meta.nextCursor,
        ...(initial === undefined
            ? {}
            : { initialData: { pages: [initial], pageParams: [null] } }),
    });
}
