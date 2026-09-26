import type { ReactNode } from 'react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { StyleguideSection } from './styleguide-section';

type Swatch = { name: string; className: string };

const SWATCHES: Swatch[] = [
    { name: 'canvas', className: 'bg-canvas' },
    { name: 'shell', className: 'bg-shell' },
    { name: 'card', className: 'bg-card' },
    { name: 'tile', className: 'bg-tile' },
    { name: 'ink', className: 'bg-ink' },
    { name: 'muted', className: 'bg-muted' },
    { name: 'faint', className: 'bg-faint' },
    { name: 'hairline', className: 'bg-hairline' },
    { name: 'accent', className: 'bg-accent' },
    { name: 'accent-deep', className: 'bg-accent-deep' },
    { name: 'accent-soft', className: 'bg-accent-soft' },
    { name: 'accent-line', className: 'bg-accent-line' },
    { name: 'dark', className: 'bg-dark' },
    { name: 'dark-2', className: 'bg-dark-2' },
    { name: 'dark-line', className: 'bg-dark-line' },
    { name: 'dark-muted', className: 'bg-dark-muted' },
    { name: 'success', className: 'bg-success' },
    { name: 'success-bg', className: 'bg-success-bg' },
    { name: 'success-text', className: 'bg-success-text' },
    { name: 'danger', className: 'bg-danger' },
    { name: 'danger-bg', className: 'bg-danger-bg' },
    { name: 'danger-text', className: 'bg-danger-text' },
    { name: 'disc-orange', className: 'bg-disc-orange' },
    { name: 'disc-neutral', className: 'bg-disc-neutral' },
    { name: 'disc-green', className: 'bg-disc-green' },
    { name: 'disc-red', className: 'bg-disc-red' },
    { name: 'scrim', className: 'bg-scrim' },
];

const RADII: Swatch[] = [
    { name: 'shell', className: 'rounded-shell' },
    { name: 'card', className: 'rounded-card' },
    { name: 'card-sm', className: 'rounded-card-sm' },
    { name: 'panel', className: 'rounded-panel' },
    { name: 'tile', className: 'rounded-tile' },
    { name: 'row', className: 'rounded-row' },
    { name: 'logo', className: 'rounded-logo' },
    { name: 'logo-lg', className: 'rounded-logo-lg' },
    { name: 'logo-sm', className: 'rounded-logo-sm' },
    { name: 'checkbox', className: 'rounded-checkbox' },
];

type TypeStep = { name: string; desktop: string; mobile: string };

const TYPE_SCALE: TypeStep[] = [
    { name: 'display', desktop: 'text-display', mobile: 'text-display-sm' },
    {
        name: 'hero-numeral',
        desktop: 'text-hero-numeral',
        mobile: 'text-hero-numeral-sm',
    },
    {
        name: 'hero-suffix',
        desktop: 'text-hero-suffix',
        mobile: 'text-hero-suffix-sm',
    },
    {
        name: 'numeral-lg',
        desktop: 'text-numeral-lg',
        mobile: 'text-numeral-lg-sm',
    },
    {
        name: 'card-title',
        desktop: 'text-card-title',
        mobile: 'text-card-title-sm',
    },
    {
        name: 'row-title',
        desktop: 'text-row-title',
        mobile: 'text-row-title-sm',
    },
    { name: 'body', desktop: 'text-body', mobile: 'text-body' },
    { name: 'label', desktop: 'text-label', mobile: 'text-label-sm' },
    { name: 'chip', desktop: 'text-chip', mobile: 'text-chip' },
];

const Group = ({ title, children }: { title: string; children: ReactNode }) => (
    <div className="rounded-card-sm bg-card p-card-sm md:p-card">
        <h3 className="mb-4 text-row-title-sm md:text-row-title">{title}</h3>
        {children}
    </div>
);

export function TokensSection() {
    const { t } = useT();

    return (
        <StyleguideSection id="tokens" title={t('styleguide.tokens.title')}>
            <Group title={t('styleguide.tokens.colors')}>
                <ul className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    {SWATCHES.map((swatch) => (
                        <li key={swatch.name} className="min-w-0">
                            <div
                                className={cn(
                                    'mb-2 h-control-sm rounded-row border border-hairline',
                                    swatch.className,
                                )}
                            />
                            <p className="truncate text-label-sm">
                                {swatch.name}
                            </p>
                            <p className="truncate text-chip text-muted">
                                {`--color-${swatch.name}`}
                            </p>
                        </li>
                    ))}
                </ul>
            </Group>

            <Group title={t('styleguide.tokens.radii')}>
                <ul className="grid grid-cols-2 gap-4 md:grid-cols-5">
                    {RADII.map((radius) => (
                        <li key={radius.name} className="min-w-0">
                            <div
                                className={cn(
                                    'mb-2 h-control-md border border-accent-line bg-accent-soft',
                                    radius.className,
                                )}
                            />
                            <p className="truncate text-chip text-muted">
                                {`--radius-${radius.name}`}
                            </p>
                        </li>
                    ))}
                </ul>
            </Group>

            <Group title={t('styleguide.tokens.shadow')}>
                <div className="rounded-card bg-shell p-card shadow-shell">
                    <p className="truncate text-chip text-muted">
                        --shadow-shell
                    </p>
                </div>
            </Group>

            <Group title={t('styleguide.tokens.type')}>
                <ul className="flex flex-col gap-6">
                    {TYPE_SCALE.map((step) => (
                        <li key={step.name} className="min-w-0">
                            <p className="mb-2 text-chip text-muted">
                                {`${step.name} · ${step.desktop} / ${step.mobile}`}
                            </p>
                            <div className="grid gap-2 md:grid-cols-2">
                                <p className={cn('truncate', step.desktop)}>
                                    {t('styleguide.tokens.sample')}
                                </p>
                                <p className={cn('truncate', step.mobile)}>
                                    {t('styleguide.tokens.sample')}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            </Group>

            <Group title={t('styleguide.tokens.motion')}>
                <ul className="grid gap-6 md:grid-cols-3">
                    <li className="flex items-center gap-3">
                        <span
                            aria-hidden="true"
                            className="size-3 animate-live-pulse rounded-full bg-accent"
                        />
                        <span className="text-label-sm">live-pulse</span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span
                            aria-hidden="true"
                            className="size-6 animate-spin-ring rounded-full border-2 border-hairline border-t-accent"
                        />
                        <span className="text-label-sm">spin-ring</span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span
                            aria-hidden="true"
                            className="h-3 w-full max-w-40 animate-shimmer rounded-full bg-linear-to-r from-tile via-hairline to-tile bg-size-[200%_100%]"
                        />
                        <span className="text-label-sm">shimmer</span>
                    </li>
                </ul>
                <p className="mt-4 text-chip text-muted">
                    {t('styleguide.tokens.motion_note')}
                </p>
            </Group>
        </StyleguideSection>
    );
}
