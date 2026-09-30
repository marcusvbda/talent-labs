import { Head, usePage } from '@inertiajs/react';
import { DemoLoopProvider } from '@/features/landing/demo-loop-context';
import { GuaranteesStrip } from '@/features/landing/guarantees-strip';
import { Hero } from '@/features/landing/hero';
import { HeroDemo } from '@/features/landing/hero-demo';
import { LandingFooter } from '@/features/landing/landing-footer';
import { LandingNav } from '@/features/landing/landing-nav';
import type { LandingProps } from '@/features/landing/types';
import { useT } from '@/i18n/i18n-provider';
import { PublicLayout } from '@/layouts/public-layout';
import { home } from '@/routes';
import type { Locale, SharedProps } from '@/types/shared';

const OG_LOCALES: Record<Locale, string> = {
    en: 'en_US',
    pt: 'pt_BR',
    es: 'es_ES',
};

/** Absolute canonical URL; falls back to the relative path when no origin exists (server). */
const canonicalUrl = (): string =>
    typeof window === 'undefined'
        ? home().url
        : new URL(home().url, window.location.origin).toString();

export default function Landing({
    betaClosed,
    contactEmail,
    legal,
}: LandingProps) {
    const { t, locale } = useT();
    const { app } = usePage<SharedProps>().props;
    const title = t('landing.meta.title', { brand: app.brand.name });
    const description = t('landing.meta.description', {
        brand: app.brand.name,
    });

    return (
        <PublicLayout
            header={<LandingNav betaClosed={betaClosed} />}
            footer={<LandingFooter contactEmail={contactEmail} legal={legal} />}
        >
            <Head title={title}>
                <meta name="description" content={description} />
                <meta property="og:title" content={title} />
                <meta property="og:description" content={description} />
                <meta property="og:type" content="website" />
                <meta property="og:locale" content={OG_LOCALES[locale]} />
                <link rel="canonical" href={canonicalUrl()} />
            </Head>
            <DemoLoopProvider>
                <Hero betaClosed={betaClosed} demo={<HeroDemo />} />
                <GuaranteesStrip />
            </DemoLoopProvider>
        </PublicLayout>
    );
}
