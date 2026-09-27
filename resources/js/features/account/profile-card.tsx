import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import { useSaveAccount } from '@/data/hooks/use-account';
import { useT } from '@/i18n/i18n-provider';
import type { Account } from '@/types/contracts';

export const ProfileCard = ({
    account,
    className,
}: {
    account: Account;
    className?: string;
}) => {
    const { t } = useT();
    const save = useSaveAccount();
    const [name, setName] = useState(account.name);

    const onSave = () => {
        save.mutate(
            {
                name,
                locale: account.locale,
                timezone: account.timezone ?? '',
                country: account.country ?? '',
            },
            {
                onSuccess: () => toast.success(t('account.saved')),
                onError: () => toast.error(t('account.save_failed')),
            },
        );
    };

    return (
        <DataCard title={t('account.profile.title')} className={className}>
            <div className="flex flex-col gap-6">
                <Field
                    label={t('account.profile.name')}
                    error={save.error?.errors?.name?.[0]}
                >
                    {(control) => (
                        <Input
                            {...control}
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                    )}
                </Field>
                <Field label={t('account.profile.email')}>
                    {(control) => (
                        <Input {...control} value={account.email} disabled />
                    )}
                </Field>
                <div>
                    <Button loading={save.isPending} onClick={onSave}>
                        {t('account.save')}
                    </Button>
                </div>
            </div>
        </DataCard>
    );
};
