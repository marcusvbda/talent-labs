type Filters = Record<string, unknown>;

export const keys = {
    dashboard: () => ['dashboard'] as const,
    jobs: {
        all: () => ['jobs'] as const,
        list: (filters: Filters) => ['jobs', 'list', filters] as const,
    },
    applications: {
        all: () => ['applications'] as const,
        list: (filters: Filters) => ['applications', 'list', filters] as const,
    },
    account: {
        status: () => ['account', 'status'] as const,
    },
};
