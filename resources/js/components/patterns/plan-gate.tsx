import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PlanKey } from '@/types/plans';

const RADII = {
    tile: 'rounded-tile',
    card: 'rounded-card',
    'card-sm': 'rounded-card-sm',
} as const;

export function PlanGate({
    locked,
    requiredPlans,
    featureKey,
    radius = 'tile',
    children,
}: {
    locked: boolean;
    requiredPlans: PlanKey[];
    featureKey: string;
    radius?: keyof typeof RADII;
    children: ReactNode;
}) {
    const { t } = useT();
    const format = useFormat();

    if (!locked || requiredPlans.length === 0) {
        return <>{children}</>;
    }

    const title = t('plan_gate.title', {
        plans: format.list(requiredPlans.map((p) => t(`plans.${p}.name`))),
    });

    // Both layers share one grid cell, so the overlay can grow the card
    // instead of overflowing it.
    return (
        <div className="grid">
            <div
                inert
                aria-hidden="true"
                className="col-start-1 row-start-1 min-w-0"
            >
                {children}
            </div>
            <div
                role="group"
                aria-label={title}
                className={cn(
                    'col-start-1 row-start-1 flex min-w-0 flex-col items-center justify-center gap-2 bg-scrim p-4 text-center backdrop-blur-xs',
                    RADII[radius],
                )}
            >
                <span className="flex size-control-sm shrink-0 items-center justify-center rounded-full bg-ink text-white">
                    <Lock aria-hidden="true" size={20} strokeWidth={1.8} />
                </span>
                <p className="text-label font-medium">{title}</p>
                <p className="text-label-sm text-muted">{t(featureKey)}</p>
                <div className="flex flex-wrap items-center justify-center gap-2">
                    <Button variant="primary-ink" size="sm" href="#">
                        {t('plan_gate.upgrade', {
                            plan: t(`plans.${requiredPlans[0]}.name`),
                        })}
                    </Button>
                    <Button variant="ghost" size="sm" href="#">
                        {t('plan_gate.see_plans')}
                    </Button>
                </div>
            </div>
        </div>
    );
}
