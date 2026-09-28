import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { QueryClient } from '@tanstack/react-query';
import { apiFetch, ApiError } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import { endpoints } from '@/data/endpoints';
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
        queryClient.invalidateQueries({ queryKey: keys.dashboards() }),
    ]);

export function useProfiles(initial?: ProfilesData) {
    const real = () => {
        const e = endpoints.profiles();

        return apiFetch<ProfilesData>(e.url, { method: e.method });
    };
    const fixture = () =>
        fixtureCall(() => profilesData(), { empty: emptyProfilesData });

    return useQuery({
        queryKey: keys.profiles(),
        queryFn: fromSource({ real, fixture }),
        ...initialDataFrom(initial),
    });
}

export function useCreateProfile() {
    const queryClient = useQueryClient();
    const real = ({ language }: { language: JobLanguage }) => {
        const e = endpoints.createProfile();

        return apiFetch<ApplicationProfile>(e.url, {
            method: e.method,
            body: { language },
        });
    };
    const fixture = ({ language }: { language: JobLanguage }) =>
        fixtureCall(() => createProfile(language));

    return useMutation<ApplicationProfile, ApiError, { language: JobLanguage }>(
        {
            mutationFn: fromSource({ real, fixture }),
            onSuccess: () => invalidateAfterProfileChange(queryClient),
        },
    );
}

// The UI works with fixture-style field names (subject/body); the backend
// uses emailSubject/emailBody on the wire, so a 422's error keys are
// remapped back to subject/body before the UI sees them.
export function useSaveProfile() {
    const queryClient = useQueryClient();
    const real = async (input: SaveProfileInput) => {
        const e = endpoints.saveProfile(input.language);

        try {
            return await apiFetch<ApplicationProfile>(e.url, {
                method: e.method,
                body: {
                    emailSubject: input.subject,
                    emailBody: input.body,
                    coverLetter: input.coverLetter,
                    active: input.active,
                },
            });
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                const remapped: Record<string, string[]> = {};

                Object.entries(error.errors ?? {}).forEach(
                    ([field, messages]) => {
                        const key =
                            field === 'emailSubject'
                                ? 'subject'
                                : field === 'emailBody'
                                  ? 'body'
                                  : field;

                        remapped[key] = messages;
                    },
                );

                throw new ApiError(error.status, error.message, remapped);
            }

            throw error;
        }
    };
    const fixture = (input: SaveProfileInput) =>
        fixtureCall(() => saveProfile(input));

    return useMutation<ApplicationProfile, ApiError, SaveProfileInput>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () => invalidateAfterProfileChange(queryClient),
    });
}

export function useDeleteProfile() {
    const queryClient = useQueryClient();
    const real = ({ language }: { language: JobLanguage }) => {
        const e = endpoints.deleteProfile(language);

        return apiFetch<Record<string, never>>(e.url, { method: e.method });
    };
    const fixture = ({ language }: { language: JobLanguage }) =>
        fixtureCall(() => deleteProfile(language));

    return useMutation<
        Record<string, never>,
        ApiError,
        { language: JobLanguage }
    >({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () => invalidateAfterProfileChange(queryClient),
    });
}
