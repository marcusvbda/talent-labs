import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';
import { login } from '@/routes';

type FieldErrors = Partial<Record<'password' | 'passwordConfirmation', string>>;

export default function ResetPassword() {
    const { t } = useT();
    // No backend action exists yet for this closed beta, so there is no
    // Inertia prop carrying the route-bound token — read it straight off
    // the URL instead.
    const token = useMemo(
        () => window.location.pathname.split('/').filter(Boolean).pop() ?? '',
        [],
    );
    void token;

    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<FieldErrors>({});
    const [done, setDone] = useState(false);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const nextErrors: FieldErrors = {};

        if (password.length < 8) {
            nextErrors.password = t('auth.register.errors.password_length');
        }

        if (passwordConfirmation !== password) {
            nextErrors.passwordConfirmation = t(
                'auth.register.errors.password_mismatch',
            );
        }

        setErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        setDone(true);
    };

    return (
        <GuestLayout>
            <Head title={t('auth.reset.title')} />
            {done ? (
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-card-title text-ink">
                            {t('auth.reset.title')}
                        </h1>
                        <p className="text-body text-muted">
                            {t('auth.reset.success')}
                        </p>
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
                            {t('auth.reset.title')}
                        </h1>
                    </div>
                    <form
                        onSubmit={onSubmit}
                        className="flex flex-col gap-5"
                        noValidate
                    >
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
                                    value={password}
                                    onChange={(event) =>
                                        setPassword(event.target.value)
                                    }
                                    autoFocus
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.reset.password_confirmation')}
                            error={errors.passwordConfirmation}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="password_confirmation"
                                    type="password"
                                    size="lg"
                                    autoComplete="new-password"
                                    value={passwordConfirmation}
                                    onChange={(event) =>
                                        setPasswordConfirmation(
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                        <Button
                            type="submit"
                            variant="primary-ink"
                            size="lg"
                            fullWidth
                        >
                            {t('auth.reset.submit')}
                        </Button>
                    </form>
                </>
            )}
        </GuestLayout>
    );
}
