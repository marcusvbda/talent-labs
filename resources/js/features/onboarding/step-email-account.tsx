import { CircleCheck } from 'lucide-react';
import { DataCard } from '@/components/patterns/data-card';
import { ProviderList } from '@/features/account/provider-list';
import { StepFooter } from '@/features/onboarding/step-footer';
import { useT } from '@/i18n/i18n-provider';
import type { AccountStatus } from '@/types/contracts';

export const StepEmailAccount = ({
    account,
    onBack,
    onDone,
}: {
    account: AccountStatus['sendingAccount'];
    onBack: () => void;
    onDone: () => void;
}) => {
    const { t } = useT();
    const connected = account.state === 'connected';

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
                            {account.accountEmail
                                ? t('onboarding.gmail.connected_as', {
                                      email: account.accountEmail,
                                  })
                                : t('onboarding.gmail.connected')}
                        </span>
                    </p>
                ) : (
                    <ProviderList
                        providers={account.providers}
                        returnTo="onboarding"
                    />
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
