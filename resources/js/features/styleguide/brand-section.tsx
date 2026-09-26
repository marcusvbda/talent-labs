import { Logo } from '@/components/patterns/logo';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const variants = [
    { variant: 'full', label: 'styleguide.brand.variant_full' },
    { variant: 'mark', label: 'styleguide.brand.variant_mark' },
] as const;

const sizes = [
    { size: 'sm', label: 'styleguide.brand.size_sm' },
    { size: 'md', label: 'styleguide.brand.size_md' },
    { size: 'lg', label: 'styleguide.brand.size_lg' },
] as const;

// Each tone is only meaningful on its own surface (ink on dark is invisible).
const backgrounds = [
    {
        className: 'bg-card text-ink',
        label: 'styleguide.brand.bg_light',
        tone: 'default',
        toneLabel: 'styleguide.brand.tone_default',
    },
    {
        className: 'bg-dark text-card',
        label: 'styleguide.brand.bg_dark',
        tone: 'inverse',
        toneLabel: 'styleguide.brand.tone_inverse',
    },
    {
        className: 'bg-accent text-ink',
        label: 'styleguide.brand.bg_accent',
        tone: 'on-accent',
        toneLabel: 'styleguide.brand.tone_on_accent',
    },
] as const;

export function BrandSection() {
    const { t } = useT();

    return (
        <StyleguideSection id="brand" title={t('styleguide.brand.title')}>
            <div className="grid gap-6 lg:grid-cols-3">
                {backgrounds.map((bg) => (
                    <div key={bg.label} className="flex flex-col gap-3">
                        <h3 className="text-label-sm text-muted">
                            {t(bg.label)}
                        </h3>
                        <div
                            className={`flex flex-col gap-6 rounded-card-sm p-card-sm ${bg.className}`}
                        >
                            {variants.map((variant) => (
                                <div
                                    key={variant.variant}
                                    className="flex flex-col gap-2"
                                >
                                    <span className="text-label-sm opacity-70">
                                        {t(bg.toneLabel)} / {t(variant.label)}
                                    </span>
                                    <div className="flex flex-wrap items-center gap-6">
                                        {sizes.map((size) => (
                                            <Logo
                                                key={size.size}
                                                tone={bg.tone}
                                                variant={variant.variant}
                                                size={size.size}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </StyleguideSection>
    );
}
