import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { plansData } from '@/data/fixtures/handlers/plans';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { PlansData, RegionKey } from '@/types/contracts';

export function usePlans(region?: RegionKey) {
    const real = () => {
        const e = endpoints.plans(region);

        return apiFetch<PlansData>(e.url, { method: e.method });
    };
    const fixture = () => fixtureCall(() => plansData(region));

    return useQuery({
        queryKey: keys.plans(region),
        queryFn: fromSource({ real, fixture }),
    });
}
