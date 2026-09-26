import { ArrowUpRight, Send, Shuffle } from 'lucide-react';
import { DataCard } from '@/components/patterns/data-card';
import { JobRow } from '@/components/patterns/job-row';
import { PlanGate } from '@/components/patterns/plan-gate';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import { useT } from '@/i18n/i18n-provider';
import type { PlanKey } from '@/types/plans';
import type { Locale } from '@/types/shared';

export type MatchJob = {
    id: string;
    company: string;
    title: string;
    meta: string;
    stack: string[];
    language: Locale;
};

const SELECTION_PLANS: PlanKey[] = ['starter', 'pro'];

export function NewMatchesCard({
    jobs,
    selected,
    onToggle,
    onClear,
    matches,
    left,
    plan,
}: {
    jobs: MatchJob[];
    selected: string[];
    onToggle: (id: string, checked: boolean) => void;
    onClear: () => void;
    matches: number;
    left: number;
    plan: PlanKey;
}) {
    const { t, plural } = useT();
    const locked = !SELECTION_PLANS.includes(plan);
    const count = locked ? 0 : selected.length;

    return (
        <DataCard
            title={t('dashboard.matches.title')}
            subtitle={t('dashboard.matches.subtitle', { count: matches })}
            actions={
                <IconButton
                    icon={ArrowUpRight}
                    label={t('dashboard.matches.open')}
                />
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
                    {jobs.map((job) => (
                        <JobRow
                            key={job.id}
                            selected={!locked && selected.includes(job.id)}
                            onSelectedChange={(checked) =>
                                onToggle(job.id, checked)
                            }
                            company={job.company}
                            title={job.title}
                            meta={job.meta}
                            stack={job.stack}
                            language={job.language}
                        />
                    ))}
                </div>
            </PlanGate>
            <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-3 pt-6">
                {!locked && (
                    <p className="text-body text-muted">
                        <b className="font-medium text-ink">
                            {t('dashboard.matches.selected', { count })}
                        </b>{' '}
                        · {t('dashboard.matches.sends_left', { count: left })}
                    </p>
                )}
                <div
                    className={
                        locked
                            ? 'flex w-full flex-wrap items-center justify-end gap-3 max-md:justify-stretch'
                            : 'flex flex-wrap items-center gap-3 max-md:w-full'
                    }
                >
                    {!locked && (
                        <>
                            <Button
                                variant="secondary-tile"
                                size="lg"
                                onClick={onClear}
                                disabled={count === 0}
                            >
                                {t('dashboard.matches.clear')}
                            </Button>
                            <Button
                                size="lg"
                                iconLeft={Send}
                                className="max-md:flex-1"
                                disabled={count === 0}
                            >
                                {plural('dashboard.matches.send', count)}
                            </Button>
                        </>
                    )}
                    <Button
                        variant={locked ? 'primary-ink' : 'secondary-tile'}
                        size="lg"
                        iconLeft={Shuffle}
                        className="max-md:flex-1"
                    >
                        {t('dashboard.matches.send_random')}
                    </Button>
                </div>
            </div>
        </DataCard>
    );
}
