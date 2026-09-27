import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PlanOffer } from '@/types/contracts';
import type { PlanKey } from '@/types/plans';

export type PlanCta = 'current' | 'upgrade' | 'switch';

/** Feature copy per plan; the offers contract carries no feature list. */
const FEATURES: Record<PlanKey, string[]> = {
    free: ['gmail', 'matching', 'random'],
    starter: ['gmail', 'matching', 'choose_jobs'],
    pro: ['gmail', 'matching', 'review_emails'],
};

export const PlanCard = ({
    plan,
    cta,
    onSelect,
}: {
    plan: PlanOffer;
    cta: PlanCta;
    onSelect: () => void;
}) => {
    const { t, plural } = useT();
    const format = useFormat();

    return (
        <Card
            tone="light"
            as="article"
            className={cn(
                'flex h-full flex-col gap-6',
                plan.highlighted && 'bg-accent-soft outline-2 outline-accent',
            )}
        >
            <div className="flex flex-col gap-2">
                <h2 className="text-card-title-sm md:text-card-title">
                    {t(`plans.${plan.key}.name`)}
                </h2>
                <p className="flex flex-wrap items-baseline gap-x-2">
                    <span className="text-numeral-lg-sm md:text-numeral-lg">
                        {format.currency(plan.price / 100, plan.currency)}
                    </span>
                    <span className="text-body text-muted">
                        {t('plans.per_month')}
                    </span>
                </p>
                <p className="text-body font-semibold">
                    {plural('plans.limit', plan.dailyLimit)}
                </p>
                <p className="text-body text-muted">
                    {t(`plans.mode.${plan.mode}`)}
                </p>
            </div>
            <ul className="flex flex-1 flex-col gap-3 border-t border-hairline pt-5">
                {FEATURES[plan.key].map((feature) => (
                    <li key={feature} className="flex items-start gap-3">
                        <Check
                            aria-hidden="true"
                            size={20}
                            strokeWidth={2}
                            className="mt-0.5 shrink-0 text-accent-deep"
                        />
                        <span className="text-body">
                            {t(`plans.features.${feature}`)}
                        </span>
                    </li>
                ))}
            </ul>
            <Button
                variant={plan.highlighted ? 'primary-ink' : 'secondary-tile'}
                size="lg"
                fullWidth
                disabled={cta === 'current'}
                onClick={onSelect}
            >
                {t(`plans.cta.${cta}`)}
            </Button>
        </Card>
    );
};
