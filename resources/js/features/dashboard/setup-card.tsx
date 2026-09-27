import { Circle, CircleCheck } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { onboarding } from '@/routes';
import type { AccountStatus } from '@/types/contracts';

export function SetupCard({
    onboarding: state,
}: {
    onboarding: AccountStatus['onboarding'];
}) {
    const { t } = useT();

    return (
        <Card tone="hero-accent" as="section" className="flex flex-col gap-6">
            <div>
                <h2 className="text-card-title-sm md:text-card-title">
                    {t('dashboard.setup.title')}
                </h2>
            </div>
            <ul className="flex flex-col gap-3 md:flex-row md:flex-wrap md:gap-6">
                {state.steps.map((step) => (
                    <li
                        key={step.key}
                        className="flex items-center gap-2 text-body text-ink"
                    >
                        {step.done ? (
                            <CircleCheck
                                aria-hidden="true"
                                strokeWidth={1.8}
                                className="size-5 shrink-0 text-accent-deep"
                            />
                        ) : (
                            <Circle
                                aria-hidden="true"
                                strokeWidth={1.8}
                                className="size-5 shrink-0 text-ink/50"
                            />
                        )}
                        <span>{t(`onboarding.steps.${step.key}`)}</span>
                    </li>
                ))}
            </ul>
            <div>
                <Button variant="primary-ink" href={onboarding().url}>
                    {t('dashboard.setup.continue')}
                </Button>
            </div>
        </Card>
    );
}
