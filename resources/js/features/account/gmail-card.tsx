import { CircleCheck, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { toast } from '@/components/ui/toast';
import {
    useConnectGmail,
    useDisconnectGmail,
} from '@/data/hooks/use-connect-gmail';
import { useT } from '@/i18n/i18n-provider';
import { connect, reconnect } from '@/routes/integrations/oauth';
import type { AccountStatus } from '@/types/contracts';

export const GmailCard = ({
    gmail,
    className,
}: {
    gmail: AccountStatus['gmail'];
    className?: string;
}) => {
    const { t } = useT();
    const connectGmail = useConnectGmail();
    const disconnectGmail = useDisconnectGmail();
    const [confirming, setConfirming] = useState(false);

    const redirect = { query: { return: 'account' } };
    const connectHref = connect('gmail', redirect).url;
    const reconnectHref = reconnect('gmail', redirect).url;

    const onDisconnect = () =>
        disconnectGmail.mutate(undefined, {
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
                        gmail.state === 'connected'
                            ? 'flex items-center gap-3 rounded-tile bg-tile p-card-sm text-body text-ink'
                            : 'flex items-center gap-3 rounded-tile bg-danger-bg p-card-sm text-body text-danger-text'
                    }
                >
                    {gmail.state === 'connected' ? (
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
                        {gmail.state === 'connected' && gmail.accountEmail
                            ? t('account.gmail.connected_as', {
                                  email: gmail.accountEmail,
                              })
                            : t(`account.gmail.status.${gmail.state}`)}
                    </span>
                </p>
                <div className="flex flex-wrap gap-3">
                    {gmail.state === 'disconnected' && (
                        <Button
                            loading={connectGmail.isPending}
                            onClick={() => connectGmail.mutate(connectHref)}
                        >
                            {t('account.gmail.connect')}
                        </Button>
                    )}
                    {gmail.state === 'reauthorization_required' && (
                        <Button
                            loading={connectGmail.isPending}
                            onClick={() => connectGmail.mutate(reconnectHref)}
                        >
                            {t('account.gmail.reconnect')}
                        </Button>
                    )}
                    {gmail.state === 'connected' && (
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
                            loading={disconnectGmail.isPending}
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
