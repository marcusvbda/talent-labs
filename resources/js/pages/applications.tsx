import { Head, usePage } from '@inertiajs/react';
import { useId, useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { FilterBar } from '@/components/patterns/filter-bar';
import { PageHeader } from '@/components/patterns/page-header';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs } from '@/components/ui/tabs';
import { useAccountStatus } from '@/data/hooks/use-account-status';
import {
    useApplicationCounts,
    useApplications,
} from '@/data/hooks/use-applications';
import { ApplicationDetailSheet } from '@/features/applications/application-detail-sheet';
import { ApplicationsTable } from '@/features/applications/applications-table';
import { useT } from '@/i18n/i18n-provider';
import { AppLayout } from '@/layouts/app-layout';
import { useFormat } from '@/lib/format';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import type {
    ApplicationFilters,
    ApplicationItem,
    JobLanguage,
    Paginated,
} from '@/types/contracts';
import type { SharedProps } from '@/types/shared';

type TabStatus = NonNullable<ApplicationFilters['status']>;
type ApplicationsPageProps = SharedProps & {
    applications: Paginated<ApplicationItem>;
};

const TABS: TabStatus[] = ['all', 'in_progress', 'sent', 'attention'];
const LANGUAGES: (JobLanguage | 'all')[] = ['en', 'pt', 'all'];
const SEARCH_DEBOUNCE_MS = 300;

// Mount-time seed only, mirroring the query string the backend used to build
// the `applications` prop (status/language/q, same names as
// ApplicationFiltersRequest). Not kept in sync afterwards.
const parseInitialFilters = (search: string) => {
    const params = new URLSearchParams(search);
    const status = params.get('status');
    const language = params.get('language');

    return {
        tab: (status && TABS.includes(status as TabStatus)
            ? status
            : 'all') as TabStatus,
        language: (language &&
        LANGUAGES.includes(language as JobLanguage | 'all')
            ? language
            : 'all') as JobLanguage | 'all',
        search: params.get('q') ?? '',
    };
};

const ApplicationsSkeleton = () => {
    const { t } = useT();

    return (
        <div
            role="status"
            aria-busy="true"
            aria-label={t('common.loading')}
            className="flex flex-col gap-3"
        >
            {[0, 1, 2, 3, 4].map((index) => (
                <Skeleton key={index} shape="block" className="h-16" />
            ))}
        </div>
    );
};

export default function Applications() {
    const { t } = useT();
    const format = useFormat();
    const [initialFilters] = useState(() =>
        parseInitialFilters(window.location.search),
    );
    const [tab, setTab] = useState<TabStatus>(initialFilters.tab);
    const [language, setLanguage] = useState<JobLanguage | 'all'>(
        initialFilters.language,
    );
    const [search, setSearch] = useState(initialFilters.search);
    const q = useDebouncedValue(search, SEARCH_DEBOUNCE_MS).trim();
    const languageId = useId();
    const [opened, setOpened] = useState<ApplicationItem | null>(null);
    const account = useAccountStatus().data;
    const counts = useApplicationCounts().data;
    const { applications: applicationsProp } =
        usePage<ApplicationsPageProps>().props;
    // The prop matches the query string this page was loaded with; once the
    // user changes any filter away from that snapshot, stop honouring it.
    const isInitialFilters =
        tab === initialFilters.tab &&
        language === initialFilters.language &&
        q === initialFilters.search.trim();
    const applications = useApplications(
        {
            status: tab,
            language,
            q: q === '' ? undefined : q,
        },
        isInitialFilters ? applicationsProp : undefined,
    );
    const rows = applications.data?.pages.flatMap((page) => page.data) ?? [];

    const languageOptions = [
        {
            value: 'all' as const,
            label: t('applications.filters.language_all'),
        },
        ...(account?.profiles.activeLanguages ?? []).map((value) => ({
            value,
            label: t(`jobs.language.${value}`),
        })),
    ];

    const body = applications.isError ? (
        <DataCard
            title={t('applications.list.title')}
            state="error"
            onRetry={() => void applications.refetch()}
        />
    ) : applications.isPending ? (
        <ApplicationsSkeleton />
    ) : rows.length === 0 ? (
        <DataCard title={t('applications.list.title')} state="empty" />
    ) : (
        <ApplicationsTable
            items={rows}
            onOpen={setOpened}
            hasMore={applications.hasNextPage}
            loadingMore={applications.isFetchingNextPage}
            onLoadMore={() => void applications.fetchNextPage()}
        />
    );

    return (
        <AppLayout>
            <Head title={t('applications.title')} />
            <PageHeader title={t('applications.title')} />
            <div className="flex min-w-0 flex-col gap-gap">
                <FilterBar wrap>
                    <Input
                        type="search"
                        value={search}
                        placeholder={t('applications.filters.search')}
                        aria-label={t('applications.filters.search')}
                        onChange={(event) => setSearch(event.target.value)}
                        className="w-full md:w-64"
                    />
                    <div className="w-full md:w-48">
                        <label htmlFor={languageId} className="sr-only">
                            {t('applications.filters.language')}
                        </label>
                        <Select
                            id={languageId}
                            value={language}
                            onChange={setLanguage}
                            options={languageOptions}
                        />
                    </div>
                </FilterBar>
                <Tabs
                    ariaLabel={t('applications.tabs')}
                    selectedIndex={TABS.indexOf(tab)}
                    onChange={(index) => setTab(TABS[index])}
                    tabs={TABS.map((value) => ({
                        label: t(`applications.tab.${value}`),
                        adornment: counts ? (
                            <span className="text-chip opacity-70">
                                {format.number(counts[value])}
                            </span>
                        ) : null,
                        content: body,
                    }))}
                />
            </div>
            <ApplicationDetailSheet
                item={opened}
                onClose={() => setOpened(null)}
            />
        </AppLayout>
    );
}
