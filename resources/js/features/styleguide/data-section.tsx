import { useQuery } from '@tanstack/react-query';
import { Inbox } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';
import { Skeleton } from '@/components/ui/skeleton';
import { fixtureCall } from '@/data/fixtures/runtime';
import type { DevState } from '@/data/fixtures/dev-state';
import { setDevState, useDevState } from '@/data/fixtures/dev-state';
import { useFixtures } from '@/data/source';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

// Demo-only key; real keys live in data/keys.ts.
const DEMO_KEY = ['styleguide', 'data-demo'] as const;

const DEMO_ITEMS = ['alpha', 'beta', 'gamma'];

const STATES: DevState['state'][] = ['normal', 'loading', 'empty', 'error'];

const DataDemo = () => {
    const { t } = useT();
    const dev = useDevState();
    const query = useQuery({
        queryKey: DEMO_KEY,
        queryFn: () => fixtureCall(() => DEMO_ITEMS, { empty: () => [] }),
        retry: false,
    });

    let body;

    if (query.isPending) {
        body = (
            <div className="flex flex-col gap-3">
                <Skeleton shape="line" />
                <Skeleton shape="line" />
                <Skeleton shape="block" />
            </div>
        );
    } else if (query.isError) {
        body = <ErrorState onRetry={() => void query.refetch()} />;
    } else if (query.data.length === 0) {
        body = (
            <EmptyState
                icon={Inbox}
                title={t('styleguide.data.empty_title')}
                description={t('styleguide.data.empty_description')}
            />
        );
    } else {
        body = (
            <ul className="flex flex-col gap-2 text-body text-ink">
                {query.data.map((item) => (
                    <li key={item} className="rounded-tile bg-tile px-4 py-2">
                        {item}
                    </li>
                ))}
            </ul>
        );
    }

    return (
        <div className="flex flex-col gap-6 rounded-card-sm bg-card p-card-sm">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-label-sm text-muted">
                    {t('styleguide.data.set_state')}
                </span>
                {STATES.map((state) => (
                    <Button
                        key={state}
                        size="sm"
                        variant={
                            dev.state === state
                                ? 'primary-ink'
                                : 'secondary-tile'
                        }
                        onClick={() => setDevState({ state })}
                    >
                        {t(`styleguide.data.state_${state}`)}
                    </Button>
                ))}
            </div>
            {body}
        </div>
    );
};

export function DataSection() {
    const { t } = useT();

    return (
        <StyleguideSection id="data" title={t('styleguide.data.title')}>
            {useFixtures ? (
                <DataDemo />
            ) : (
                <p className="text-body text-muted">
                    {t('styleguide.data.fixtures_only')}
                </p>
            )}
        </StyleguideSection>
    );
}
