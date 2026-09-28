import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { apiFetch, apiUpload } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { setProfileCv, validateCv } from '@/data/fixtures/handlers/profiles';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fromSource } from '@/data/source';
import { invalidateAfterProfileChange } from '@/data/hooks/use-profiles';
import type { ApiError } from '@/data/api';
import type { ApplicationProfile, JobLanguage } from '@/types/contracts';

type UploadVars = { language: JobLanguage; file: File };

export function useUploadCv() {
    const queryClient = useQueryClient();
    const [progress, setProgress] = useState<number | undefined>(undefined);

    const real = ({ language, file }: UploadVars) => {
        const e = endpoints.uploadCv(language);
        const form = new FormData();

        form.append('cv', file);

        return apiUpload<ApplicationProfile>(e.url, form, setProgress, 'post');
    };
    const fixture = ({ language, file }: UploadVars) =>
        fixtureCall(() => {
            validateCv(file);

            return setProfileCv(language, {
                fileName: file.name,
                sizeBytes: file.size,
                uploadedAt: new Date().toISOString(),
            });
        });

    const mutation = useMutation<ApplicationProfile, ApiError, UploadVars>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () => invalidateAfterProfileChange(queryClient),
        onSettled: () => setProgress(undefined),
    });

    return { ...mutation, progress };
}

export function useDeleteCv() {
    const queryClient = useQueryClient();
    const real = ({ language }: { language: JobLanguage }) => {
        const e = endpoints.deleteCv(language);

        return apiFetch<ApplicationProfile>(e.url, { method: e.method });
    };
    const fixture = ({ language }: { language: JobLanguage }) =>
        fixtureCall(() => setProfileCv(language, null));

    return useMutation<ApplicationProfile, ApiError, { language: JobLanguage }>(
        {
            mutationFn: fromSource({ real, fixture }),
            onSuccess: () => invalidateAfterProfileChange(queryClient),
        },
    );
}
