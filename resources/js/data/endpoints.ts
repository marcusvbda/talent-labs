import type {
    ApplicationFilters,
    ChartData,
    DashboardPeriod,
    JobFilters,
    JobLanguage,
    RegionKey,
} from '@/types/contracts';

export type Endpoint = {
    url: string;
    method: 'get' | 'post' | 'put' | 'delete';
};

type QueryValue = string | number | boolean | null | undefined;

const withQuery = (
    path: string,
    params: Record<string, QueryValue | QueryValue[]>,
): string => {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach((item) => {
                if (item !== undefined && item !== null) {
                    search.append(`${key}[]`, String(item));
                }
            });

            return;
        }

        if (value === undefined || value === null) {
            return;
        }

        search.append(key, String(value));
    });

    const query = search.toString();

    return query === '' ? path : `${path}?${query}`;
};

const omitDefault = <T extends string>(
    value: T | undefined,
    defaultValue: T,
): T | undefined => (value === defaultValue ? undefined : value);

// Typed placeholders: a later spec replaces entries with Wayfinder helpers.
export const endpoints = {
    accountStatus: (): Endpoint => ({
        url: '/internal/account/status',
        method: 'get',
    }),
    dashboard: (period: DashboardPeriod): Endpoint => ({
        url: withQuery('/internal/dashboard', { period }),
        method: 'get',
    }),
    chart: (range: ChartData['range']): Endpoint => ({
        url: withQuery('/internal/dashboard/chart', { range }),
        method: 'get',
    }),
    sending: (): Endpoint => ({ url: '/internal/sending', method: 'get' }),
    pauseSending: (): Endpoint => ({
        url: '/internal/sending/pause',
        method: 'post',
    }),
    resumeSending: (): Endpoint => ({
        url: '/internal/sending/resume',
        method: 'post',
    }),
    jobs: (filters: JobFilters = {}): Endpoint => ({
        url: withQuery('/internal/jobs', {
            q: filters.q || undefined,
            language: omitDefault(filters.language, 'all'),
            seniority: filters.seniority,
            remote: omitDefault(filters.remote, 'any'),
            today: filters.today || undefined,
            stack: filters.stack,
            cursor: filters.cursor,
        }),
        method: 'get',
    }),
    job: (id: number | string): Endpoint => ({
        url: `/internal/jobs/${id}`,
        method: 'get',
    }),
    queueApplications: (): Endpoint => ({
        url: '/internal/applications',
        method: 'post',
    }),
    reviewDrafts: (): Endpoint => ({
        url: '/internal/applications/drafts',
        method: 'post',
    }),
    queueReviewed: (): Endpoint => ({
        url: '/internal/applications/reviewed',
        method: 'post',
    }),
    applications: (filters: ApplicationFilters = {}): Endpoint => ({
        url: withQuery('/internal/applications', {
            status: omitDefault(filters.status, 'all'),
            language: omitDefault(filters.language, 'all'),
            q: filters.q || undefined,
            cursor: filters.cursor,
        }),
        method: 'get',
    }),
    applicationCounts: (): Endpoint => ({
        url: '/internal/applications/counts',
        method: 'get',
    }),
    application: (id: number | string): Endpoint => ({
        url: `/internal/applications/${id}`,
        method: 'get',
    }),
    preferences: (): Endpoint => ({
        url: '/internal/preferences',
        method: 'get',
    }),
    savePreferences: (): Endpoint => ({
        url: '/internal/preferences',
        method: 'put',
    }),
    preferencesPreview: (): Endpoint => ({
        url: '/internal/preferences/preview',
        method: 'post',
    }),
    profiles: (): Endpoint => ({ url: '/internal/profiles', method: 'get' }),
    createProfile: (): Endpoint => ({
        url: '/internal/profiles',
        method: 'post',
    }),
    saveProfile: (language: JobLanguage): Endpoint => ({
        url: `/internal/profiles/${language}`,
        method: 'put',
    }),
    deleteProfile: (language: JobLanguage): Endpoint => ({
        url: `/internal/profiles/${language}`,
        method: 'delete',
    }),
    uploadCv: (language: JobLanguage): Endpoint => ({
        url: `/internal/profiles/${language}/cv`,
        method: 'post',
    }),
    deleteCv: (language: JobLanguage): Endpoint => ({
        url: `/internal/profiles/${language}/cv`,
        method: 'delete',
    }),
    templatePreview: (language: JobLanguage): Endpoint => ({
        url: `/internal/profiles/${language}/preview`,
        method: 'post',
    }),
    plans: (region?: RegionKey): Endpoint => ({
        url: withQuery('/internal/plans', { region }),
        method: 'get',
    }),
    notifications: (): Endpoint => ({
        url: '/internal/notifications',
        method: 'get',
    }),
    markAllNotificationsRead: (): Endpoint => ({
        url: '/internal/notifications/read-all',
        method: 'post',
    }),
    account: (): Endpoint => ({ url: '/internal/account', method: 'get' }),
    saveAccount: (): Endpoint => ({ url: '/internal/account', method: 'put' }),
    changePassword: (): Endpoint => ({
        url: '/internal/account/password',
        method: 'put',
    }),
    deleteAccount: (): Endpoint => ({
        url: '/internal/account',
        method: 'delete',
    }),
    saveOnboardingBasics: (): Endpoint => ({
        url: '/internal/onboarding/basics',
        method: 'put',
    }),
    accountExport: (): Endpoint => ({
        url: '/internal/account/export',
        method: 'get',
    }),
};
