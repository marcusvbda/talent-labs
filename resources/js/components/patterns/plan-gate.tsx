import type { ReactNode } from 'react';
import { LockOverlay } from '@/components/patterns/lock-overlay';
import type { LockOverlayRadius } from '@/components/patterns/lock-overlay';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import { plans } from '@/routes';
import type { PlanKey } from '@/types/plans';

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
    radius?: LockOverlayRadius;
    children: ReactNode;
}) {
    const { t } = useT();
    const format = useFormat();

    if (!locked || requiredPlans.length === 0) {
        return <>{children}</>;
    }

    return (
        <LockOverlay
            locked
            radius={radius}
            title={t('plan_gate.title', {
                plans: format.list(
                    requiredPlans.map((p) => t(`plans.${p}.name`)),
                ),
            })}
            description={t(featureKey)}
            actions={
                <>
                    <Button variant="primary-ink" size="sm" href={plans().url}>
                        {t('plan_gate.upgrade', {
                            plan: t(`plans.${requiredPlans[0]}.name`),
                        })}
                    </Button>
                    <Button variant="ghost" size="sm" href={plans().url}>
                        {t('plan_gate.see_plans')}
                    </Button>
                </>
            }
        >
            {children}
        </LockOverlay>
    );
}
