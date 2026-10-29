import { Check, CircleDashed, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Chip } from '@/components/ui/chip';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type { JobMatch, MatchCriterion } from '@/types/contracts';

const iconFor = (criterion: MatchCriterion): LucideIcon => {
    switch (criterion.result) {
        case 'met':
        case 'remote':
        case 'location':
            return Check;
        case 'partial':
        case 'not_stated':
            return CircleDashed;
        case 'missed':
            return X;
    }
};

export function MatchBreakdown({ match }: { match: JobMatch }) {
    const { t, plural } = useT();
    const format = useFormat();

    const textFor = (criterion: MatchCriterion): string => {
        switch (criterion.key) {
            case 'stack':
                return plural(
                    'jobs.match.stack',
                    criterion.matched.length + criterion.missing.length,
                    {
                        matched: criterion.matched.length,
                        total:
                            criterion.matched.length + criterion.missing.length,
                    },
                );
            case 'seniority':
                return t(`jobs.match.seniority.${criterion.result}`);
            case 'location':
                return t(`jobs.match.location.${criterion.result}`);
            case 'title':
                return t(`jobs.match.title_result.${criterion.result}`);
        }
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center justify-between gap-2">
                <p className="text-label-sm text-muted">
                    {t('jobs.match.title')}
                </p>
                <Chip variant="plan">
                    {t('jobs.match.score', { score: match.score })}
                </Chip>
            </div>
            <ul className="flex flex-col gap-2">
                {match.criteria.map((criterion) => {
                    const Icon = iconFor(criterion);

                    return (
                        <li key={criterion.key} className="flex gap-2">
                            <Icon
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0 text-muted"
                            />
                            <div className="flex flex-col gap-0.5">
                                <span className="text-body text-ink">
                                    {textFor(criterion)}
                                </span>
                                {criterion.key === 'stack' &&
                                    criterion.missing.length > 0 && (
                                        <span className="text-label-sm text-muted">
                                            {t('jobs.match.stack_missing', {
                                                list: format.list(
                                                    criterion.missing,
                                                    'unit',
                                                ),
                                            })}
                                        </span>
                                    )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
