import type { PlanKey, PlanOffer, RegionKey } from '../../../types/contracts';

export const BILLING_AVAILABLE = false;

export const PLAN_REGIONS: { key: RegionKey; currency: string }[] = [
    { key: 'br', currency: 'BRL' },
    { key: 'eu', currency: 'EUR' },
    { key: 'row', currency: 'USD' },
];

// Prices are integer minor units (cents) per region.
const PRICES: Record<RegionKey, Record<PlanKey, number>> = {
    br: { free: 2500, starter: 5000, pro: 10000 },
    eu: { free: 500, starter: 900, pro: 1900 },
    row: { free: 500, starter: 1000, pro: 2000 },
};

const BASE: Omit<PlanOffer, 'price' | 'currency'>[] = [
    {
        key: 'free',
        name: 'Free',
        mode: 'random',
        dailyLimit: 25,
        interval: 'month',
        highlighted: false,
    },
    {
        key: 'starter',
        name: 'Starter',
        mode: 'select',
        dailyLimit: 50,
        interval: 'month',
        highlighted: true,
    },
    {
        key: 'pro',
        name: 'Pro',
        mode: 'review',
        dailyLimit: 150,
        interval: 'month',
        highlighted: false,
    },
];

const offersFor = (region: RegionKey, currency: string): PlanOffer[] =>
    BASE.map((plan) => ({
        ...plan,
        price: PRICES[region][plan.key],
        currency,
    }));

export const PLAN_OFFERS: Record<RegionKey, PlanOffer[]> = {
    br: offersFor('br', 'BRL'),
    eu: offersFor('eu', 'EUR'),
    row: offersFor('row', 'USD'),
};
