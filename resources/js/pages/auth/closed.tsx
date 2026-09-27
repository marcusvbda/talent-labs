import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { GuestLayout } from '@/layouts/guest-layout';
import { login } from '@/routes';

export default function Closed() {
    const { t } = useT();

    return (
        <GuestLayout>
            <Head title={t('auth.closed.title')} />
            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-card-title text-ink">
                        {t('auth.closed.title')}
                    </h1>
                    <p className="text-body text-muted">
                        {t('auth.closed.body')}
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
        </GuestLayout>
    );
}
