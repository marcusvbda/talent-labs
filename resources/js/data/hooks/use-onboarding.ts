import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import type { ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { saveOnboardingBasics } from '@/data/fixtures/handlers/account';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { AccountStatus, OnboardingBasics } from '@/types/contracts';

export function useSaveOnboardingBasics() {
    const queryClient = useQueryClient();
    const real = (input: OnboardingBasics) => {
        const e = endpoints.saveOnboardingBasics();

        return apiFetch<AccountStatus>(e.url, {
            method: e.method,
            body: {
                country: input.country,
                locale: input.locale,
                timezone: input.timezone,
            },
        });
    };
    const fixture = (input: OnboardingBasics) =>
        fixtureCall(() => saveOnboardingBasics(input));

    return useMutation<AccountStatus, ApiError, OnboardingBasics>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: (status) => {
            queryClient.setQueryData(keys.account.status(), status);

            return Promise.all([
                queryClient.invalidateQueries({
                    queryKey: keys.account.self(),
                }),
                queryClient.invalidateQueries({ queryKey: keys.plansAll() }),
            ]);
        },
    });
}
