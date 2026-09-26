import type { ReactNode } from 'react';
import { Bell, Search, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import { Kbd } from '@/components/ui/kbd';
import { LiveDot } from '@/components/ui/live-dot';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { StyleguideSection } from './styleguide-section';

const VARIANTS = [
    'primary-ink',
    'secondary-tile',
    'ghost',
    'ghost-on-dark',
    'on-accent-white',
] as const;

const SIZES = ['lg', 'md', 'sm'] as const;

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

const surfaceFor = (variant: (typeof VARIANTS)[number]) =>
    variant === 'ghost-on-dark'
        ? 'bg-dark'
        : variant === 'on-accent-white'
          ? 'bg-hero'
          : 'bg-card';

export function ActionsSection() {
    const { t } = useT();

    return (
        <StyleguideSection id="actions" title={t('styleguide.actions.title')}>
            {VARIANTS.map((variant) => (
                <div key={variant} className="flex flex-col gap-3">
                    <h3 className="text-label-sm text-muted">{variant}</h3>
                    <div
                        className={cn(
                            'flex flex-col gap-4 rounded-card-sm p-card-sm',
                            surfaceFor(variant),
                        )}
                    >
                        {SIZES.map((size) => (
                            <div
                                key={size}
                                className="flex flex-wrap items-center gap-4"
                            >
                                <Button variant={variant} size={size}>
                                    {t('styleguide.actions.state_default')}
                                </Button>
                                <Button
                                    variant={variant}
                                    size={size}
                                    iconLeft={Send}
                                    iconRight={Search}
                                >
                                    {t('styleguide.actions.with_icons')}
                                </Button>
                                <Button variant={variant} size={size} disabled>
                                    {t('styleguide.actions.state_disabled')}
                                </Button>
                                <Button variant={variant} size={size} loading>
                                    {t('styleguide.actions.state_loading')}
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            ))}

            <Group title={t('styleguide.actions.full_width')}>
                <Button fullWidth iconLeft={Send}>
                    {t('styleguide.actions.full_width')}
                </Button>
            </Group>

            <Group title={t('styleguide.actions.link')}>
                <Button href="#" variant="secondary-tile">
                    {t('styleguide.actions.link')}
                </Button>
                <Button href="#" disabled>
                    {t('styleguide.actions.state_disabled')}
                </Button>
            </Group>

            <Group title={t('styleguide.actions.icon_buttons')}>
                <IconButton
                    icon={Bell}
                    label={t('styleguide.actions.notifications')}
                    size={60}
                    bg="white"
                    dot
                />
                <IconButton
                    icon={Bell}
                    label={t('styleguide.actions.notifications')}
                    size={60}
                    bg="tile"
                />
                <IconButton
                    icon={Search}
                    label={t('styleguide.actions.search')}
                    size={46}
                    bg="tile"
                    dot
                />
                <IconButton
                    icon={Search}
                    label={t('styleguide.actions.search')}
                    size={46}
                    bg="white"
                />
                <IconButton
                    icon={Search}
                    label={t('styleguide.actions.search')}
                    size={46}
                    disabled
                />
            </Group>

            <Group title={t('styleguide.actions.icon_buttons')} dark>
                <IconButton
                    icon={Bell}
                    label={t('styleguide.actions.notifications')}
                    size={60}
                    bg="dark"
                />
                <IconButton
                    icon={Search}
                    label={t('styleguide.actions.search')}
                    size={46}
                    bg="dark"
                />
            </Group>

            <Group title={t('styleguide.actions.live_dot')}>
                <LiveDot />
            </Group>

            <Group title={t('styleguide.actions.spinner')}>
                <Spinner size="sm" />
                <Spinner size="md" />
                <Spinner tone="accent" />
                <span className="inline-flex rounded-full bg-ink p-2">
                    <Spinner tone="white" />
                </span>
            </Group>

            <Group title={t('styleguide.actions.kbd')}>
                <Kbd>⌘</Kbd>
                <Kbd>K</Kbd>
            </Group>
        </StyleguideSection>
    );
}
