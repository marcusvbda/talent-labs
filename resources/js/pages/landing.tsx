import { Head, usePage } from '@inertiajs/react';
import { Logo } from '@/components/patterns/logo';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { BareLayout } from '@/layouts/bare-layout';
import { dashboard, login } from '@/routes';
import type { SharedProps } from '@/types/shared';

export default function Landing() {
    const { t } = useT();
    const { auth } = usePage<SharedProps>().props;
    const isGuest = auth.user === null;

    return (
        <BareLayout className="flex flex-col items-center justify-center gap-6 text-center">
            <Head title="" />
            <Logo variant="full" size="lg" />
            <p className="text-label-sm text-ink">{t('landing.status')}</p>
            <p className="text-body text-muted">{t('landing.subtitle')}</p>
            <Button
                variant="primary-ink"
                size="lg"
                href={isGuest ? login().url : dashboard().url}
            >
                {isGuest ? t('landing.login') : t('landing.dashboard')}
            </Button>
        </BareLayout>
    );
}
