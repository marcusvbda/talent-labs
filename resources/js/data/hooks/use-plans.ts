import { useQuery } from '@tanstack/react-query';
import { plansData } from '@/data/fixtures/handlers/plans';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { RegionKey } from '@/types/contracts';

export function usePlans(region?: RegionKey) {
    const fixture = () => fixtureCall(() => plansData(region));

    return useQuery({
        queryKey: keys.plans(region),
        queryFn: fromSource({ fixture }),
    });
}
