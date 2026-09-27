import { useState } from 'react';
import { toast } from '@/components/ui/toast';
import { useT } from '@/i18n/i18n-provider';
import type { JobCard } from '@/types/contracts';

/**
 * Jobs picked for sending. One job per company: picking a second job of a
 * company already selected replaces the first. `remaining` is the sends left
 * today; picking more than that sets `overQuota`.
 */
export function useSelection(remaining: number) {
    const { t } = useT();
    const [selected, setSelected] = useState<JobCard[]>([]);

    const toggle = (job: JobCard) => {
        if (selected.some((item) => item.id === job.id)) {
            setSelected(selected.filter((item) => item.id !== job.id));

            return;
        }

        const sameCompany = selected.find(
            (item) => item.company.id === job.company.id,
        );

        if (sameCompany) {
            toast.info(t('send.one_per_company'));
        }

        setSelected([
            ...selected.filter((item) => item.company.id !== job.company.id),
            job,
        ]);
    };

    const clear = () => setSelected([]);

    // Keeps the current picks; jobs of an already-picked company are skipped.
    const selectAllOnPage = (jobs: JobCard[]) => {
        const next = [...selected];

        jobs.forEach((job) => {
            if (!next.some((item) => item.company.id === job.company.id)) {
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
