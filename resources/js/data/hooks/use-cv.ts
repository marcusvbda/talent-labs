import { useMutation, useQueryClient } from '@tanstack/react-query';
import { setProfileCv, validateCv } from '@/data/fixtures/handlers/profiles';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fromSource } from '@/data/source';
import { invalidateAfterProfileChange } from '@/data/hooks/use-profiles';
import type { ApiError } from '@/data/api';
import type { ApplicationProfile, JobLanguage } from '@/types/contracts';

type UploadVars = { language: JobLanguage; file: File };

export function useUploadCv() {
    const queryClient = useQueryClient();
    const fixture = ({ language, file }: UploadVars) =>
        fixtureCall(() => {
            validateCv(file);

            return setProfileCv(language, {
                fileName: file.name,
                sizeBytes: file.size,
                uploadedAt: new Date().toISOString(),
            });
        });

    return useMutation<ApplicationProfile, ApiError, UploadVars>({
        mutationFn: fromSource({ fixture }),
        onSuccess: () => invalidateAfterProfileChange(queryClient),
    });
}

export function useDeleteCv() {
    const queryClient = useQueryClient();
    const fixture = ({ language }: { language: JobLanguage }) =>
        fixtureCall(() => setProfileCv(language, null));

    return useMutation<ApplicationProfile, ApiError, { language: JobLanguage }>(
        {
            mutationFn: fromSource({ fixture }),
            onSuccess: () => invalidateAfterProfileChange(queryClient),
        },
    );
}
