import type { PlanKey, RegionKey, SendMode } from '@/types/contracts';

export type LandingPlan = {
    key: PlanKey;
    mode: SendMode;
    dailyLimit: number;
    highlighted: boolean;
    prices: Record<RegionKey, { amount: number; currency: string }>;
};

/** Legal page URLs; null = the page does not exist yet and its link is hidden. */
export type LandingLegal = {
    privacy: string | null;
    terms: string | null;
    optOut: string | null;
};

export type LandingProps = {
    plans: LandingPlan[];
    defaultRegion: RegionKey;
    betaClosed: boolean;
    contactEmail: string | null;
    legal: LandingLegal;
};
