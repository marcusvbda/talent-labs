import { usePage } from '@inertiajs/react';
import type { NavPillItem } from '@/components/patterns/nav-pills';
import { useT } from '@/i18n/i18n-provider';
import { dashboard, plans, preferences, profiles } from '@/routes';

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
        ...(['jobs', 'applications'] as const).map((key) => ({
            key,
            label: t(`nav.${key}`),
            disabled: true,
        })),
        {
            key: 'profiles',
            label: t('nav.profiles'),
            href: profiles().url,
            active: isActive(profiles().url),
        },
        {
            key: 'preferences',
            label: t('nav.preferences'),
            href: preferences().url,
            active: isActive(preferences().url),
        },
        {
            key: 'plans',
            label: t('nav.plans'),
            href: plans().url,
            active: isActive(plans().url),
        },
    ];
}
