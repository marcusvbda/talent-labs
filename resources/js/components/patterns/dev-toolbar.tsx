import { Link } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover } from '@/components/ui/popover';
import { Segmented } from '@/components/ui/segmented';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tooltip } from '@/components/ui/tooltip';
import { setDevState, useDevState } from '@/data/fixtures/dev-state';
import type { DevState } from '@/data/fixtures/dev-state';
import { useSetLocale } from '@/data/hooks/use-set-locale';
import { runSimulation, useSimulations } from '@/data/realtime/dev-emitter';
import { useT } from '@/i18n/i18n-provider';
import { styleguide } from '@/routes/dev';
import { PLAN_KEYS } from '@/types/plans';
import type { Locale } from '@/types/shared';

const STATES: DevState['state'][] = ['normal', 'loading', 'empty', 'error'];
const LOCALES: Locale[] = ['en', 'pt', 'es'];
const GMAIL: DevState['gmail'][] = [
    'connected',
    'needs_reconnection',
    'disconnected',
];
const SIMULATIONS = [
    { name: 'send', label: 'dev_toolbar.simulate_send' },
    { name: 'newJobs', label: 'dev_toolbar.simulate_new_jobs' },
    { name: 'failure', label: 'dev_toolbar.simulate_failure' },
] as const;

const Row = ({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) => (
    <div className="flex flex-col gap-2">
        <span className="text-label-sm text-muted">{label}</span>
        {children}
    </div>
);

export default function DevToolbar() {
    const { t, locale } = useT();
    const dev = useDevState();
    const setLocale = useSetLocale();
    const registered = useSimulations();

    return (
        <div className="fixed bottom-4 left-4 z-30">
            <Popover
                className="flex w-80 flex-col gap-5"
                trigger={
                    <Button
                        size="sm"
                        variant="secondary-tile"
                        iconLeft={Wrench}
                    >
                        {t('dev_toolbar.title')}
                    </Button>
                }
            >
                <Row label={t('dev_toolbar.plan')}>
                    <Segmented
                        ariaLabel={t('dev_toolbar.plan')}
                        value={dev.plan}
                        onChange={(plan) => setDevState({ plan })}
                        options={PLAN_KEYS.map((key) => ({
                            value: key,
                            label: t(`plans.${key}.name`),
                        }))}
                    />
                </Row>
                <Row label={t('dev_toolbar.state')}>
                    <Segmented
                        ariaLabel={t('dev_toolbar.state')}
                        value={dev.state}
                        onChange={(state) => setDevState({ state })}
                        options={STATES.map((key) => ({
                            value: key,
                            label: t(`dev_toolbar.state.${key}`),
                        }))}
                    />
                </Row>
                <Row label={t('dev_toolbar.locale')}>
                    <Segmented
                        ariaLabel={t('dev_toolbar.locale')}
                        value={locale}
                        onChange={(next) => setLocale.mutate(next)}
                        disabled={setLocale.isPending}
                        options={LOCALES.map((key) => ({
                            value: key,
                            label: t(`locale.${key}`),
                        }))}
                    />
                </Row>
                <Row label={t('dev_toolbar.gmail')}>
                    <Select
                        aria-label={t('dev_toolbar.gmail')}
                        value={dev.gmail}
                        onChange={(gmail) => setDevState({ gmail })}
                        options={GMAIL.map((key) => ({
                            value: key,
                            label: t(`dev_toolbar.gmail.${key}`),
                        }))}
                    />
                </Row>
                <div className="flex items-center justify-between gap-3">
                    <span className="text-label-sm text-muted">
                        {t('dev_toolbar.onboarding')}
                    </span>
                    <Switch
                        aria-label={t('dev_toolbar.onboarding')}
                        checked={dev.onboardingComplete}
                        onChange={(onboardingComplete) =>
                            setDevState({ onboardingComplete })
                        }
                    />
                </div>
                <div className="flex flex-wrap gap-2">
                    {SIMULATIONS.map(({ name, label }) => {
                        const available = registered.includes(name);
                        const button = (
                            <Button
                                size="sm"
                                variant="secondary-tile"
                                disabled={!available}
                                onClick={() => runSimulation(name)}
                            >
                                {t(label)}
                            </Button>
                        );

                        return available ? (
                            <span key={name}>{button}</span>
                        ) : (
                            <Tooltip
                                key={name}
                                content={t('dev_toolbar.no_simulation')}
                            >
                                {button}
                            </Tooltip>
                        );
                    })}
                </div>
                <Link
                    href={styleguide().url}
                    className="text-label-sm text-accent-deep underline focus-visible:focus-ring"
                >
                    {t('dev_toolbar.styleguide')}
                </Link>
            </Popover>
        </div>
    );
}
