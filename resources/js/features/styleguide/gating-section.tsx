import { Briefcase } from 'lucide-react';
import { FilterBar } from '@/components/patterns/filter-bar';
import { LockOverlay } from '@/components/patterns/lock-overlay';
import { PlanGate } from '@/components/patterns/plan-gate';
import { StatTile } from '@/components/patterns/stat-tile';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

export function GatingSection() {
    const { t } = useT();
    const tile = (
        <StatTile
            label={t('styleguide.gating.tile_label')}
            icon={Briefcase}
            tint="orange"
            value="128"
            context={t('styleguide.gating.tile_context')}
        />
    );

    return (
        <StyleguideSection id="gating" title={t('styleguide.gating.title')}>
            <div className="grid gap-gap md:grid-cols-2">
                <div className="flex flex-col gap-3">
                    <h3 className="text-label font-medium">
                        {t('styleguide.gating.locked')}
                    </h3>
                    <PlanGate
                        locked
                        requiredPlans={['starter', 'pro']}
                        featureKey="styleguide.gating.benefit"
                    >
                        {tile}
                    </PlanGate>
                </div>
                <div className="flex flex-col gap-3">
                    <h3 className="text-label font-medium">
                        {t('styleguide.gating.unlocked')}
                    </h3>
                    <PlanGate
                        locked={false}
                        requiredPlans={['starter', 'pro']}
                        featureKey="styleguide.gating.benefit"
                    >
                        {tile}
                    </PlanGate>
                </div>
            </div>
            <div className="flex flex-col gap-3">
                <h3 className="text-label font-medium">
                    {t('styleguide.gating.lock_overlay')}
                </h3>
                <LockOverlay
                    locked
                    title={t('styleguide.gating.lock_title')}
                    description={t('styleguide.gating.lock_description')}
                    actions={
                        <Button variant="primary-ink" size="sm" href="#gating">
                            {t('styleguide.gating.lock_action')}
                        </Button>
                    }
                >
                    {tile}
                </LockOverlay>
            </div>
            <div className="flex flex-col gap-3">
                <h3 className="text-label font-medium">
                    {t('styleguide.gating.filters')}
                </h3>
                <FilterBar>
                    {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
                        <Chip key={n} variant="stack">
                            {t('styleguide.gating.filter', { n })}
                        </Chip>
                    ))}
                </FilterBar>
            </div>
        </StyleguideSection>
    );
}
