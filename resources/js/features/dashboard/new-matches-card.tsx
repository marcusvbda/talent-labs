import { ArrowUpRight, Send, Shuffle } from 'lucide-react';
import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { JobRow } from '@/components/patterns/job-row';
import { PlanGate } from '@/components/patterns/plan-gate';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import { ReviewModal } from '@/features/review/review-modal';
import { ConfirmSendModal } from '@/features/send/confirm-send-modal';
import { RandomSendModal } from '@/features/send/random-send-modal';
import { useSelection } from '@/features/send/use-selection';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type { DashboardData, SendMode } from '@/types/contracts';
import type { PlanKey } from '@/types/plans';

const SELECTION_PLANS: PlanKey[] = ['starter', 'pro'];
const MAX_SHOWN = 4;

export function NewMatchesCard({
    matches,
    mode,
    accountEmail,
    remaining,
}: {
    matches: DashboardData['matches'];
    mode: SendMode;
    accountEmail: string;
    remaining: number;
}) {
    const { t, plural } = useT();
    const format = useFormat();
    const selection = useSelection(remaining);
    const [confirming, setConfirming] = useState(false);
    const [randomOpen, setRandomOpen] = useState(false);
    const [reviewIds, setReviewIds] = useState<number[] | null>(null);
    const locked = mode === 'random';
    const canChoose = !locked;
    const items = matches.items.slice(0, MAX_SHOWN);
    const count = canChoose ? selection.selected.length : 0;
    const randomCount = Math.min(remaining, matches.total);

    return (
        <DataCard
            title={t('dashboard.matches.title')}
            subtitle={t('dashboard.matches.subtitle', {
                count: matches.total,
            })}
            state={matches.total === 0 ? 'empty' : 'ready'}
            actions={
                canChoose ? (
                    <IconButton
                        icon={ArrowUpRight}
                        label={t('dashboard.matches.open')}
                    />
                ) : undefined
            }
            className="flex flex-col"
        >
            <PlanGate
                locked={locked}
                requiredPlans={SELECTION_PLANS}
                featureKey="dashboard.matches.gate_benefit"
                radius="card-sm"
            >
                <div className="flex flex-col gap-3">
                    {items.map((job) => (
                        <JobRow
                            key={job.id}
                            selected={!locked && selection.isSelected(job.id)}
                            onSelectedChange={() => selection.toggle(job)}
                            onOpen={locked ? () => {} : undefined}
                            selectable={!locked}
                            company={job.company.name}
                            title={job.title}
                            meta={`${job.company.name} · ${job.location ?? t('jobs.remote')} · ${format.relativeTime(job.firstSeenAt)}`}
                            stack={job.stack}
                            language={job.language}
                        />
                    ))}
                </div>
            </PlanGate>
            <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-3 pt-6">
                {canChoose && (
                    <p className="text-body text-muted">
                        <b className="font-medium text-ink">
                            {t('dashboard.matches.selected', { count })}
                        </b>{' '}
                        ·{' '}
                        {t('dashboard.matches.sends_left', {
                            count: remaining,
                        })}
                    </p>
                )}
                <div
                    className={
                        canChoose
                            ? 'flex flex-wrap items-center gap-3 max-md:w-full'
                            : 'flex w-full flex-wrap items-center justify-end gap-3 max-md:justify-stretch'
                    }
                >
                    {canChoose && (
                        <>
                            <Button
                                variant="secondary-tile"
                                size="lg"
                                onClick={selection.clear}
                                disabled={count === 0}
                            >
                                {t('dashboard.matches.clear')}
                            </Button>
                            <Button
                                size="lg"
                                iconLeft={Send}
                                className="max-md:flex-1"
                                disabled={count === 0}
                                onClick={() =>
                                    mode === 'review'
                                        ? setReviewIds(
                                              selection.selected.map(
                                                  (job) => job.id,
                                              ),
                                          )
                                        : setConfirming(true)
                                }
                            >
                                {plural(
                                    mode === 'review'
                                        ? 'send.review'
                                        : 'dashboard.matches.send',
                                    count,
                                )}
                            </Button>
                        </>
                    )}
                    <Button
                        variant={canChoose ? 'secondary-tile' : 'primary-ink'}
                        size="lg"
                        iconLeft={Shuffle}
                        className="max-md:flex-1"
                        disabled={randomCount === 0}
                        onClick={() => setRandomOpen(true)}
                    >
                        {t('dashboard.matches.send_random')}
                    </Button>
                </div>
            </div>
            <RandomSendModal
                open={randomOpen}
                onClose={() => setRandomOpen(false)}
                count={randomCount}
                gmail={accountEmail}
            />
            <ConfirmSendModal
                open={confirming}
                onClose={() => setConfirming(false)}
                selected={selection.selected}
                gmail={accountEmail}
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
        </DataCard>
    );
}
