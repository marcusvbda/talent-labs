import { Head } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';
import { login } from '@/routes';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export default function ForgotPassword() {
    const { t } = useT();
    const [email, setEmail] = useState('');
    const [error, setError] = useState<string | undefined>(undefined);
    const [sent, setSent] = useState(false);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!email.trim()) {
            setError(t('auth.register.errors.email_required'));

            return;
        }

        if (!EMAIL_PATTERN.test(email)) {
            setError(t('auth.register.errors.email_invalid'));

            return;
        }

        setError(undefined);
        setSent(true);
    };

    return (
        <GuestLayout>
            <Head title={t('auth.forgot.title')} />
            {sent ? (
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-card-title text-ink">
                            {t('auth.forgot.title')}
                        </h1>
                        <p className="text-body text-muted">
                            {t('auth.forgot.sent')}
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
                            {t('auth.forgot.title')}
                        </h1>
                        <p className="text-body text-muted">
                            {t('auth.forgot.subtitle')}
                        </p>
                    </div>
                    <form
                        onSubmit={onSubmit}
                        className="flex flex-col gap-5"
                        noValidate
                    >
                        <Field label={t('auth.login.email')} error={error}>
                            {(control) => (
                                <Input
                                    {...control}
                                    name="email"
                                    type="email"
                                    size="lg"
                                    autoComplete="username"
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                    autoFocus
                                />
                            )}
                        </Field>
                        <Button
                            type="submit"
                            variant="primary-ink"
                            size="lg"
                            fullWidth
                        >
                            {t('auth.forgot.submit')}
                        </Button>
                    </form>
                </>
            )}
        </GuestLayout>
    );
}
