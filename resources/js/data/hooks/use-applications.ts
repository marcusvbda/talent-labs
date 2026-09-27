import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import {
    countApplications,
    emptyApplicationCounts,
    emptyApplicationsPage,
    listApplications,
} from '@/data/fixtures/handlers/applications';
import type { ApplicationCounts } from '@/data/fixtures/handlers/applications';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type {
    ApplicationFilters,
    ApplicationItem,
    Paginated,
} from '@/types/contracts';

type Page = Paginated<ApplicationItem>;

export function useApplications(filters: ApplicationFilters) {
    const real = ({ pageParam }: { pageParam: string | null }) => {
        const e = endpoints.applications({ ...filters, cursor: pageParam });

        return apiFetch<Page>(e.url, { method: e.method });
    };
    const fixture = ({ pageParam }: { pageParam: string | null }) =>
        fixtureCall(() => listApplications(filters, pageParam), {
            empty: emptyApplicationsPage,
        });

    return useInfiniteQuery({
        queryKey: keys.applications.list(filters),
        queryFn: fromSource({ real, fixture }),
        initialPageParam: null as string | null,
        getNextPageParam: (last: Page) => last.meta.nextCursor,
    });
}

// Tab counters over the whole history, ignoring language and search.
export function useApplicationCounts() {
    const real = () => {
        const e = endpoints.applicationCounts();

        return apiFetch<ApplicationCounts>(e.url, { method: e.method });
    };
    const fixture = () =>
        fixtureCall(countApplications, { empty: emptyApplicationCounts });

    return useQuery({
        queryKey: [...keys.applications.all(), 'counts'] as const,
        queryFn: fromSource({ real, fixture }),
    });
}
