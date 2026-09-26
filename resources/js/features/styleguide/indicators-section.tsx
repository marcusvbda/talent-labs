import type { ReactNode } from 'react';
import { Send } from 'lucide-react';
import { Avatar } from '@/components/ui/avatar';
import { Chip } from '@/components/ui/chip';
import { Pill } from '@/components/ui/pill';
import { ProgressBar } from '@/components/ui/progress-bar';
import { StatusDisc } from '@/components/ui/status-disc';
import { TickMeter } from '@/components/ui/tick-meter';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { StyleguideSection } from './styleguide-section';

const Group = ({
    title,
    dark,
    children,
}: {
    title: string;
    dark?: boolean;
    children: ReactNode;
}) => (
    <div className="flex flex-col gap-3">
        <h3 className="text-label-sm text-muted">{title}</h3>
        <div
            className={cn(
                'flex flex-wrap items-center gap-4 rounded-card-sm p-card-sm',
                dark ? 'bg-dark' : 'bg-card',
            )}
        >
            {children}
        </div>
    </div>
);

const TINTS = ['orange', 'neutral', 'green', 'red'] as const;
const STATUSES = ['sending', 'done', 'failed', 'waiting'] as const;

export function IndicatorsSection() {
    const { t } = useT();

    return (
        <StyleguideSection
            id="indicators"
            title={t('styleguide.indicators.title')}
        >
            <Group title={t('styleguide.indicators.pills')}>
                <div className="flex flex-wrap items-center gap-4 rounded-full bg-hero p-3">
                    <Pill tone="white-on-accent" icon={Send}>
                        {t('styleguide.indicators.sample_pill')}
                    </Pill>
                </div>
                <Pill tone="tile" icon={Send}>
                    {t('styleguide.indicators.sample_pill')}
                </Pill>
                <Pill tone="dark" icon={Send}>
                    {t('styleguide.indicators.sample_pill')}
                </Pill>
            </Group>
            <Group title={t('styleguide.indicators.pills')} dark>
                <Pill tone="white-on-accent" icon={Send}>
                    {t('styleguide.indicators.sample_pill')}
                </Pill>
                <Pill tone="tile" icon={Send}>
                    {t('styleguide.indicators.sample_pill')}
                </Pill>
                <Pill tone="dark" icon={Send}>
                    {t('styleguide.indicators.sample_pill')}
                </Pill>
            </Group>
            <Group title={t('styleguide.indicators.chips')}>
                <Chip variant="stack">
                    {t('styleguide.indicators.sample_stack')}
                </Chip>
                <Chip variant="language">EN</Chip>
                <Chip variant="language">PT</Chip>
                <Chip variant="language">ES</Chip>
                <Chip variant="plan">
                    {t('styleguide.indicators.sample_plan')}
                </Chip>
                <Chip variant="delta-up">+12%</Chip>
                <Chip variant="delta-down">-4%</Chip>
            </Group>
            <Group title={t('styleguide.indicators.chips_dark')} dark>
                <Chip variant="stack">
                    {t('styleguide.indicators.sample_stack')}
                </Chip>
                <Chip variant="language">EN</Chip>
                <Chip variant="plan">
                    {t('styleguide.indicators.sample_plan')}
                </Chip>
                <Chip variant="delta-up">+12%</Chip>
                <Chip variant="delta-down">-4%</Chip>
            </Group>
            <Group title={t('styleguide.indicators.avatars')}>
                <Avatar initials="MB" size="sm" />
                <Avatar initials="MB" size="md" />
                <Avatar initials="MB" size="lg" />
            </Group>
            {[false, true].map((dark) => (
                <Group
                    key={String(dark)}
                    title={
                        dark
                            ? t('styleguide.indicators.discs_dark')
                            : t('styleguide.indicators.discs')
                    }
                    dark={dark}
                >
                    {STATUSES.map((status, index) => (
                        <StatusDisc
                            key={status}
                            status={status}
                            tint={TINTS[index]}
                        />
                    ))}
                    <StatusDisc
                        status="icon"
                        icon={Send}
                        tint="orange"
                        label={t('styleguide.indicators.sample_pill')}
                    />
                    <StatusDisc status="done" tint="green" size="sm" />
                    <StatusDisc status="done" tint="green" size="lg" />
                </Group>
            ))}
            <Group title={t('styleguide.indicators.progress')}>
                {(['accent', 'ink'] as const).map((tone) => (
                    <ProgressBar
                        key={tone}
                        value={60}
                        tone={tone}
                        label={t('styleguide.indicators.progress')}
                    />
                ))}
            </Group>
            <Group title={t('styleguide.indicators.progress_dark')} dark>
                <ProgressBar
                    value={60}
                    tone="on-dark"
                    label={t('styleguide.indicators.progress')}
                />
                <ProgressBar
                    value={35}
                    tone="accent"
                    label={t('styleguide.indicators.progress')}
                />
            </Group>
            <Group title={t('styleguide.indicators.ticks')}>
                <TickMeter
                    total={20}
                    filled={12}
                    tone="accent"
                    label={t('styleguide.indicators.ticks')}
                    className="w-full"
                />
                <TickMeter
                    total={20}
                    filled={7}
                    tone="ink"
                    label={t('styleguide.indicators.ticks')}
                    className="w-full"
                />
            </Group>
            <Group title={t('styleguide.indicators.ticks_dark')} dark>
                <TickMeter
                    total={20}
                    filled={12}
                    tone="on-dark"
                    label={t('styleguide.indicators.ticks')}
                    className="w-full"
                />
                <TickMeter
                    total={20}
                    filled={12}
                    tone="accent"
                    label={t('styleguide.indicators.ticks')}
                    className="w-full"
                />
            </Group>
        </StyleguideSection>
    );
}
