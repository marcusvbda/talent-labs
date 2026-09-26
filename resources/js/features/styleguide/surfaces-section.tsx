import type { ReactNode } from 'react';
import { Inbox } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const Group = ({ title, children }: { title: string; children: ReactNode }) => (
    <div className="flex flex-col gap-3">
        <h3 className="text-label-sm text-muted">{title}</h3>
        {children}
    </div>
);

export function SurfacesSection() {
    const { t } = useT();

    return (
        <StyleguideSection id="surfaces" title={t('styleguide.surfaces.title')}>
            <Group title={t('styleguide.surfaces.cards')}>
                <div className="grid gap-gap md:grid-cols-3">
                    <Card tone="light">
                        <p className="text-body">
                            {t('styleguide.surfaces.tone_light')}
                        </p>
                    </Card>
                    <Card tone="dark">
                        <p className="text-body">
                            {t('styleguide.surfaces.tone_dark')}
                        </p>
                    </Card>
                    <Card tone="hero-accent">
                        <p className="text-body">
                            {t('styleguide.surfaces.tone_hero')}
                        </p>
                    </Card>
                </div>
            </Group>
            <Group title={t('styleguide.surfaces.skeletons')}>
                <Card tone="light" className="flex flex-col gap-4">
                    <div className="flex items-center gap-4">
                        <Skeleton shape="circle" />
                        <div className="flex flex-1 flex-col gap-2">
                            <Skeleton shape="line" />
                            <Skeleton shape="line" className="w-2/3" />
                        </div>
                    </div>
                    <Skeleton shape="block" />
                </Card>
            </Group>
            <Group title={t('styleguide.surfaces.empty')}>
                <Card tone="light">
                    <EmptyState
                        icon={Inbox}
                        title={t('states.empty.title')}
                        description={t('styleguide.surfaces.empty_description')}
                    />
                </Card>
            </Group>
            <Group title={t('styleguide.surfaces.error_retry')}>
                <Card tone="light">
                    <ErrorState onRetry={() => undefined} />
                </Card>
            </Group>
            <Group title={t('styleguide.surfaces.error_plain')}>
                <Card tone="light">
                    <ErrorState />
                </Card>
            </Group>
        </StyleguideSection>
    );
}
