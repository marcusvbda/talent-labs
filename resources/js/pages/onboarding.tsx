import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/components/patterns/page-header';
import { Card } from '@/components/ui/card';
import { ErrorState } from '@/components/ui/error-state';
import { Skeleton } from '@/components/ui/skeleton';
import { Stepper } from '@/components/ui/stepper';
import { toast } from '@/components/ui/toast';
import { useAccountStatus } from '@/data/hooks/use-account-status';
import { StepBasics } from '@/features/onboarding/step-basics';
import { StepGmail } from '@/features/onboarding/step-gmail';
import { StepPreferences } from '@/features/onboarding/step-preferences';
import { StepProfile } from '@/features/onboarding/step-profile';
import { useT } from '@/i18n/i18n-provider';
import { AppLayout } from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { AccountStatus } from '@/types/contracts';

const OnboardingSkeleton = () => {
    const { t } = useT();

    return (
        <div
            role="status"
            aria-busy="true"
            aria-label={t('common.loading')}
            className="flex flex-col gap-gap"
        >
            <Skeleton shape="line" className="h-8" />
            <Card tone="light" className="flex flex-col gap-4">
                <Skeleton shape="line" className="h-6 w-1/4" />
                <Skeleton shape="block" />
                <Skeleton shape="line" className="w-2/3" />
            </Card>
        </div>
    );
};

const Wizard = ({ status }: { status: AccountStatus }) => {
    const { t, locale } = useT();
    const steps = status.onboarding.steps;
    const firstOpen = steps.findIndex((step) => !step.done);
    const [index, setIndex] = useState(Math.max(firstOpen, 0));
    const last = steps.length - 1;
    const key = steps[index]?.key;

    const next = () => setIndex((current) => Math.min(current + 1, last));
    const back = () => setIndex((current) => Math.max(current - 1, 0));
    const finish = () => {
        toast.success(t('onboarding.done'));
        router.visit(dashboard());
    };

    return (
        <>
            <Stepper
                ariaLabel={t('onboarding.steps_label')}
                steps={steps.map((step) => ({
                    key: step.key,
                    label: t(`onboarding.steps.${step.key}`),
                    done: step.done,
                }))}
                currentIndex={index}
                onStepChange={setIndex}
            />
            {key === 'basics' && <StepBasics onDone={next} />}
            {key === 'gmail' && (
                <StepGmail gmail={status.gmail} onBack={back} onDone={next} />
            )}
            {key === 'profile' && (
                <StepProfile
                    initialLanguage={locale === 'pt' ? 'pt' : 'en'}
                    onBack={back}
                    onDone={next}
                />
            )}
            {key === 'preferences' && (
                <StepPreferences onBack={back} onFinish={finish} />
            )}
        </>
    );
};

export default function Onboarding() {
    const { t } = useT();
    const { data, isError, refetch } = useAccountStatus();

    return (
        <AppLayout nav={false}>
            <Head title={t('onboarding.title')} />
            <PageHeader
                eyebrow={t('onboarding.eyebrow')}
                title={t('onboarding.title')}
                summary={t('onboarding.intro')}
            />
            {isError ? (
                <Card tone="light">
                    <ErrorState onRetry={() => void refetch()} />
                </Card>
            ) : !data ? (
                <OnboardingSkeleton />
            ) : (
                <Wizard status={data} />
            )}
        </AppLayout>
    );
}
