import { Form, Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef } from 'react';
import type { RefObject } from 'react';
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

declare global {
    interface Window {
        turnstile?: {
            render: (
                container: HTMLElement,
                options: {
                    sitekey: string;
                    'response-field-name': string;
                    language: string;
                },
            ) => string;
            reset: (widgetId?: string) => void;
            remove: (widgetId?: string) => void;
        };
    }
}

const TURNSTILE_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

const TurnstileWidget = ({
    siteKey,
    language,
    widgetIdRef,
}: {
    siteKey: string;
    language: string;
    widgetIdRef: RefObject<string | null>;
}) => {
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let cancelled = false;
        let renderedId: string | null = null;

        const render = () => {
            const container = containerRef.current;

            if (cancelled || !container || !window.turnstile) {
                return;
            }

            renderedId = window.turnstile.render(container, {
                sitekey: siteKey,
                'response-field-name': 'captcha',
                language,
            });
            widgetIdRef.current = renderedId;
        };

        let script = document.querySelector<HTMLScriptElement>(
            `script[src="${TURNSTILE_SRC}"]`,
        );

        if (!script) {
            script = document.createElement('script');
            script.src = TURNSTILE_SRC;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        if (window.turnstile) {
            render();
        } else {
            script.addEventListener('load', render);
        }

        return () => {
            cancelled = true;
            script.removeEventListener('load', render);

            if (renderedId !== null) {
                window.turnstile?.remove(renderedId);
                widgetIdRef.current = null;
            }
        };
    }, [siteKey, language, widgetIdRef]);

    return <div ref={containerRef} />;
};

export default function Register({
    invite,
    open,
    turnstileSiteKey,
}: {
    invite: { email: string | null } | null;
    open: boolean;
    turnstileSiteKey: string | null;
}) {
    const inviteEmail = invite?.email ?? null;
    const { t, locale } = useT();
    const turnstileWidgetId = useRef<string | null>(null);
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
                    {open
                        ? t('auth.register.subtitle_open')
                        : t('auth.register.subtitle')}
                </p>
            </div>
            <Form
                {...store()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-5"
                onError={() => {
                    if (turnstileWidgetId.current !== null) {
                        window.turnstile?.reset(turnstileWidgetId.current);
                    }
                }}
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
                                inviteEmail !== null
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
                                    defaultValue={inviteEmail ?? ''}
                                    readOnly={inviteEmail !== null}
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
                        {turnstileSiteKey !== null && (
                            <>
                                <TurnstileWidget
                                    siteKey={turnstileSiteKey}
                                    language={locale === 'pt' ? 'pt-BR' : 'en'}
                                    widgetIdRef={turnstileWidgetId}
                                />
                                {errors.captcha && (
                                    <p
                                        role="alert"
                                        className="text-chip text-danger-text"
                                    >
                                        {errors.captcha}
                                    </p>
                                )}
                            </>
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
