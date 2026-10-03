import { Head, usePage } from '@inertiajs/react';
import { lazy, useEffect } from 'react';
import { DemoLoopProvider } from '@/features/landing/demo-loop-context';
import { GuaranteesStrip } from '@/features/landing/guarantees-strip';
import { Hero } from '@/features/landing/hero';
import { HeroDemo } from '@/features/landing/hero-demo';
import { LandingFooter } from '@/features/landing/landing-footer';
import { LandingNav } from '@/features/landing/landing-nav';
import { LazySection } from '@/features/landing/lazy-section';
import { requestMountAll } from '@/features/landing/scroll-to-hash';
import type { LandingProps } from '@/features/landing/types';
import { useT } from '@/i18n/i18n-provider';
import { PublicLayout } from '@/layouts/public-layout';
import { home } from '@/routes';
import type { Locale, SharedProps } from '@/types/shared';

const CtaBanner = lazy(() =>
    import('@/features/landing/cta-banner').then((m) => ({
        default: m.CtaBanner,
    })),
);
const Faq = lazy(() =>
    import('@/features/landing/faq').then((m) => ({ default: m.Faq })),
);
const FeatureLanguages = lazy(() =>
    import('@/features/landing/feature-languages').then((m) => ({
        default: m.FeatureLanguages,
    })),
);
const FeatureMatching = lazy(() =>
    import('@/features/landing/feature-matching').then((m) => ({
        default: m.FeatureMatching,
    })),
);
const LiveBand = lazy(() =>
    import('@/features/landing/live-band').then((m) => ({
        default: m.LiveBand,
    })),
);
const Pricing = lazy(() =>
    import('@/features/landing/pricing').then((m) => ({ default: m.Pricing })),
);

const OG_LOCALES: Record<Locale, string> = {
    en: 'en_US',
    pt: 'pt_BR',
};

/** Absolute canonical URL; falls back to the relative path when no origin exists (server). */
const canonicalUrl = (): string =>
    typeof window === 'undefined'
        ? home().url
        : new URL(home().url, window.location.origin).toString();

export default function Landing({
    plans,
    defaultRegion,
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

    useEffect(() => {
        // Landing on a deep link: mount every section so the anchor resolves.
        if (window.location.hash) {
            requestMountAll();
        }
    }, []);

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
                <LazySection id="product">
                    <FeatureMatching />
                </LazySection>
                <LazySection id="languages">
                    <FeatureLanguages />
                </LazySection>
                <LazySection id="how">
                    <LiveBand betaClosed={betaClosed} />
                </LazySection>
            </DemoLoopProvider>
            <LazySection id="plans" className="py-12">
                <Pricing
                    plans={plans}
                    defaultRegion={defaultRegion}
                    betaClosed={betaClosed}
                />
            </LazySection>
            <LazySection id="faq">
                <Faq legal={legal} />
            </LazySection>
            <LazySection className="min-h-lazy-cta">
                <CtaBanner betaClosed={betaClosed} />
            </LazySection>
        </PublicLayout>
    );
}
