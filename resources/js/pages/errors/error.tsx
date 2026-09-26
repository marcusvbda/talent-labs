import { Head, usePage } from '@inertiajs/react';
import { Logo } from '@/components/patterns/logo';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { BareLayout } from '@/layouts/bare-layout';
import { dashboard, home } from '@/routes';
import type { SharedProps } from '@/types/shared';

type ErrorStatus = 403 | 404 | 419 | 500 | 503;

export default function ErrorPage({ status }: { status: ErrorStatus }) {
    const { t } = useT();
    const { auth } = usePage<SharedProps>().props;
    const isGuest = auth.user === null;
    const title = t(`errors.${status}.title`);

    return (
        <BareLayout className="flex flex-col items-center justify-center gap-6 text-center">
            <Head title={title} />
            <Logo variant="full" size="lg" />
            <p className="text-hero-numeral-sm text-ink tabular-nums md:text-hero-numeral">
                {status}
            </p>
            <h1 className="text-card-title text-ink">{title}</h1>
            <p className="text-body text-muted">
                {t(`errors.${status}.message`)}
            </p>
            <Button
                variant="primary-ink"
                size="lg"
                href={isGuest ? home().url : dashboard().url}
            >
                {isGuest ? t('errors.home') : t('errors.back_to_dashboard')}
            </Button>
        </BareLayout>
    );
}
