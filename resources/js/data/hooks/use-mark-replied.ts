import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch, ApiError } from '@/data/api';
import { detailFor } from '@/data/fixtures/catalog';
import { endpoints } from '@/data/endpoints';
import { reviewedOverrides } from '@/data/fixtures/handlers/drafts';
import { fixtureCall } from '@/data/fixtures/runtime';
import { fixtureState } from '@/data/fixtures/state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { ApplicationDetail } from '@/types/contracts';

type Vars = { id: number; replied: boolean };

export function useMarkReplied() {
    const queryClient = useQueryClient();
    const real = ({ id, replied }: Vars) => {
        const e = endpoints.replyApplication(id);

        return apiFetch<ApplicationDetail>(e.url, {
            method: e.method,
            body: { replied },
        });
    };
    const fixture = ({ id, replied }: Vars) =>
        fixtureCall(() => {
            const row = fixtureState
                .get()
                .applications.find((entry) => entry.item.id === id);

            if (!row) {
                throw new ApiError(404, 'Application not found');
            }

            if (row.item.status !== 'sent') {
                throw new ApiError(422, 'Application not sent');
            }

            const item = fixtureState.patchApplication(id, {
                repliedAt: replied
                    ? (row.item.repliedAt ?? new Date().toISOString())
                    : null,
            });

            return {
                ...detailFor(item ?? row.item, row.jobId),
                ...reviewedOverrides.get(id),
                repliedAt: item?.repliedAt ?? null,
            } as ApplicationDetail;
        });

    return useMutation<ApplicationDetail, ApiError, Vars>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: (detail) => {
            queryClient.setQueryData(
                keys.applications.detail(detail.id),
                detail,
            );

            return queryClient.invalidateQueries({
                queryKey: keys.applications.all(),
            });
        },
    });
}
