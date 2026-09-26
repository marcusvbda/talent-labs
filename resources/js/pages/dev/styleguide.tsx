import { Head } from '@inertiajs/react';
import { ActionsSection } from '@/features/styleguide/actions-section';
import { BrandSection } from '@/features/styleguide/brand-section';
import { CardsSection } from '@/features/styleguide/cards-section';
import { ChoicesSection } from '@/features/styleguide/choices-section';
import { DataSection } from '@/features/styleguide/data-section';
import { FeedbackSection } from '@/features/styleguide/feedback-section';
import { FormsSection } from '@/features/styleguide/forms-section';
import { NavigationSection } from '@/features/styleguide/navigation-section';
import { OverlaysSection } from '@/features/styleguide/overlays-section';
import { IndicatorsSection } from '@/features/styleguide/indicators-section';
import { ChartSection } from '@/features/styleguide/chart-section';
import { LiveSection } from '@/features/styleguide/live-section';
import { GatingSection } from '@/features/styleguide/gating-section';
import { RowsSection } from '@/features/styleguide/rows-section';
import { ShellSection } from '@/features/styleguide/shell-section';
import { SurfacesSection } from '@/features/styleguide/surfaces-section';
import { TokensSection } from '@/features/styleguide/tokens-section';
import { useT } from '@/i18n/i18n-provider';
import { BareLayout } from '@/layouts/bare-layout';

export default function Styleguide() {
    const { t } = useT();

    const sections = [
        { id: 'tokens', label: t('styleguide.tokens.title') },
        { id: 'actions', label: t('styleguide.actions.title') },
        { id: 'indicators', label: t('styleguide.indicators.title') },
        { id: 'surfaces', label: t('styleguide.surfaces.title') },
        { id: 'forms', label: t('styleguide.forms.title') },
        { id: 'choices', label: t('styleguide.choices.title') },
        { id: 'overlays', label: t('styleguide.overlays.title') },
        { id: 'feedback', label: t('styleguide.feedback.title') },
        { id: 'data', label: t('styleguide.data.title') },
        { id: 'navigation', label: t('styleguide.navigation.title') },
        { id: 'shell', label: t('styleguide.shell.title') },
        { id: 'cards', label: t('styleguide.cards.title') },
        { id: 'rows', label: t('styleguide.rows.title') },
        { id: 'live', label: t('styleguide.live.title') },
        { id: 'chart', label: t('styleguide.chart.title') },
        { id: 'gating', label: t('styleguide.gating.title') },
        { id: 'brand', label: t('styleguide.brand.title') },
    ];

    return (
        <BareLayout>
            <Head title={t('styleguide.title')} />
            <header className="mb-8 flex flex-col gap-2">
                <h1 className="text-display-sm md:text-display">
                    {t('styleguide.title')}
                </h1>
                <p className="text-body text-muted">{t('styleguide.hint')}</p>
            </header>
            <nav
                aria-label={t('styleguide.index')}
                className="sticky top-0 z-10 -mx-4 mb-8 overflow-x-auto bg-shell px-4 py-3 md:-mx-8 md:px-8"
            >
                <ul className="flex gap-4">
                    {sections.map((section) => (
                        <li key={section.id}>
                            <a
                                href={`#${section.id}`}
                                className="rounded-checkbox text-label-sm text-muted hover:text-ink focus-visible:focus-ring"
                            >
                                {section.label}
                            </a>
                        </li>
                    ))}
                </ul>
            </nav>
            <div className="flex flex-col gap-gap">
                <TokensSection />
                <ActionsSection />
                <IndicatorsSection />
                <SurfacesSection />
                <FormsSection />
                <ChoicesSection />
                <OverlaysSection />
                <FeedbackSection />
                <DataSection />
                <NavigationSection />
                <ShellSection />
                <CardsSection />
                <RowsSection />
                <LiveSection />
                <ChartSection />
                <GatingSection />
                <BrandSection />
            </div>
        </BareLayout>
    );
}
