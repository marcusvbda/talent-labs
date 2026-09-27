import { useQuery } from '@tanstack/react-query';
import { initialDataFrom } from '@/data/define-query';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { LiveSending } from '@/types/contracts';

const fixture = () => fixtureCall(() => fixtureState.liveSending());

export function useLiveSending(initial?: LiveSending) {
    return useQuery({
        queryKey: keys.sending(),
        queryFn: fromSource({ fixture }),
        ...initialDataFrom(initial),
    });
}
