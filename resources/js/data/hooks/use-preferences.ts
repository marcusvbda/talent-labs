import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import type { ApiError } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import { endpoints } from '@/data/endpoints';
import {
    currentPreferences,
    savePreferences,
} from '@/data/fixtures/handlers/preferences';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { Preferences } from '@/types/contracts';

export function usePreferences(initial?: Preferences) {
    const real = () => {
        const e = endpoints.preferences();

        return apiFetch<Preferences>(e.url, { method: e.method });
    };
    const fixture = () => fixtureCall(() => currentPreferences());

    return useQuery({
        queryKey: keys.preferences.current(),
        queryFn: fromSource({ real, fixture }),
        ...initialDataFrom(initial),
    });
}

export function useSavePreferences() {
    const queryClient = useQueryClient();
    const real = (preferences: Preferences) => {
        const e = endpoints.savePreferences();

        return apiFetch<Preferences>(e.url, {
            method: e.method,
            body: preferences,
        });
    };
    const fixture = (preferences: Preferences) =>
        fixtureCall(() => savePreferences(preferences));

    return useMutation<Preferences, ApiError, Preferences>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: (saved) => {
            queryClient.setQueryData(keys.preferences.current(), saved);

            return Promise.all([
                queryClient.invalidateQueries({ queryKey: keys.jobs.all() }),
                queryClient.invalidateQueries({ queryKey: keys.dashboards() }),
                queryClient.invalidateQueries({
                    queryKey: keys.preferences.previews(),
                }),
            ]);
        },
    });
}
