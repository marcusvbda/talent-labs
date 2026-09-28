import { useEffect, useState } from 'react';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { ErrorState } from '@/components/ui/error-state';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Modal } from '@/components/ui/modal';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from '@/components/ui/toast';
import { useQueueReviewed } from '@/data/hooks/use-queue-reviewed';
import { useReviewDrafts } from '@/data/hooks/use-review-drafts';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import type { JobCard, ReviewDraft } from '@/types/contracts';
import { BodyEditor } from './body-editor';

const SUBJECT_MAX = 200;
const BODY_MAX = 5000;

type Outcome = 'queued' | 'skipped';
type Edit = { subject: string; body: string };

const ReviewSkeleton = () => {
    const { t } = useT();

    return (
        <div
            role="status"
            aria-busy="true"
            aria-label={t('common.loading')}
            className="grid gap-6 md:grid-cols-[16rem_1fr]"
        >
            <Skeleton shape="block" className="h-40" />
            <div className="flex flex-col gap-4">
                <Skeleton shape="block" className="h-12" />
                <Skeleton shape="block" className="h-56" />
            </div>
        </div>
    );
};

const JobSummary = ({ job }: { job: JobCard }) => {
    const { t } = useT();

    return (
        <div className="flex flex-col gap-3 rounded-tile bg-tile p-card-sm">
            <div className="flex items-center gap-3">
                <CompanyLogo name={job.company.name} size="sm" />
                <span className="min-w-0 truncate text-label-sm text-ink">
                    {job.company.name}
                </span>
            </div>
            <p className="text-body font-medium text-ink">{job.title}</p>
            <div className="flex flex-wrap gap-1.5">
                <Chip variant="language">
                    {t(`jobs.language.${job.language}`)}
                </Chip>
                <Chip variant="plan">
                    {t(`jobs.seniority.${job.seniority}`)}
                </Chip>
            </div>
            {job.stack.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {job.stack.map((item) => (
                        <Chip key={item} variant="stack">
                            {item}
                        </Chip>
                    ))}
                </div>
            )}
        </div>
    );
};

const Dots = ({
    drafts,
    index,
    outcomes,
}: {
    drafts: ReviewDraft[];
    index: number;
    outcomes: Record<number, Outcome>;
}) => (
    <div aria-hidden="true" className="flex flex-wrap gap-1.5">
        {drafts.map((draft, position) => (
            <span
                key={draft.job.id}
                className={cn(
                    'h-1.5 w-6 rounded-full',
                    position === index
                        ? 'bg-accent-deep'
                        : outcomes[draft.job.id]
                          ? 'bg-ink'
                          : 'bg-tile',
                )}
            />
        ))}
    </div>
);

const ReviewSession = ({
    jobIds,
    onClose,
}: {
    jobIds: number[];
    onClose: (queued: number) => void;
}) => {
    const { t } = useT();
    const load = useReviewDrafts();
    const queue = useQueueReviewed();
    const [index, setIndex] = useState(0);
    const [edits, setEdits] = useState<Record<number, Edit>>({});
    const [outcomes, setOutcomes] = useState<Record<number, Outcome>>({});
    const { mutate: loadDrafts } = load;

    useEffect(() => {
        loadDrafts({ jobIds });
    }, [jobIds, loadDrafts]);

    const drafts = load.data ?? [];
    const draft = drafts[index];
    const finished = load.isSuccess && index >= drafts.length;
    const values = Object.values(outcomes);
    const queuedCount = values.filter((value) => value === 'queued').length;
    const skippedCount = values.filter((value) => value === 'skipped').length;

    const current: Edit | undefined = draft
        ? (edits[draft.job.id] ?? { subject: draft.subject, body: draft.body })
        : undefined;
    const locked = draft ? outcomes[draft.job.id] === 'queued' : false;
    const subjectLength = current?.subject.trim().length ?? 0;
    const bodyLength = current?.body.trim().length ?? 0;
    const subjectInvalid = subjectLength < 1 || subjectLength > SUBJECT_MAX;
    const bodyInvalid = bodyLength < 1 || bodyLength > BODY_MAX;

    const fieldErrors = queue.error?.status === 422 ? queue.error.errors : {};
    const serverSubject = fieldErrors?.subject?.[0];
    const serverBody = fieldErrors?.body?.[0];

    const close = () => {
        if (!queue.isPending) {
            onClose(queuedCount);
        }
    };

    const update = (patch: Partial<Edit>) => {
        if (draft && current) {
            queue.reset();
            setEdits({ ...edits, [draft.job.id]: { ...current, ...patch } });
        }
    };

    const decide = (outcome: Outcome) => {
        if (draft) {
            setOutcomes({ ...outcomes, [draft.job.id]: outcome });
            setIndex(index + 1);
        }
    };

    const approve = () => {
        if (!draft || !current) {
            return;
        }

        queue.mutate(
            {
                jobId: draft.job.id,
                subject: current.subject.trim(),
                body: current.body.trim(),
            },
            {
                onSuccess: (result) => {
                    if (result.queued.length > 0) {
                        decide('queued');

                        return;
                    }

                    toast.error(
                        result.rejected.map((row) => row.reason).join(' · ') ||
                            t('review.failed'),
                    );
                },
                onError: (error) =>
                    error.status === 422
                        ? undefined
                        : toast.error(
                              error.status === 403
                                  ? error.message
                                  : t('review.failed'),
                          ),
            },
        );
    };

    let title = t('review.title');
    let footer = null;
    let content = <ReviewSkeleton />;

    if (load.isError) {
        content = (
            <ErrorState
                title={t('review.load_failed')}
                onRetry={() => loadDrafts({ jobIds })}
            />
        );
    } else if (finished) {
        title = t('review.done.title');
        footer = <Button onClick={close}>{t('review.close')}</Button>;
        content = (
            <p className="text-body text-ink" role="status">
                {t('review.summary', {
                    queued: queuedCount,
                    skipped: skippedCount,
                })}
            </p>
        );
    } else if (draft && current) {
        title = t('review.progress', {
            index: index + 1,
            total: drafts.length,
        });
        footer = (
            <>
                <Button
                    variant="secondary-tile"
                    disabled={queue.isPending || index === 0}
                    onClick={() => setIndex(index - 1)}
                >
                    {t('review.previous')}
                </Button>
                <Button
                    variant="secondary-tile"
                    disabled={queue.isPending}
                    onClick={() =>
                        locked ? setIndex(index + 1) : decide('skipped')
                    }
                >
                    {t('review.skip')}
                </Button>
                <Button
                    loading={queue.isPending}
                    disabled={locked || subjectInvalid || bodyInvalid}
                    onClick={approve}
                >
                    {t('review.approve')}
                </Button>
            </>
        );
        content = (
            <div className="flex flex-col gap-5">
                <Dots drafts={drafts} index={index} outcomes={outcomes} />
                <div className="grid gap-6 md:grid-cols-[16rem_1fr]">
                    <JobSummary job={draft.job} />
                    <div className="flex min-w-0 flex-col gap-4">
                        <div className="flex flex-wrap items-center gap-2 text-chip text-muted">
                            <span>
                                {t('review.to', {
                                    recipient: draft.recipientLabel,
                                })}
                            </span>
                            <Chip variant="language">
                                {t(`jobs.language.${draft.language}`)}
                            </Chip>
                            <Chip variant="stack">
                                {t('review.cv')}: {draft.cvFileName}
                            </Chip>
                        </div>
                        {locked && (
                            <p role="status" className="text-chip text-muted">
                                {t('review.locked')}
                            </p>
                        )}
                        <Field
                            label={t('review.subject.label')}
                            error={
                                subjectInvalid
                                    ? t('review.subject.length')
                                    : serverSubject
                            }
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    value={current.subject}
                                    disabled={locked}
                                    onChange={(event) =>
                                        update({ subject: event.target.value })
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            label={t('review.body.label')}
                            error={
                                bodyInvalid
                                    ? t('review.body.length')
                                    : serverBody
                            }
                        >
                            {(control) => (
                                <BodyEditor
                                    control={control}
                                    value={current.body}
                                    invalid={bodyInvalid}
                                    disabled={locked}
                                    onChange={(body) => update({ body })}
                                />
                            )}
                        </Field>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <Modal
            open
            onClose={close}
            title={title}
            size="xl"
            footer={footer ?? undefined}
        >
            {content}
        </Modal>
    );
};

export function ReviewModal({
    open,
    jobIds,
    onClose,
}: {
    open: boolean;
    jobIds: number[];
    onClose: (queued: number) => void;
}) {
    return open ? <ReviewSession jobIds={jobIds} onClose={onClose} /> : null;
}
