import { CircleCheck, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { toast } from '@/components/ui/toast';
import {
    useConnectEmail,
    useDisconnectEmail,
} from '@/data/hooks/use-connect-email';
import { SmtpForm } from '@/features/account/smtp-form';
import { ProviderList } from '@/features/account/provider-list';
import { useT } from '@/i18n/i18n-provider';
import { reconnect } from '@/routes/integrations/oauth';
import type { AccountStatus } from '@/types/contracts';

export const EmailAccountCard = ({
    account,
    className,
}: {
    account: AccountStatus['sendingAccount'];
    className?: string;
}) => {
    const { t } = useT();
    const connectEmail = useConnectEmail();
    const disconnectEmail = useDisconnectEmail(account.providerKey);
    const [confirming, setConfirming] = useState(false);
    const [smtpOpen, setSmtpOpen] = useState(false);
    const isSmtp = account.providerKey === 'smtp';

    const redirect = { query: { return: 'account' } };
    const reconnectHref =
        account.providerKey === null
            ? null
            : reconnect(account.providerKey, redirect).url;

    const onDisconnect = () =>
        disconnectEmail.mutate(undefined, {
            onSuccess: () => {
                setConfirming(false);
                toast.success(t('account.gmail.disconnected'));
            },
            onError: () => toast.error(t('account.gmail.disconnect_failed')),
        });

    return (
        <DataCard
            title={t('account.gmail.title')}
            subtitle={t('account.gmail.body')}
            className={className}
        >
            <div className="flex flex-col gap-6">
                <p
                    className={
                        account.state === 'connected'
                            ? 'flex items-center gap-3 rounded-tile bg-tile p-card-sm text-body text-ink'
                            : 'flex items-center gap-3 rounded-tile bg-danger-bg p-card-sm text-body text-danger-text'
                    }
                >
                    {account.state === 'connected' ? (
                        <CircleCheck
                            aria-hidden="true"
                            strokeWidth={1.8}
                            className="size-5 shrink-0 text-accent-deep"
                        />
                    ) : (
                        <TriangleAlert
                            aria-hidden="true"
                            strokeWidth={1.8}
                            className="size-5 shrink-0"
                        />
                    )}
                    <span className="min-w-0 break-words">
                        {account.state === 'connected' && account.accountEmail
                            ? t('account.gmail.connected_as', {
                                  email: account.accountEmail,
                              })
                            : t(`account.gmail.status.${account.state}`)}
                    </span>
                </p>
                {isSmtp && account.state !== 'disconnected' && (
                    <p className="text-body text-muted">
                        {t('integrations.smtp.sent_notice')}
                    </p>
                )}
                {account.providerKey === 'microsoft' &&
                    account.state !== 'disconnected' && (
                        <p className="text-body text-muted">
                            {t('integrations.microsoft.cv_notice')}
                        </p>
                    )}
                {account.state === 'disconnected' && (
                    <ProviderList
                        providers={account.providers}
                        returnTo="account"
                    />
                )}
                <div className="flex flex-wrap gap-3">
                    {account.state === 'reauthorization_required' && (
                        <Button
                            loading={connectEmail.isPending}
                            onClick={() => {
                                if (isSmtp) {
                                    setSmtpOpen(true);

                                    return;
                                }

                                if (reconnectHref !== null) {
                                    connectEmail.mutate(reconnectHref);
                                }
                            }}
                        >
                            {t('account.gmail.reconnect')}
                        </Button>
                    )}
                    {account.state === 'connected' && (
                        <Button
                            variant="ghost"
                            className="text-danger-text"
                            onClick={() => setConfirming(true)}
                        >
                            {t('account.gmail.disconnect')}
                        </Button>
                    )}
                </div>
            </div>
            <SmtpForm
                open={smtpOpen}
                onClose={() => setSmtpOpen(false)}
                defaults={account.smtpDefaults}
            />
            <Modal
                open={confirming}
                onClose={() => setConfirming(false)}
                size="sm"
                title={t('account.gmail.disconnect_title')}
                description={t('account.gmail.disconnect_body')}
                footer={
                    <>
                        <Button
                            variant="secondary-tile"
                            onClick={() => setConfirming(false)}
                        >
                            {t('account.cancel')}
                        </Button>
                        <Button
                            loading={disconnectEmail.isPending}
                            onClick={onDisconnect}
                        >
                            {t('account.gmail.disconnect')}
                        </Button>
                    </>
                }
            />
        </DataCard>
    );
};
