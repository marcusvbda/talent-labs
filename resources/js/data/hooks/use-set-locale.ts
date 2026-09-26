import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { fromSource } from '@/data/source';
import { update as localeUpdate } from '@/routes/locale';
import type { Locale } from '@/types/shared';

const putLocale = (locale: Locale) =>
    apiFetch<void>(localeUpdate().url, { method: 'put', body: { locale } });

export function useSetLocale() {
    return useMutation({
        // Same function in both modes: translations are always server-provided.
        mutationFn: fromSource({ real: putLocale, fixture: putLocale }),
        onSuccess: () => {
            router.reload();
        },
    });
}
