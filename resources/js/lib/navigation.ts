import { usePage } from '@inertiajs/react';
import type { NavPillItem } from '@/components/patterns/nav-pills';
import { useT } from '@/i18n/i18n-provider';
import {
    applications,
    dashboard,
    jobs,
    plans,
    preferences,
    profiles,
} from '@/routes';

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
        {
            key: 'jobs',
            label: t('nav.jobs'),
            href: jobs().url,
            active: isActive(jobs().url),
        },
        {
            key: 'applications',
            label: t('nav.applications'),
            href: applications().url,
            active: isActive(applications().url),
        },
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
