import {
    BILLING_AVAILABLE,
    PLAN_OFFERS,
    PLAN_REGIONS,
} from '@/data/fixtures/catalog';
import { getDevState } from '@/data/fixtures/dev-state';
import { fixtureState } from '@/data/fixtures/state';
import type { PlansData, RegionKey } from '@/types/contracts';

export function plansData(region?: RegionKey): PlansData {
    const selected = region ?? fixtureState.accountStatus().region;

    return {
        region: selected,
        regions: PLAN_REGIONS.map((row) => ({ ...row })),
        plans: PLAN_OFFERS[selected].map((plan) => ({ ...plan })),
        current: getDevState().plan,
        billingAvailable: BILLING_AVAILABLE,
        contactEmail: 'hello@example.com',
        hasSubscription: false,
        checkoutBlocked: false,
    };
}
