import { useQuery } from '@tanstack/react-query';
import { ApiError, apiFetch } from '@/data/api';
import { detailFor } from '@/data/fixtures/catalog';
import { endpoints } from '@/data/endpoints';
import { reviewedOverrides } from '@/data/fixtures/handlers/drafts';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { ApplicationDetail } from '@/types/contracts';

export function useApplication(id: number | null) {
    const real = () => {
        const e = endpoints.application(id ?? 'none');

        return apiFetch<ApplicationDetail>(e.url, { method: e.method });
    };
    const fixture = () =>
        fixtureCall(() => {
            const row = fixtureState
                .get()
                .applications.find((entry) => entry.item.id === id);

            if (!row) {
                throw new ApiError(404, 'Application not found');
            }

            const detail = detailFor(row.item, row.jobId);
            const reviewed = reviewedOverrides.get(row.item.id);

            return reviewed ? { ...detail, ...reviewed } : detail;
        });

    return useQuery({
        queryKey: keys.applications.detail(id ?? 'none'),
        queryFn: fromSource({ real, fixture }),
        enabled: id !== null,
    });
}
