import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import { endpoints } from '@/data/endpoints';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { LiveSending } from '@/types/contracts';

const real = () => {
    const e = endpoints.sending();

    return apiFetch<LiveSending>(e.url, { method: e.method });
};

const fixture = () => fixtureCall(() => fixtureState.liveSending());

export function useLiveSending(initial?: LiveSending) {
    return useQuery({
        queryKey: keys.sending(),
        queryFn: fromSource({ real, fixture }),
        ...initialDataFrom(initial),
    });
}
