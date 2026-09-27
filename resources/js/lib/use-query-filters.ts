import { router, usePage } from '@inertiajs/react';
import { jobs } from '@/routes';
import type { JobFilters, JobLanguage, Seniority } from '@/types/contracts';

const LANGUAGES: JobLanguage[] = ['en', 'pt'];
const SENIORITIES: Seniority[] = [
    'intern',
    'junior',
    'mid',
    'senior',
    'lead',
    'unknown',
];
const REMOTE: NonNullable<JobFilters['remote']>[] = [
    'any',
    'remote',
    'not_remote',
];

const parse = (search: string): JobFilters => {
    const params = new URLSearchParams(search);
    const language = params.get('language') as JobLanguage | null;
    const remote = params.get('remote') as JobFilters['remote'] | null;

    return {
        q: params.get('q') ?? '',
        language: language && LANGUAGES.includes(language) ? language : 'all',
        seniority: params
            .getAll('seniority[]')
            .filter((value): value is Seniority =>
                SENIORITIES.includes(value as Seniority),
            ),
        remote: remote && REMOTE.includes(remote) ? remote : 'any',
        today: params.get('today') === '1',
        stack: params.getAll('stack[]').filter(Boolean),
    };
};

// Defaults are left out so the bare page keeps a clean URL.
const serialize = (filters: JobFilters): string => {
    const params = new URLSearchParams();

    if (filters.q) {
        params.set('q', filters.q);
    }

    if (filters.language && filters.language !== 'all') {
        params.set('language', filters.language);
    }

    filters.seniority?.forEach((value) => params.append('seniority[]', value));

    if (filters.remote && filters.remote !== 'any') {
        params.set('remote', filters.remote);
    }

    if (filters.today) {
        params.set('today', '1');
    }

    filters.stack?.forEach((value) => params.append('stack[]', value));

    return params.toString();
};

/**
 * Job filters mirrored in the query string. Every change is a history entry,
 * so back / forward restore the previous filter set.
 */
export function useQueryFilters(): [
    JobFilters,
    (patch: Partial<JobFilters>) => void,
] {
    // Subscribing to the page url re-renders on back / forward too.
    const { url } = usePage();
    const filters = parse(url.split('?')[1]?.split('#')[0] ?? '');

    const update = (patch: Partial<JobFilters>) => {
        const query = serialize({
            ...parse(window.location.search),
            ...patch,
        });

        router.get(
            query ? `${jobs().url}?${query}` : jobs().url,
            {},
            { preserveState: true, preserveScroll: true, replace: false },
        );
    };

    return [filters, update];
}
