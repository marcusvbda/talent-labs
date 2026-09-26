import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from '@/components/ui/toast';
import type { SharedProps } from '@/types/shared';

export function useFlashToasts() {
    const { flash } = usePage<SharedProps>().props;

    // `flash` is a new object on every visit and stable across re-renders,
    // so each visit surfaces its messages exactly once.
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);
}
