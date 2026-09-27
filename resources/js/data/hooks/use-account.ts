import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import type { ApiError } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import { endpoints } from '@/data/endpoints';
import { changePassword, saveAccount } from '@/data/fixtures/handlers/account';
import type {
    ChangePasswordInput,
    SaveAccountInput,
} from '@/data/fixtures/handlers/account';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { Account } from '@/types/contracts';

type Empty = Record<string, never>;

export function useAccount(initial?: Account) {
    const real = () => {
        const e = endpoints.account();

        return apiFetch<Account>(e.url, { method: e.method });
    };
    const fixture = () => fixtureCall(() => fixtureState.account());

    return useQuery({
        queryKey: keys.account.self(),
        queryFn: fromSource({ real, fixture }),
        ...initialDataFrom(initial),
    });
}

export function useSaveAccount() {
    const queryClient = useQueryClient();
    const real = (input: SaveAccountInput) => {
        const e = endpoints.saveAccount();

        return apiFetch<Account>(e.url, {
            method: e.method,
            body: {
                name: input.name,
                locale: input.locale,
                timezone: input.timezone,
                country: input.country,
            },
        });
    };
    const fixture = (input: SaveAccountInput) =>
        fixtureCall(() => saveAccount(input));

    return useMutation<Account, ApiError, SaveAccountInput>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: (account) => {
            queryClient.setQueryData(keys.account.self(), account);

            return Promise.all([
                queryClient.invalidateQueries({
                    queryKey: keys.account.status(),
                }),
                queryClient.invalidateQueries({ queryKey: keys.plansAll() }),
            ]);
        },
    });
}

export function useChangePassword() {
    const real = (input: ChangePasswordInput) => {
        const e = endpoints.changePassword();

        return apiFetch<Empty>(e.url, {
            method: e.method,
            body: {
                current_password: input.currentPassword,
                password: input.password,
                password_confirmation: input.passwordConfirmation,
            },
        });
    };
    const fixture = (input: ChangePasswordInput) =>
        fixtureCall(() => changePassword(input));

    return useMutation<Empty, ApiError, ChangePasswordInput>({
        mutationFn: fromSource({ real, fixture }),
    });
}

export function useDeleteAccount() {
    const real = ({ password }: { password: string }) => {
        const e = endpoints.deleteAccount();

        return apiFetch<Empty>(e.url, { method: e.method, body: { password } });
    };
    const fixture = (_input: { password: string }): Promise<Empty> =>
        fixtureCall((): Empty => ({}));

    return useMutation<Empty, ApiError, { password: string }>({
        mutationFn: fromSource({ real, fixture }),
    });
}
