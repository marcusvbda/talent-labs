import { Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useConnectEmail } from '@/data/hooks/use-connect-email';
import { useT } from '@/i18n/i18n-provider';
import { connect } from '@/routes/integrations/oauth';
import type { AccountStatus } from '@/types/contracts';

export const ProviderList = ({
    providers,
    returnTo,
}: {
    providers: AccountStatus['sendingAccount']['providers'];
    returnTo: 'account' | 'onboarding';
}) => {
    const { t } = useT();
    const connectEmail = useConnectEmail();

    return (
        <ul className="flex flex-col gap-3">
            {providers.map((provider) => (
                <li
                    key={provider.key}
                    className="flex flex-wrap items-center gap-3 rounded-tile bg-tile p-card-sm"
                >
                    <Mail
                        aria-hidden="true"
                        strokeWidth={1.8}
                        className="size-5 shrink-0 text-accent-deep"
                    />
                    <div className="min-w-0 flex-1">
                        <p className="text-body font-medium text-ink">
                            {t(`integrations.${provider.key}.label`)}
                        </p>
                        <p className="text-body text-muted">
                            {t(`integrations.${provider.key}.description`)}
                        </p>
                    </div>
                    <Button
                        loading={connectEmail.isPending}
                        onClick={() =>
                            connectEmail.mutate(
                                connect(provider.key, {
                                    query: { return: returnTo },
                                }).url,
                            )
                        }
                    >
                        {t('account.gmail.connect')}
                    </Button>
                </li>
            ))}
        </ul>
    );
};
