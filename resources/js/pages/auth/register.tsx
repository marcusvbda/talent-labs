import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from '@/components/ui/toast';
import { useInviteCheck } from '@/data/hooks/use-invite-check';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';
import { login } from '@/routes';
import { closed } from '@/routes/register';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

type FieldErrors = Partial<
    Record<'name' | 'email' | 'password' | 'passwordConfirmation', string>
>;

const browserTimezone = () => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return 'UTC';
    }
};

const RegisterSkeleton = () => {
    const { t } = useT();

    return (
        <div
            role="status"
            aria-busy="true"
            aria-label={t('common.loading')}
            className="flex flex-col gap-3"
        >
            <Skeleton shape="line" className="h-6 w-2/3" />
            <Skeleton shape="block" className="h-52" />
        </div>
    );
};

export default function Register() {
    const { t } = useT();
    const invite = useMemo(
        () => new URLSearchParams(window.location.search).get('invite'),
        [],
    );
    const inviteCheck = useInviteCheck(invite);
    const inviteEmail =
        inviteCheck.data?.valid === true ? inviteCheck.data.email : null;
    const invalid =
        !invite || (inviteCheck.isSuccess && inviteCheck.data.valid === false);
    const loading = Boolean(invite) && inviteCheck.isPending;

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<FieldErrors>({});
    const [done, setDone] = useState(false);
    const [timezone] = useState(browserTimezone);

    useEffect(() => {
        if (invalid) {
            router.visit(closed().url);
        }
    }, [invalid]);

    useEffect(() => {
        if (inviteEmail) {
            setEmail(inviteEmail);
        }
    }, [inviteEmail]);

    if (invalid) {
        return null;
    }

    if (loading || !inviteCheck.data) {
        return (
            <GuestLayout>
                <Head title={t('auth.register.title')} />
                <RegisterSkeleton />
            </GuestLayout>
        );
    }

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const nextErrors: FieldErrors = {};

        if (!name.trim()) {
            nextErrors.name = t('auth.register.errors.name_required');
        }

        if (!email.trim()) {
            nextErrors.email = t('auth.register.errors.email_required');
        } else if (!EMAIL_PATTERN.test(email)) {
            nextErrors.email = t('auth.register.errors.email_invalid');
        }

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

        // Fictional payload — no request is ever sent in this closed beta.
        void { name, email, password, timezone };

        setDone(true);
        toast.success(t('auth.register.success_title'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.register.title')} />
            {done ? (
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-card-title text-ink">
                            {t('auth.register.success_title')}
                        </h1>
                        <p className="text-body text-muted">
                            {t('auth.register.success_body')}
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
                            {t('auth.register.title')}
                        </h1>
                        <p className="text-body text-muted">
                            {t('auth.register.subtitle')}
                        </p>
                    </div>
                    <form
                        onSubmit={onSubmit}
                        className="flex flex-col gap-5"
                        noValidate
                    >
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
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    autoFocus
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.register.email')}
                            error={errors.email}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="email"
                                    type="email"
                                    size="lg"
                                    autoComplete="username"
                                    value={email}
                                    disabled={inviteEmail !== null}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
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
                                    value={password}
                                    onChange={(event) =>
                                        setPassword(event.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            label={t('auth.register.password_confirmation')}
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
                            {t('auth.register.submit')}
                        </Button>
                    </form>
                </>
            )}
        </GuestLayout>
    );
}
