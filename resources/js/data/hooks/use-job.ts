import { useQuery } from '@tanstack/react-query';
import { ApiError, apiFetch } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { findJob } from '@/data/fixtures/handlers/jobs';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { JobDetail } from '@/types/contracts';

export function useJob(id: number | null) {
    const real = () => {
        const e = endpoints.job(id as number);

        return apiFetch<JobDetail>(e.url, { method: e.method });
    };
    const fixture = () =>
        fixtureCall(() => {
            const job = findJob(id as number);

            if (job === null) {
                throw new ApiError(404, 'Job not found');
            }

            return job;
        });

    return useQuery({
        queryKey: keys.jobs.detail(id ?? 'none'),
        queryFn: fromSource({ real, fixture }),
        enabled: id !== null,
    });
}
