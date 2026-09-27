import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { QueryClient } from '@tanstack/react-query';
import type { ApiError } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import {
    createProfile,
    deleteProfile,
    emptyProfilesData,
    profilesData,
    saveProfile,
} from '@/data/fixtures/handlers/profiles';
import type { SaveProfileInput } from '@/data/fixtures/handlers/profiles';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type {
    ApplicationProfile,
    JobLanguage,
    ProfilesData,
} from '@/types/contracts';

// A profile change moves the language lock, quota gating and job lists.
export const invalidateAfterProfileChange = (queryClient: QueryClient) =>
    Promise.all([
        queryClient.invalidateQueries({ queryKey: keys.profiles() }),
        queryClient.invalidateQueries({ queryKey: keys.account.status() }),
        queryClient.invalidateQueries({ queryKey: keys.jobs.all() }),
    ]);

export function useProfiles(initial?: ProfilesData) {
    const fixture = () =>
        fixtureCall(() => profilesData(), { empty: emptyProfilesData });

    return useQuery({
        queryKey: keys.profiles(),
        queryFn: fromSource({ fixture }),
        ...initialDataFrom(initial),
    });
}

export function useCreateProfile() {
    const queryClient = useQueryClient();
    const fixture = ({ language }: { language: JobLanguage }) =>
        fixtureCall(() => createProfile(language));

    return useMutation<ApplicationProfile, ApiError, { language: JobLanguage }>(
        {
            mutationFn: fromSource({ fixture }),
            onSuccess: () => invalidateAfterProfileChange(queryClient),
        },
    );
}

export function useSaveProfile() {
    const queryClient = useQueryClient();
    const fixture = (input: SaveProfileInput) =>
        fixtureCall(() => saveProfile(input));

    return useMutation<ApplicationProfile, ApiError, SaveProfileInput>({
        mutationFn: fromSource({ fixture }),
        onSuccess: () => invalidateAfterProfileChange(queryClient),
    });
}

export function useDeleteProfile() {
    const queryClient = useQueryClient();
    const fixture = ({ language }: { language: JobLanguage }) =>
        fixtureCall(() => deleteProfile(language));

    return useMutation<
        Record<string, never>,
        ApiError,
        { language: JobLanguage }
    >({
        mutationFn: fromSource({ fixture }),
        onSuccess: () => invalidateAfterProfileChange(queryClient),
    });
}
