import { useMutation, useQuery } from '@tanstack/react-query';
import { apiFetch, type ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import {
    billingSummary,
    checkoutResponse,
} from '@/data/fixtures/handlers/billing';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { BillingSummary, CheckoutResponse } from '@/types/contracts';
import type { PlanKey } from '@/types/plans';

export function useCheckout() {
    const real = ({ plan }: { plan: PlanKey }) => {
        const e = endpoints.billingCheckout();

        return apiFetch<CheckoutResponse>(e.url, {
            method: e.method,
            body: { plan },
        });
    };
    const fixture = (_variables: { plan: PlanKey }) =>
        fixtureCall(checkoutResponse);

    return useMutation<CheckoutResponse, ApiError, { plan: PlanKey }>({
        mutationFn: fromSource({ real, fixture }),
    });
}

export function usePortal() {
    const real = ({ returnTo }: { returnTo?: 'plans' | 'account' }) => {
        const e = endpoints.billingPortal();

        return apiFetch<CheckoutResponse>(e.url, {
            method: e.method,
            body: { returnTo },
        });
    };
    const fixture = (_variables: { returnTo?: 'plans' | 'account' }) =>
        fixtureCall(checkoutResponse);

    return useMutation<
        CheckoutResponse,
        ApiError,
        { returnTo?: 'plans' | 'account' }
    >({
        mutationFn: fromSource({ real, fixture }),
    });
}

export function useBilling() {
    const real = () => {
        const e = endpoints.billing();

        return apiFetch<BillingSummary>(e.url, { method: e.method });
    };
    const fixture = () => fixtureCall(billingSummary);

    return useQuery({
        queryKey: keys.billing(),
        queryFn: fromSource({ real, fixture }),
    });
}
