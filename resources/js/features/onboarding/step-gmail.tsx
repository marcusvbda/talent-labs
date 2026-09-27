import { CircleCheck } from 'lucide-react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { useConnectGmail } from '@/data/hooks/use-connect-gmail';
import { StepFooter } from '@/features/onboarding/step-footer';
import { useT } from '@/i18n/i18n-provider';
import { connect } from '@/routes/integrations/oauth';
import { onboarding } from '@/routes';
import type { AccountStatus } from '@/types/contracts';

export const StepGmail = ({
    gmail,
    onBack,
    onDone,
}: {
    gmail: AccountStatus['gmail'];
    onBack: () => void;
    onDone: () => void;
}) => {
    const { t } = useT();
    const connectGmail = useConnectGmail();
    const connected = gmail.state === 'connected';
    const href = connect('gmail', {
        query: { redirect: onboarding().url },
    }).url;

    return (
        <DataCard
            title={t('onboarding.gmail.title')}
            subtitle={t('onboarding.gmail.body')}
        >
            <div className="flex flex-col gap-6">
                {connected ? (
                    <p className="flex items-center gap-3 rounded-tile bg-tile p-card-sm text-body text-ink">
                        <CircleCheck
                            aria-hidden="true"
                            strokeWidth={1.8}
                            className="size-5 shrink-0 text-accent-deep"
                        />
                        <span className="min-w-0 break-words">
                            {gmail.accountEmail
                                ? t('onboarding.gmail.connected_as', {
                                      email: gmail.accountEmail,
                                  })
                                : t('onboarding.gmail.connected')}
                        </span>
                    </p>
                ) : (
                    <div>
                        <Button
                            loading={connectGmail.isPending}
                            onClick={() => connectGmail.mutate(href)}
                        >
                            {t('onboarding.gmail.connect')}
                        </Button>
                    </div>
                )}
                <StepFooter
                    onBack={onBack}
                    onContinue={onDone}
                    disabled={!connected}
                />
            </div>
        </DataCard>
    );
};
