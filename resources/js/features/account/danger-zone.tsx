import { router } from '@inertiajs/react';
import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Modal } from '@/components/ui/modal';
import { toast } from '@/components/ui/toast';
import { useDeleteAccount } from '@/data/hooks/use-account';
import { useT } from '@/i18n/i18n-provider';
import { home } from '@/routes';

export const DangerZone = ({ className }: { className?: string }) => {
    const { t } = useT();
    const deleteAccount = useDeleteAccount();
    const [confirming, setConfirming] = useState(false);
    const [password, setPassword] = useState('');

    const onDelete = () =>
        deleteAccount.mutate(
            { password },
            {
                onSuccess: () => {
                    setConfirming(false);
                    toast.success(t('account.delete.done'));
                    router.visit(home());
                },
            },
        );

    return (
        <DataCard title={t('account.danger.title')} className={className}>
            <div className="flex flex-col gap-4">
                <p className="text-body text-muted">
                    {t('account.delete.warning')}
                </p>
                <div>
                    <Button
                        variant="ghost"
                        className="text-danger-text"
                        onClick={() => setConfirming(true)}
                    >
                        {t('account.delete.title')}
                    </Button>
                </div>
            </div>
            <Modal
                open={confirming}
                onClose={() => setConfirming(false)}
                size="sm"
                title={t('account.delete.confirm_title')}
                description={t('account.delete.warning')}
                footer={
                    <>
                        <Button
                            variant="secondary-tile"
                            onClick={() => setConfirming(false)}
                        >
                            {t('account.cancel')}
                        </Button>
                        <Button
                            loading={deleteAccount.isPending}
                            disabled={password === ''}
                            onClick={onDelete}
                        >
                            {t('account.delete.confirm')}
                        </Button>
                    </>
                }
            >
                <Field
                    label={t('account.delete.password_label')}
                    error={deleteAccount.error?.errors?.password?.[0]}
                >
                    {(control) => (
                        <Input
                            {...control}
                            type="password"
                            autoComplete="current-password"
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                        />
                    )}
                </Field>
            </Modal>
        </DataCard>
    );
};
