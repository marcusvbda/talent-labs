import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useConnectEmail } from '@/data/hooks/use-connect-email';
import { SmtpForm } from '@/features/account/smtp-form';
import { useT } from '@/i18n/i18n-provider';
import { dashboard } from '@/routes';
import { reconnect } from '@/routes/integrations/oauth';
import type { AccountStatus } from '@/types/contracts';

export function EmailAccountBanner({
    providerKey,
    smtpDefaults,
}: {
    providerKey: string;
    smtpDefaults: AccountStatus['sendingAccount']['smtpDefaults'];
}) {
    const { t } = useT();
    const connectEmail = useConnectEmail();
    const [smtpOpen, setSmtpOpen] = useState(false);
    const href = reconnect(providerKey, {
        query: { redirect: dashboard().url },
    }).url;

    return (
        <div
            role="note"
            className="flex flex-wrap items-center justify-between gap-3 rounded-tile bg-danger-bg p-card-sm text-body text-danger-text"
        >
            <span className="flex items-center gap-3">
                <TriangleAlert
                    aria-hidden="true"
                    strokeWidth={1.8}
                    className="size-5 shrink-0"
                />
                {t('dashboard.gmail.expired')}
            </span>
            <Button
                size="sm"
                loading={connectEmail.isPending}
                onClick={() =>
                    providerKey === 'smtp'
                        ? setSmtpOpen(true)
                        : connectEmail.mutate(href)
                }
            >
                {t('dashboard.gmail.reconnect')}
            </Button>
            <SmtpForm
                open={smtpOpen}
                onClose={() => setSmtpOpen(false)}
                defaults={smtpDefaults}
            />
        </div>
    );
}
