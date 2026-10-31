import { useMutation } from '@tanstack/react-query';
import { apiFetch, type ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { generateCoverLetter } from '@/data/fixtures/handlers/drafts';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fromSource } from '@/data/source';
import type { CoverLetterResult } from '@/types/contracts';

type Input = { jobId: number; regenerate?: boolean };

export function useGenerateCoverLetter() {
    const real = ({ jobId, regenerate }: Input) => {
        const e = endpoints.generateCoverLetter();

        return apiFetch<CoverLetterResult>(e.url, {
            method: e.method,
            body: { jobId, regenerate },
        });
    };
    const fixture = ({ jobId, regenerate }: Input) =>
        fixtureCall(() => generateCoverLetter(jobId, regenerate ?? false));

    return useMutation<CoverLetterResult, ApiError, Input>({
        mutationFn: fromSource({ real, fixture }),
    });
}
