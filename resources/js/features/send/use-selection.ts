import { useState } from 'react';
import type { JobCard } from '@/types/contracts';

/**
 * Jobs picked for sending. `remaining` is the sends left today; picking more
 * than that sets `overQuota`.
 */
export function useSelection(remaining: number) {
    const [selected, setSelected] = useState<JobCard[]>([]);

    const toggle = (job: JobCard) => {
        setSelected(
            selected.some((item) => item.id === job.id)
                ? selected.filter((item) => item.id !== job.id)
                : [...selected, job],
        );
    };

    const clear = () => setSelected([]);

    // Keeps the current picks and adds the rest of the page.
    const selectAllOnPage = (jobs: JobCard[]) => {
        const next = [...selected];

        jobs.forEach((job) => {
            if (!next.some((item) => item.id === job.id)) {
                next.push(job);
            }
        });

        setSelected(next);
    };

    const isSelected = (id: number) => selected.some((item) => item.id === id);

    return {
        selected,
        toggle,
        clear,
        selectAllOnPage,
        isSelected,
        overQuota: selected.length > remaining,
    };
}
