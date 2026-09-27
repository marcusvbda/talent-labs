import { useEffect, useId, useState } from 'react';
import type { ReactNode } from 'react';
import { FilterBar } from '@/components/patterns/filter-bar';
import { Input } from '@/components/ui/input';
import { MultiSelect } from '@/components/ui/multi-select';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { TagsInput } from '@/components/ui/tags-input';
import { useT } from '@/i18n/i18n-provider';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import type { JobFilters, JobLanguage, Seniority } from '@/types/contracts';

const SEARCH_DEBOUNCE_MS = 300;
const SENIORITIES: Seniority[] = [
    'intern',
    'junior',
    'mid',
    'senior',
    'lead',
    'unknown',
];
const REMOTE_OPTIONS = ['any', 'remote', 'not_remote'] as const;

// The filters share one row, so their labels are screen-reader only.
const Labeled = ({
    label,
    className,
    children,
}: {
    label: string;
    className: string;
    children: (id: string) => ReactNode;
}) => {
    const id = useId();

    return (
        <div className={className}>
            <label htmlFor={id} className="sr-only">
                {label}
            </label>
            {children(id)}
        </div>
    );
};

export function JobFilterBar({
    filters,
    onChange,
    activeLanguages,
}: {
    filters: JobFilters;
    onChange: (patch: Partial<JobFilters>) => void;
    activeLanguages: JobLanguage[];
}) {
    const { t } = useT();
    const query = filters.q ?? '';
    const [draft, setDraft] = useState(query);
    const [seen, setSeen] = useState(query);
    const debounced = useDebouncedValue(draft, SEARCH_DEBOUNCE_MS);

    // Back / forward changes the query from outside: follow it.
    if (query !== seen) {
        setSeen(query);
        setDraft(query);
    }

    useEffect(() => {
        if (debounced !== query) {
            onChange({ q: debounced });
        }
        // Only a settled search term is written to the URL.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    const languageOptions = [
        { value: 'all' as const, label: t('jobs.filters.language_all') },
        ...activeLanguages.map((language) => ({
            value: language,
            label: t(`jobs.language.${language}`),
        })),
    ];

    return (
        <FilterBar wrap>
            <Input
                type="search"
                value={draft}
                placeholder={t('jobs.filters.search')}
                aria-label={t('jobs.filters.search')}
                onChange={(event) => setDraft(event.target.value)}
                className="w-full md:w-64"
            />
            <label className="flex h-control-md items-center gap-3 rounded-tile bg-tile px-6 text-body text-ink">
                <Switch
                    checked={filters.today ?? false}
                    onChange={(today) => onChange({ today })}
                    aria-label={t('jobs.filters.today')}
                />
                {t('jobs.filters.today')}
            </label>
            <Labeled
                label={t('jobs.filters.language')}
                className="w-full md:w-48"
            >
                {(id) => (
                    <Select
                        id={id}
                        value={filters.language ?? 'all'}
                        onChange={(language) => onChange({ language })}
                        options={languageOptions}
                    />
                )}
            </Labeled>
            <Labeled
                label={t('jobs.filters.seniority')}
                className="w-full md:w-64"
            >
                {(id) => (
                    <MultiSelect
                        id={id}
                        value={filters.seniority ?? []}
                        onChange={(seniority) => onChange({ seniority })}
                        options={SENIORITIES.map((value) => ({
                            value,
                            label: t(`jobs.seniority.${value}`),
                        }))}
                        placeholder={t('jobs.filters.seniority')}
                    />
                )}
            </Labeled>
            <Labeled
                label={t('jobs.filters.remote')}
                className="w-full md:w-48"
            >
                {(id) => (
                    <Select
                        id={id}
                        value={filters.remote ?? 'any'}
                        onChange={(remote) => onChange({ remote })}
                        options={REMOTE_OPTIONS.map((value) => ({
                            value,
                            label: t(`jobs.filters.remote.${value}`),
                        }))}
                    />
                )}
            </Labeled>
            <Labeled label={t('jobs.filters.stack')} className="w-full md:w-72">
                {(id) => (
                    <TagsInput
                        id={id}
                        value={filters.stack ?? []}
                        onChange={(stack) => onChange({ stack })}
                        placeholder={t('jobs.filters.stack')}
                    />
                )}
            </Labeled>
        </FilterBar>
    );
}
