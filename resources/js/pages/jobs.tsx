import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { PageHeader } from '@/components/patterns/page-header';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccountStatus } from '@/data/hooks/use-account-status';
import { useJobs } from '@/data/hooks/use-jobs';
import { JobDetailSheet } from '@/features/jobs/job-detail-sheet';
import { JobFilterBar } from '@/features/jobs/job-filters';
import { JobsList } from '@/features/jobs/jobs-list';
import { ReviewModal } from '@/features/review/review-modal';
import { ConfirmSendModal } from '@/features/send/confirm-send-modal';
import { SendBar } from '@/features/send/send-bar';
import { useSelection } from '@/features/send/use-selection';
import { useT } from '@/i18n/i18n-provider';
import { AppLayout } from '@/layouts/app-layout';
import { useFormat } from '@/lib/format';
import { useQueryFilters } from '@/lib/use-query-filters';
import { preferences, profiles } from '@/routes';
import type { JobCard, JobsPage } from '@/types/contracts';
import type { SharedProps } from '@/types/shared';

type JobsPageProps = SharedProps & { jobs: JobsPage };

const JobsSkeleton = () => {
    const { t } = useT();

    return (
        <div
            role="status"
            aria-busy="true"
            aria-label={t('common.loading')}
            className="flex flex-col gap-3"
        >
            {[0, 1, 2, 3, 4].map((index) => (
                <Skeleton key={index} shape="block" className="h-20" />
            ))}
        </div>
    );
};

export default function Jobs() {
    const { t, plural } = useT();
    const format = useFormat();
    const [filters, setFilters] = useQueryFilters();
    const { jobs: jobsProp } = usePage<JobsPageProps>().props;
    // The prop is a snapshot for the query string this page was loaded with;
    // only honour it while the filters still match that snapshot.
    const [loadedFilters] = useState(() => JSON.stringify(filters));
    const isInitialFilters = JSON.stringify(filters) === loadedFilters;
    const jobs = useJobs(filters, isInitialFilters ? jobsProp : undefined);
    const status = useAccountStatus();
    const account = status.data;
    const selection = useSelection(account?.quota.remaining ?? 0);
    const [opened, setOpened] = useState<JobCard | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [reviewIds, setReviewIds] = useState<number[] | null>(null);

    const summary = jobs.data?.pages[0]?.summary;
    const rows = jobs.data?.pages.flatMap((page) => page.data) ?? [];
    const mode = account?.plan.mode;
    const selectable = mode !== undefined;
    const loading = jobs.isPending || !account;

    return (
        <AppLayout>
            <Head title={t('jobs.title')} />
            <PageHeader
                title={t('jobs.title')}
                summary={
                    summary
                        ? plural('jobs.summary', summary.total, {
                              total: format.number(summary.total),
                              today: format.number(summary.collectedToday),
                          })
                        : undefined
                }
                actions={
                    <Button variant="secondary-tile" href={preferences().url}>
                        {t('jobs.edit_preferences')}
                    </Button>
                }
            />
            <div className="flex min-w-0 flex-col gap-gap pb-24 md:pb-0">
                <JobFilterBar
                    filters={filters}
                    onChange={setFilters}
                    activeLanguages={account?.profiles.activeLanguages ?? []}
                />
                {summary?.lockedByLanguage.map((row) => (
                    <div
                        key={row.language}
                        role="note"
                        className="flex flex-wrap items-center justify-between gap-3 rounded-tile bg-accent-soft p-card-sm text-body text-accent-deep"
                    >
                        {plural('jobs.locked', row.count, {
                            language: t(`jobs.language_lower.${row.language}`),
                        })}
                        <Button
                            size="sm"
                            variant="secondary-tile"
                            href={profiles().url}
                        >
                            {t('jobs.locked_action', {
                                language: t(`jobs.language.${row.language}`),
                            })}
                        </Button>
                    </div>
                ))}
                {jobs.isError ? (
                    <DataCard
                        title={t('jobs.list.title')}
                        state="error"
                        onRetry={() => void jobs.refetch()}
                    />
                ) : loading ? (
                    <JobsSkeleton />
                ) : rows.length === 0 ? (
                    <DataCard title={t('jobs.list.title')} state="empty" />
                ) : (
                    <JobsList
                        jobs={rows}
                        selectable={selectable}
                        isSelected={selection.isSelected}
                        onToggle={selection.toggle}
                        onOpen={setOpened}
                        hasMore={jobs.hasNextPage}
                        loadingMore={jobs.isFetchingNextPage}
                        onLoadMore={() => void jobs.fetchNextPage()}
                    />
                )}
                {mode && account && (
                    <SendBar
                        mode={mode}
                        count={selection.selected.length}
                        left={account.quota.remaining}
                        overQuota={selection.overQuota}
                        onClear={selection.clear}
                        onSend={() => setConfirming(true)}
                        onReview={() =>
                            setReviewIds(
                                selection.selected.map((job) => job.id),
                            )
                        }
                    />
                )}
            </div>
            <JobDetailSheet
                job={opened}
                onClose={() => setOpened(null)}
                selectable={selectable}
                selected={opened ? selection.isSelected(opened.id) : false}
                onToggle={selection.toggle}
            />
            <ConfirmSendModal
                open={confirming}
                onClose={() => setConfirming(false)}
                selected={selection.selected}
                gmail={account?.gmail.accountEmail ?? ''}
                onQueued={() => {
                    selection.clear();
                    setConfirming(false);
                }}
            />
            <ReviewModal
                open={reviewIds !== null}
                jobIds={reviewIds ?? []}
                onClose={(queued) => {
                    if (queued > 0) {
                        selection.clear();
                    }

                    setReviewIds(null);
                }}
            />
        </AppLayout>
    );
}
