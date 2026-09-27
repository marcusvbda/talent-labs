import type {
    ApplicationFilters,
    ChartData,
    DashboardPeriod,
    JobFilters,
    JobLanguage,
    Preferences,
    RegionKey,
} from '@/types/contracts';

// Every key starts with its group name so invalidating `keys.jobs.all()`
// matches every jobs query.
export const keys = {
    account: {
        status: () => ['account', 'status'] as const,
        self: () => ['account', 'self'] as const,
    },
    dashboard: (period: DashboardPeriod) => ['dashboard', period] as const,
    chart: (range: ChartData['range']) => ['chart', range] as const,
    dashboards: () => ['dashboard'] as const,
    charts: () => ['chart'] as const,
    sending: () => ['sending'] as const,
    jobs: {
        all: () => ['jobs'] as const,
        list: (filters: JobFilters) => {
            const { cursor: _cursor, ...rest } = filters;

            return ['jobs', 'list', rest] as const;
        },
        detail: (id: number | string) => ['jobs', 'detail', id] as const,
    },
    applications: {
        all: () => ['applications'] as const,
        list: (filters: ApplicationFilters) => {
            const { cursor: _cursor, ...rest } = filters;

            return ['applications', 'list', rest] as const;
        },
        detail: (id: number | string) =>
            ['applications', 'detail', id] as const,
    },
    preferences: {
        current: () => ['preferences', 'current'] as const,
        preview: (draft: Preferences) =>
            ['preferences', 'preview', draft] as const,
        previews: () => ['preferences', 'preview'] as const,
    },
    profiles: () => ['profiles'] as const,
    profilesPreview: (input: {
        language: JobLanguage;
        subject: string;
        body: string;
        coverLetter: string;
    }) => ['profiles', 'preview', input] as const,
    plansAll: () => ['plans'] as const,
    plans: (region?: RegionKey) => ['plans', region ?? null] as const,
    notifications: () => ['notifications'] as const,
};
