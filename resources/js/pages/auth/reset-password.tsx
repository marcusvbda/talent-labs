import { Form, Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/Auth/ResetPasswordController';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string | null;
}) {
    const { t } = useT();

    return (
        <GuestLayout>
            <Head title={t('auth.reset.title')} />
            <div className="flex flex-col gap-1">
                <h1 className="text-card-title text-ink">
                    {t('auth.reset.title')}
                </h1>
            </div>
            <Form
                {...store()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-5"
            >
                {({ errors, processing }) => (
                    <>
                        <input type="hidden" name="token" value={token} />
                        <Field
                            label={t('auth.login.email')}
                            error={errors.email}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="email"
                                    type="email"
                                    size="lg"
                                    autoComplete="username"
                                    defaultValue={email ?? ''}
                                    readOnly={email !== null}
                                    required
                                    autoFocus={email === null}
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.reset.password')}
                            error={errors.password}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="password"
                                    type="password"
                                    size="lg"
                                    autoComplete="new-password"
                                    required
                                    autoFocus={email !== null}
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.reset.password_confirmation')}
                            error={errors.password_confirmation}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="password_confirmation"
                                    type="password"
                                    size="lg"
                                    autoComplete="new-password"
                                    required
                                />
                            )}
                        </Field>
                        <Button
                            type="submit"
                            variant="primary-ink"
                            size="lg"
                            fullWidth
                            loading={processing}
                        >
                            {t('auth.reset.submit')}
                        </Button>
                    </>
                )}
            </Form>
        </GuestLayout>
    );
}
