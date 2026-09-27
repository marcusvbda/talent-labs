import { useMutation } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import type { ApiError } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { buildDrafts } from '@/data/fixtures/handlers/drafts';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fromSource } from '@/data/source';
import type { ReviewDraft } from '@/types/contracts';

export function useReviewDrafts() {
    const real = ({ jobIds }: { jobIds: number[] }) => {
        const e = endpoints.reviewDrafts();

        return apiFetch<ReviewDraft[]>(e.url, {
            method: e.method,
            body: { jobIds },
        });
    };
    const fixture = ({ jobIds }: { jobIds: number[] }) =>
        fixtureCall(() => buildDrafts(jobIds));

    return useMutation<ReviewDraft[], ApiError, { jobIds: number[] }>({
        mutationFn: fromSource({ real, fixture }),
    });
}
