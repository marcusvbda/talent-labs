import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { send } from '@/actions/App/Http/Controllers/Auth/EmailVerificationController';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';
import { logout } from '@/routes';
import type { SharedProps } from '@/types/shared';

const RESEND_COOLDOWN_SECONDS = 60;

type Props = {
    contactEmail: string | null;
};

export default function VerifyEmail({ contactEmail }: Props) {
    const { t } = useT();
    const { auth } = usePage<SharedProps>().props;
    const email = auth.user?.email ?? '';

    const [sending, setSending] = useState(false);
    const [seconds, setSeconds] = useState(0);
    const coolingDown = seconds > 0;

    useEffect(() => {
        if (!coolingDown) {
            return;
        }

        const id = window.setInterval(() => {
            setSeconds((current) => Math.max(current - 1, 0));
        }, 1000);

        return () => window.clearInterval(id);
    }, [coolingDown]);

    const resend = () => {
        router.post(
            send().url,
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onSuccess: () => setSeconds(RESEND_COOLDOWN_SECONDS),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <GuestLayout>
            <Head title={t('auth.verify.title')} />
            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-card-title text-ink">
                            {t('auth.verify.title')}
                        </h1>
                        <p className="text-body text-muted">
                            {t('auth.verify.body', { email })}
                        </p>
                    </div>
                    <Button
                        variant="primary-ink"
                        size="lg"
                        fullWidth
                        loading={sending}
                        disabled={coolingDown}
                        onClick={resend}
                    >
                        {coolingDown
                            ? t('auth.verify.resend_wait', { seconds })
                            : t('auth.verify.resend')}
                    </Button>
                </div>
                <div className="flex flex-col gap-3 border-t border-hairline pt-6">
                    <div className="flex flex-col gap-1">
                        <h2 className="text-body font-medium text-ink">
                            {t('auth.verify.wrong_title')}
                        </h2>
                        <p className="text-body text-muted">
                            {contactEmail
                                ? t('auth.verify.wrong_contact', {
                                      email: contactEmail,
                                  })
                                : t('auth.verify.wrong_no_contact')}
                        </p>
                    </div>
                    <Button
                        variant="secondary-tile"
                        size="lg"
                        fullWidth
                        onClick={() => router.post(logout().url)}
                    >
                        {t('user_menu.logout')}
                    </Button>
                </div>
            </div>
        </GuestLayout>
    );
}
