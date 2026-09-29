import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import { usePortal } from '@/data/hooks/use-billing';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type { BillingSummary } from '@/types/contracts';

const Row = ({ label, value }: { label: string; value: string }) => (
    <div className="flex items-center justify-between gap-4 rounded-tile bg-tile p-card-sm text-body text-ink">
        <span className="text-label-sm">{label}</span>
        <span className="min-w-0 text-right break-words">{value}</span>
    </div>
);

export const BillingCard = ({
    billing,
    className,
}: {
    billing: BillingSummary;
    className?: string;
}) => {
    const { t } = useT();
    const format = useFormat();
    const portal = usePortal();

    const onManage = () =>
        portal.mutate(
            { returnTo: 'account' },
            {
                onSuccess: ({ url }) => window.location.assign(url),
                onError: (error) => toast.error(error.message),
            },
        );

    return (
        <DataCard title={t('account.billing.title')} className={className}>
            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <Row
                        label={t('account.billing.plan')}
                        value={t(`plans.${billing.plan.key}.name`)}
                    />
                    <Row
                        label={t('account.billing.title')}
                        value={t(`account.billing.source.${billing.source}`)}
                    />
                </div>
                {billing.renewsAt ? (
                    <p className="text-body text-ink">
                        {t('account.billing.renews', {
                            date: format.date(billing.renewsAt),
                        })}
                    </p>
                ) : billing.endsAt ? (
                    <p className="text-body text-ink">
                        {t('account.billing.ends', {
                            date: format.date(billing.endsAt),
                        })}
                    </p>
                ) : null}
                <div>
                    <Button loading={portal.isPending} onClick={onManage}>
                        {t('account.billing.manage')}
                    </Button>
                </div>
            </div>
        </DataCard>
    );
};
