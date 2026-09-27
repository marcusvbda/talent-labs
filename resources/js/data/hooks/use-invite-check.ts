import { useQuery } from '@tanstack/react-query';
import { ApiError } from '@/data/api';
import { checkInvite } from '@/data/fixtures/handlers/account';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { InviteCheck } from '@/types/contracts';

export function useInviteCheck(invite: string | null) {
    const real = (): Promise<InviteCheck> =>
        Promise.reject(new ApiError(501, 'Invites are not implemented yet.'));
    const fixture = () =>
        fixtureCall((): InviteCheck => checkInvite(invite ?? ''));

    return useQuery({
        queryKey: keys.invite(invite ?? ''),
        queryFn: fromSource({ real, fixture }),
        enabled: typeof invite === 'string' && invite !== '',
        retry: false,
    });
}
