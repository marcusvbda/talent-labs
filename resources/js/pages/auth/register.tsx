import { Form, Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { store } from '@/actions/App/Http/Controllers/Auth/RegisterController';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';

const browserTimezone = () => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return 'UTC';
    }
};

export default function Register({
    invite,
}: {
    invite: { email: string | null };
}) {
    const { t } = useT();
    const inviteToken = useMemo(
        () => new URLSearchParams(window.location.search).get('invite') ?? '',
        [],
    );
    const timezone = useMemo(browserTimezone, []);

    return (
        <GuestLayout>
            <Head title={t('auth.register.title')} />
            <div className="flex flex-col gap-1">
                <h1 className="text-card-title text-ink">
                    {t('auth.register.title')}
                </h1>
                <p className="text-body text-muted">
                    {t('auth.register.subtitle')}
                </p>
            </div>
            <Form
                {...store()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-5"
            >
                {({ errors, processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="invite"
                            value={inviteToken}
                        />
                        <input type="hidden" name="timezone" value={timezone} />
                        <Field
                            label={t('auth.register.name')}
                            error={errors.name}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="name"
                                    type="text"
                                    size="lg"
                                    autoComplete="name"
                                    required
                                    autoFocus
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.register.email')}
                            error={errors.email}
                            hint={
                                invite.email !== null
                                    ? t('auth.register.invite_locked_hint')
                                    : undefined
                            }
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="email"
                                    type="email"
                                    size="lg"
                                    autoComplete="username"
                                    defaultValue={invite.email ?? ''}
                                    readOnly={invite.email !== null}
                                    required
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.register.password')}
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
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.register.password_confirmation')}
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
                        {errors.invite && (
                            <p
                                role="alert"
                                className="text-chip text-danger-text"
                            >
                                {errors.invite}
                            </p>
                        )}
                        <Button
                            type="submit"
                            variant="primary-ink"
                            size="lg"
                            fullWidth
                            loading={processing}
                        >
                            {t('auth.register.submit')}
                        </Button>
                    </>
                )}
            </Form>
        </GuestLayout>
    );
}
