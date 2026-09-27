import { Form, Head, usePage } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/Auth/ForgotPasswordController';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';
import { login } from '@/routes';
import type { SharedProps } from '@/types/shared';

export default function ForgotPassword() {
    const { t } = useT();
    const { flash } = usePage<SharedProps>().props;

    return (
        <GuestLayout>
            <Head title={t('auth.forgot.title')} />
            {flash?.success ? (
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-card-title text-ink">
                            {t('auth.forgot.title')}
                        </h1>
                        <p className="text-body text-muted">{flash.success}</p>
                    </div>
                    <Button
                        variant="primary-ink"
                        size="lg"
                        fullWidth
                        href={login().url}
                    >
                        {t('auth.closed.login_link')}
                    </Button>
                </div>
            ) : (
                <>
                    <div className="flex flex-col gap-1">
                        <h1 className="text-card-title text-ink">
                            {t('auth.forgot.title')}
                        </h1>
                        <p className="text-body text-muted">
                            {t('auth.forgot.subtitle')}
                        </p>
                    </div>
                    <Form {...store()} className="flex flex-col gap-5">
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
                                <Button
                                    type="submit"
                                    variant="primary-ink"
                                    size="lg"
                                    fullWidth
                                    loading={processing}
                                >
                                    {t('auth.forgot.submit')}
                                </Button>
                            </>
                        )}
                    </Form>
                </>
            )}
        </GuestLayout>
    );
}
