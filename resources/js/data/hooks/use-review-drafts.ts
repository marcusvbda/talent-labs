import { useMutation } from '@tanstack/react-query';
import type { ApiError } from '@/data/api';
import { buildDrafts } from '@/data/fixtures/handlers/drafts';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fromSource } from '@/data/source';
import type { ReviewDraft } from '@/types/contracts';

export function useReviewDrafts() {
    const fixture = ({ jobIds }: { jobIds: number[] }) =>
        fixtureCall(() => buildDrafts(jobIds));

    return useMutation<ReviewDraft[], ApiError, { jobIds: number[] }>({
        mutationFn: fromSource({ fixture }),
    });
}
