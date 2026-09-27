import { router } from '@inertiajs/react';
import {
    Briefcase,
    FileSliders,
    LanguagesIcon,
    PauseCircle,
    PlayCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Input } from '@/components/ui/input';
import { Modal } from '@/components/ui/modal';
import { useLiveSending } from '@/data/hooks/use-live-sending';
import { useJobs } from '@/data/hooks/use-jobs';
import {
    usePauseSending,
    useResumeSending,
} from '@/data/hooks/use-pause-sending';
import { useT } from '@/i18n/i18n-provider';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { useMainNav } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { account, jobs, preferences, profiles } from '@/routes';

const JOBS_SEARCH_DEBOUNCE_MS = 250;
const MAX_JOB_RESULTS = 5;

type PaletteRow = {
    id: string;
    group: 'pages' | 'jobs' | 'actions';
    label: string;
    sublabel?: string;
    icon?: LucideIcon;
    onSelect: () => void;
};

export function CommandPalette({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const { t } = useT();
    const mainNav = useMainNav();
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const debouncedQuery = useDebouncedValue(query, JOBS_SEARCH_DEBOUNCE_MS);
    const jobsQuery = useJobs({ q: debouncedQuery });
    const liveSending = useLiveSending();
    const pauseSending = usePauseSending();
    const resumeSending = useResumeSending();
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            setQuery('');
            setActiveIndex(0);
        }
    }, [open]);

    useEffect(() => {
        setActiveIndex(0);
    }, [query]);

    const needle = query.trim().toLowerCase();

    const pageRows = useMemo<PaletteRow[]>(() => {
        const items = [
            ...mainNav,
            { key: 'account', label: t('nav.account'), href: account().url },
        ];

        return items
            .filter((item) => item.label.toLowerCase().includes(needle))
            .map((item) => ({
                id: `page-${item.key}`,
                group: 'pages' as const,
                label: item.label,
                onSelect: () => {
                    onClose();
                    router.visit(item.href as string);
                },
            }));
    }, [mainNav, needle, onClose, t]);

    const jobRows = useMemo<PaletteRow[]>(() => {
        const results = jobsQuery.data?.pages[0]?.data ?? [];

        return results.slice(0, MAX_JOB_RESULTS).map((job) => ({
            id: `job-${job.id}`,
            group: 'jobs' as const,
            label: job.title,
            sublabel: job.company.name,
            icon: Briefcase,
            onSelect: () => {
                onClose();
                router.visit(jobs({ query: { q: debouncedQuery } }).url);
            },
        }));
    }, [jobsQuery.data, onClose, debouncedQuery]);

    const actionRows = useMemo<PaletteRow[]>(() => {
        const isPaused = liveSending.data?.state === 'paused';
        const rows: PaletteRow[] = [
            isPaused
                ? {
                      id: 'action-resume-sending',
                      group: 'actions' as const,
                      label: t('palette.action.resume_sending'),
                      icon: PlayCircle,
                      onSelect: () => {
                          onClose();
                          resumeSending.mutate();
                      },
                  }
                : {
                      id: 'action-pause-sending',
                      group: 'actions' as const,
                      label: t('palette.action.pause_sending'),
                      icon: PauseCircle,
                      onSelect: () => {
                          onClose();
                          pauseSending.mutate();
                      },
                  },
            {
                id: 'action-add-language',
                group: 'actions' as const,
                label: t('palette.action.add_language'),
                icon: LanguagesIcon,
                onSelect: () => {
                    onClose();
                    router.visit(profiles().url);
                },
            },
            {
                id: 'action-edit-preferences',
                group: 'actions' as const,
                label: t('palette.action.edit_preferences'),
                icon: FileSliders,
                onSelect: () => {
                    onClose();
                    router.visit(preferences().url);
                },
            },
        ];

        return rows.filter((row) => row.label.toLowerCase().includes(needle));
    }, [liveSending.data, needle, onClose, pauseSending, resumeSending, t]);

    const rows = useMemo(
        () => [...pageRows, ...jobRows, ...actionRows],
        [pageRows, jobRows, actionRows],
    );

    const activeRow = rows[activeIndex] ?? null;

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((index) =>
                rows.length === 0 ? 0 : (index + 1) % rows.length,
            );
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) =>
                rows.length === 0 ? 0 : (index - 1 + rows.length) % rows.length,
            );
        } else if (event.key === 'Enter') {
            event.preventDefault();
            activeRow?.onSelect();
        }
    };

    const groups: { key: PaletteRow['group']; title: string }[] = [
        { key: 'pages', title: t('palette.group.pages') },
        { key: 'jobs', title: t('palette.group.jobs') },
        { key: 'actions', title: t('palette.group.actions') },
    ];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={t('palette.title')}
            size="lg"
        >
            <div className="flex flex-col gap-4">
                <Input
                    ref={inputRef}
                    autoFocus
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={t('palette.placeholder')}
                    aria-label={t('palette.placeholder')}
                />
                <div role="listbox" className="flex flex-col gap-4">
                    {rows.length === 0 ? (
                        <p className="text-body text-muted">
                            {t('palette.empty')}
                        </p>
                    ) : (
                        groups.map((group) => {
                            const groupRows = rows.filter(
                                (row) => row.group === group.key,
                            );

                            if (groupRows.length === 0) {
                                return null;
                            }

                            return (
                                <div
                                    key={group.key}
                                    className="flex flex-col gap-1.5"
                                >
                                    <h3 className="text-label-sm text-muted">
                                        {group.title}
                                    </h3>
                                    <ul className="flex flex-col gap-1">
                                        {groupRows.map((row) => {
                                            const index = rows.indexOf(row);
                                            const active =
                                                index === activeIndex;
                                            const Icon = row.icon;

                                            return (
                                                <li key={row.id}>
                                                    <button
                                                        type="button"
                                                        role="option"
                                                        aria-selected={active}
                                                        onMouseEnter={() =>
                                                            setActiveIndex(
                                                                index,
                                                            )
                                                        }
                                                        onClick={row.onSelect}
                                                        className={cn(
                                                            'flex w-full items-center gap-3 rounded-tile px-4 py-2.5 text-left text-body transition-colors',
                                                            active
                                                                ? 'bg-ink text-white'
                                                                : 'text-ink hover:bg-tile',
                                                        )}
                                                    >
                                                        {Icon ? (
                                                            <Icon
                                                                aria-hidden="true"
                                                                size={18}
                                                                strokeWidth={
                                                                    1.8
                                                                }
                                                                className="shrink-0"
                                                            />
                                                        ) : null}
                                                        <span className="min-w-0 flex-1 truncate">
                                                            {row.label}
                                                        </span>
                                                        {row.sublabel ? (
                                                            <span
                                                                className={cn(
                                                                    'shrink-0 truncate text-label-sm',
                                                                    active
                                                                        ? 'text-white/70'
                                                                        : 'text-muted',
                                                                )}
                                                            >
                                                                {row.sublabel}
                                                            </span>
                                                        ) : null}
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </Modal>
    );
}
