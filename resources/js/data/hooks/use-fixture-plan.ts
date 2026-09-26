import { useDevState } from '@/data/fixtures/dev-state';
import { useFixtures } from '@/data/source';
import type { PlanKey } from '@/types/plans';

/** The dev-toolbar plan when fixtures are on; undefined against the real API. */
export function useFixturePlan(): PlanKey | undefined {
    const { plan } = useDevState();

    return useFixtures ? plan : undefined;
}
