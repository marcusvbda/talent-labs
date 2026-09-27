import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/components/patterns/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ErrorState } from '@/components/ui/error-state';
import { Modal } from '@/components/ui/modal';
import { Skeleton } from '@/components/ui/skeleton';
import { usePlans } from '@/data/hooks/use-plans';
import { PlanCard } from '@/features/plans/plan-card';
import type { PlanCta } from '@/features/plans/plan-card';
import { RegionMenu } from '@/features/plans/region-menu';
import { useT } from '@/i18n/i18n-provider';
import { AppGrid, AppLayout } from '@/layouts/app-layout';
import type { RegionKey } from '@/types/contracts';

const CELL = 'min-w-0 lg:col-span-4';

const PlansSkeleton = () => {
    const { t } = useT();

    return (
        <AppGrid>
            {[0, 1, 2].map((index) => (
                <div
                    key={index}
                    role="status"
                    aria-busy="true"
                    aria-label={t('common.loading')}
                    className={CELL}
                >
                    <Card tone="light" className="flex flex-col gap-4">
                        <Skeleton shape="line" className="h-6 w-1/3" />
                        <Skeleton shape="line" className="h-10 w-1/2" />
                        <Skeleton shape="line" className="w-2/3" />
                        <Skeleton shape="block" />
                        <Skeleton shape="line" />
                        <Skeleton shape="line" className="w-2/3" />
                    </Card>
                </div>
            ))}
        </AppGrid>
    );
};

export default function Plans() {
    const { t } = useT();
    const [region, setRegion] = useState<RegionKey | undefined>();
    const [betaOpen, setBetaOpen] = useState(false);
    const { data, isError, refetch } = usePlans(region);

    const currentIndex = data?.plans.findIndex(
        (plan) => plan.key === data.current,
    );

    return (
        <AppLayout>
            <Head title={t('plans.title')} />
            <PageHeader
                eyebrow={t('plans.eyebrow')}
                title={t('plans.title')}
                summary={t('plans.summary')}
                actions={
                    data ? (
                        <RegionMenu
                            region={data.region}
                            regions={data.regions.map((row) => row.key)}
                            onChange={setRegion}
                        />
                    ) : undefined
                }
            />
            {isError ? (
                <Card tone="light">
                    <ErrorState onRetry={() => void refetch()} />
                </Card>
            ) : !data ? (
                <PlansSkeleton />
            ) : (
                <AppGrid>
                    {data.plans.map((plan, index) => {
                        const cta: PlanCta =
                            plan.key === data.current
                                ? 'current'
                                : index > (currentIndex ?? 0)
                                  ? 'upgrade'
                                  : 'switch';

                        return (
                            <div key={plan.key} className={CELL}>
                                <PlanCard
                                    plan={plan}
                                    cta={cta}
                                    onSelect={() =>
                                        // Billing ships later; until then plans are assigned manually.
                                        !data.billingAvailable &&
                                        setBetaOpen(true)
                                    }
                                />
                            </div>
                        );
                    })}
                </AppGrid>
            )}
            <Modal
                open={betaOpen}
                onClose={() => setBetaOpen(false)}
                size="sm"
                title={t('plans.closed_beta.title')}
                description={t('plans.closed_beta.body')}
                footer={
                    <Button size="md" onClick={() => setBetaOpen(false)}>
                        {t('plans.closed_beta.confirm')}
                    </Button>
                }
            />
        </AppLayout>
    );
}
