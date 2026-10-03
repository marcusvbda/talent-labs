import { Check } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Pill } from '@/components/ui/pill';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PlanKey, RegionKey } from '@/types/contracts';
import { BetaCta } from './beta-cta';
import type { LandingPlan } from './types';

const FEATURES: Record<PlanKey, string[]> = {
    free: ['auto'],
    starter: ['auto', 'select'],
    pro: ['auto', 'select', 'review'],
};

export const LandingPlanCard = ({
    plan,
    region,
    betaClosed,
}: {
    plan: LandingPlan;
    region: RegionKey;
    betaClosed: boolean;
}) => {
    const { t } = useT();
    const format = useFormat();
    const { amount, currency } = plan.prices[region];
    const description = plan.mode === 'random' ? 'auto' : plan.mode;

    return (
        <Card
            tone="light"
            as="article"
            className={cn(
                'flex h-full flex-col gap-6 transition-transform motion-safe:hover:-translate-y-1',
                plan.highlighted &&
                    'bg-accent-soft outline-2 outline-accent max-lg:order-first',
            )}
        >
            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-card-title-sm md:text-card-title">
                        {t(`plans.${plan.key}.name`)}
                    </h3>
                    {plan.highlighted && (
                        <Pill tone="white-on-accent">
                            {t('landing.plans.recommended')}
                        </Pill>
                    )}
                </div>
                <p className="flex flex-wrap items-baseline gap-x-2">
                    <span className="text-numeral-lg-sm md:text-numeral-lg">
                        {format.currency(amount / 100, currency)}
                    </span>
                    <span className="text-body text-muted">
                        {t('plans.per_month')}
                    </span>
                </p>
                <p className="text-body font-semibold">
                    {t('landing.plans.per_day', { n: plan.dailyLimit })}
                </p>
                <p className="text-body text-muted">
                    {t(`landing.plans.desc.${description}`)}
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
                            {t(`landing.plans.feature.${feature}`)}
                        </span>
                    </li>
                ))}
            </ul>
            <BetaCta
                betaClosed={betaClosed}
                variant={plan.highlighted ? 'primary-ink' : 'secondary-tile'}
                size="lg"
                fullWidth
            />
            {betaClosed && (
                <p className="text-center text-label-sm text-muted">
                    {t('landing.plans.beta_note')}
                </p>
            )}
        </Card>
    );
};
