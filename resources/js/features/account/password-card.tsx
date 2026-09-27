import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import { useChangePassword } from '@/data/hooks/use-account';
import { useT } from '@/i18n/i18n-provider';

export const PasswordCard = ({ className }: { className?: string }) => {
    const { t } = useT();
    const changePassword = useChangePassword();
    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');

    const onSubmit = () => {
        changePassword.mutate(
            { currentPassword, password, passwordConfirmation },
            {
                onSuccess: () => {
                    setCurrentPassword('');
                    setPassword('');
                    setPasswordConfirmation('');
                    toast.success(t('account.password.updated'));
                },
                onError: () => toast.error(t('account.password.failed')),
            },
        );
    };

    const errors = changePassword.error?.errors;

    return (
        <DataCard title={t('account.password.title')} className={className}>
            <div className="flex flex-col gap-6">
                <Field
                    label={t('account.password.current')}
                    error={errors?.current_password?.[0]}
                >
                    {(control) => (
                        <Input
                            {...control}
                            type="password"
                            autoComplete="current-password"
                            value={currentPassword}
                            onChange={(event) =>
                                setCurrentPassword(event.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    label={t('account.password.new')}
                    error={errors?.password?.[0]}
                >
                    {(control) => (
                        <Input
                            {...control}
                            type="password"
                            autoComplete="new-password"
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                        />
                    )}
                </Field>
                <Field label={t('account.password.confirm')}>
                    {(control) => (
                        <Input
                            {...control}
                            type="password"
                            autoComplete="new-password"
                            value={passwordConfirmation}
                            onChange={(event) =>
                                setPasswordConfirmation(event.target.value)
                            }
                        />
                    )}
                </Field>
                <div>
                    <Button
                        loading={changePassword.isPending}
                        onClick={onSubmit}
                    >
                        {t('account.password.submit')}
                    </Button>
                </div>
            </div>
        </DataCard>
    );
};
