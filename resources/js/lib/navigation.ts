import { usePage } from '@inertiajs/react';
import type { NavPillItem } from '@/components/patterns/nav-pills';
import { useT } from '@/i18n/i18n-provider';
import { dashboard } from '@/routes';

export function useMainNav(): NavPillItem[] {
    const { t } = useT();
    const { url } = usePage();
    const path = url.split(/[?#]/)[0];

    const isActive = (href: string) =>
        path === href || path.startsWith(`${href}/`);

    return [
        {
            key: 'dashboard',
            label: t('nav.dashboard'),
            href: dashboard().url,
            active: isActive(dashboard().url),
        },
        ...(
            [
                'jobs',
                'applications',
                'profiles',
                'preferences',
                'plans',
            ] as const
        ).map((key) => ({
            key,
            label: t(`nav.${key}`),
            disabled: true,
        })),
    ];
}
