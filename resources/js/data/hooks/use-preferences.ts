import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { ApiError } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import {
    currentPreferences,
    savePreferences,
} from '@/data/fixtures/handlers/preferences';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { Preferences } from '@/types/contracts';

export function usePreferences(initial?: Preferences) {
    const fixture = () => fixtureCall(() => currentPreferences());

    return useQuery({
        queryKey: keys.preferences.current(),
        queryFn: fromSource({ fixture }),
        ...initialDataFrom(initial),
    });
}

export function useSavePreferences() {
    const queryClient = useQueryClient();
    const fixture = (preferences: Preferences) =>
        fixtureCall(() => savePreferences(preferences));

    return useMutation<Preferences, ApiError, Preferences>({
        mutationFn: fromSource({ fixture }),
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
