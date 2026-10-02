import { useT } from '@/i18n/i18n-provider';
import { BetaCta } from './beta-cta';

export function CtaBanner({ betaClosed }: { betaClosed: boolean }) {
    const { t } = useT();

    return (
        <section aria-labelledby="cta-title" className="py-12 md:py-16">
            <div className="flex flex-col items-start gap-6 rounded-card-sm bg-hero p-8 text-ink md:rounded-card md:p-12">
                <h2
                    id="cta-title"
                    className="text-landing-cta-sm md:text-landing-cta"
                >
                    {t('landing.cta.title')}
                </h2>
                <BetaCta
                    betaClosed={betaClosed}
                    variant="primary-ink"
                    size="lg"
                />
                <p className="max-w-2xl text-landing-body">
                    {t('landing.email.note')}
                </p>
            </div>
        </section>
    );
}
