import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import { endpoints } from '@/data/endpoints';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { AccountStatus } from '@/types/contracts';

const real = () => {
    const e = endpoints.accountStatus();

    return apiFetch<AccountStatus>(e.url, { method: e.method });
};

const fixture = () => fixtureCall(() => fixtureState.accountStatus());

export function useCanChooseJobs(): boolean {
    const mode = useAccountStatus().data?.plan.mode;

    return mode === 'select' || mode === 'review';
}

export function useAccountStatus(initial?: AccountStatus) {
    return useQuery({
        queryKey: keys.account.status(),
        queryFn: fromSource({ real, fixture }),
        ...initialDataFrom(initial),
    });
}
