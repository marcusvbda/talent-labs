import { Form, Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/Auth/LoginController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';

export default function Login() {
    const { t } = useT();

    return (
        <GuestLayout>
            <Head title={t('auth.login.title')} />
            <div className="flex flex-col gap-1">
                <h1 className="text-card-title text-ink">
                    {t('auth.login.title')}
                </h1>
                <p className="text-body text-muted">
                    {t('auth.login.subtitle')}
                </p>
            </div>
            <Form
                {...store()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ errors, processing }) => (
                    <>
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
                                    required
                                    autoFocus
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.login.password')}
                            error={errors.password}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="password"
                                    type="password"
                                    size="lg"
                                    autoComplete="current-password"
                                    required
                                />
                            )}
                        </Field>
                        <label className="flex items-center gap-3 text-label-sm text-ink">
                            <Checkbox name="remember" value="1" />
                            {t('auth.login.remember')}
                        </label>
                        <Button
                            type="submit"
                            variant="primary-ink"
                            size="lg"
                            fullWidth
                            loading={processing}
                        >
                            {t('auth.login.submit')}
                        </Button>
                    </>
                )}
            </Form>
        </GuestLayout>
    );
}
