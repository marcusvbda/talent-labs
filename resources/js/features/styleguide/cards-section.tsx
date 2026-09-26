import {
    CalendarCheck,
    Layers,
    Send,
    SlidersHorizontal,
    Timer,
} from 'lucide-react';
import { DarkCard } from '@/components/patterns/dark-card';
import { DataCard } from '@/components/patterns/data-card';
import { HeroCard } from '@/components/patterns/hero-card';
import { SectionHeader } from '@/components/patterns/section-header';
import { StatTile } from '@/components/patterns/stat-tile';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const BARS = [112, 100, 112, 86, 110, 42];

const Group = ({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) => (
    <div className="flex flex-col gap-3">
        <h3 className="text-label font-medium">{title}</h3>
        {children}
    </div>
);

export function CardsSection() {
    const { t } = useT();
    const stats = [
        { label: t('styleguide.cards.stat_queued'), value: '12' },
        { label: t('styleguide.cards.stat_failed'), value: '1' },
        { label: t('styleguide.cards.stat_next'), value: '0:42' },
    ];
    const hero = (loading: boolean) => (
        <HeroCard
            label={t('styleguide.cards.hero_label')}
            icon={Send}
            value="18"
            suffix="/ 50"
            bars={BARS}
            caption={t('styleguide.cards.hero_caption')}
            stats={stats}
            loading={loading}
        />
    );
    const states = ['ready', 'loading', 'empty', 'error'] as const;

    return (
        <StyleguideSection id="cards" title={t('styleguide.cards.title')}>
            <Group title={t('styleguide.cards.hero')}>
                <div className="grid gap-gap lg:grid-cols-2">
                    {hero(false)}
                    {hero(true)}
                </div>
            </Group>
            <Group title={t('styleguide.cards.tiles')}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile
                        label={t('styleguide.cards.tile_collected')}
                        icon={Layers}
                        tint="orange"
                        value="248"
                        delta={{
                            direction: 'up',
                            label: t('styleguide.cards.delta_up'),
                        }}
                    />
                    <StatTile
                        label={t('styleguide.cards.tile_sent')}
                        icon={Send}
                        tint="green"
                        value="18"
                        unit="/ 50"
                        meter={{ total: 10, filled: 4 }}
                    />
                    <StatTile
                        label={t('styleguide.cards.tile_interviews')}
                        icon={CalendarCheck}
                        tint="red"
                        value="3"
                        delta={{
                            direction: 'down',
                            label: t('styleguide.cards.delta_down'),
                        }}
                    />
                    <StatTile
                        label={t('styleguide.cards.tile_next')}
                        icon={Timer}
                        tint="neutral"
                        value="0:42"
                        context={t('styleguide.cards.context')}
                    />
                    <StatTile
                        label={t('styleguide.cards.tile_collected')}
                        icon={Layers}
                        tint="orange"
                        value="0"
                        loading
                    />
                </div>
            </Group>
            <Group title={t('styleguide.cards.data')}>
                <div className="grid gap-gap md:grid-cols-2">
                    {states.map((state) => (
                        <DataCard
                            key={state}
                            title={t('styleguide.cards.data_title')}
                            subtitle={t(`styleguide.cards.state_${state}`)}
                            state={state}
                            onRetry={() => undefined}
                            actions={
                                <IconButton
                                    icon={SlidersHorizontal}
                                    label={t('styleguide.cards.filters')}
                                />
                            }
                            footer={
                                <Button variant="secondary-tile" size="sm">
                                    {t('styleguide.cards.view_all')}
                                </Button>
                            }
                        >
                            <p className="text-body text-muted">
                                {t('styleguide.cards.data_body')}
                            </p>
                        </DataCard>
                    ))}
                </div>
            </Group>
            <Group title={t('styleguide.cards.dark')}>
                <DarkCard
                    title={t('styleguide.cards.dark_title')}
                    subtitle={t('styleguide.cards.dark_subtitle')}
                >
                    <p className="text-body">
                        {t('styleguide.cards.data_body')}
                    </p>
                </DarkCard>
            </Group>
            <Group title={t('styleguide.cards.header')}>
                <SectionHeader
                    title={t('styleguide.cards.section_title')}
                    action={
                        <Button variant="secondary-tile" size="sm">
                            {t('styleguide.cards.view_all')}
                        </Button>
                    }
                />
            </Group>
        </StyleguideSection>
    );
}
