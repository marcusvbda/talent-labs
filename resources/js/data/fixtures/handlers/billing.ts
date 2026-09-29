import type { BillingSummary, CheckoutResponse } from '@/types/contracts';

export const checkoutResponse = (): CheckoutResponse => ({
    url: '/plans?checkout=success',
});

export const billingSummary = (): BillingSummary => ({
    hasCustomer: false,
    source: 'manual',
    plan: { key: 'free', name: 'Free' },
    status: null,
    renewsAt: null,
    endsAt: null,
});
