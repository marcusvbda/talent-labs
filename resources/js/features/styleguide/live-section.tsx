import { useState } from 'react';
import { ActivityRow } from '@/components/patterns/activity-row';
import type { ActivityStatus } from '@/components/patterns/activity-row';
import { CountdownBar } from '@/components/patterns/countdown-bar';
import { LiveStepper } from '@/components/patterns/live-stepper';
import { Card } from '@/components/ui/card';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const STATUSES: ActivityStatus[] = ['sending', 'done', 'failed', 'waiting'];
const STEP_KEYS = ['one', 'two', 'three', 'four', 'five'] as const;

export function LiveSection() {
    const { t } = useT();
    const steps = STEP_KEYS.map((key) => ({
        key,
        label: t(`styleguide.demo.step_${key}`),
    }));
    const [times] = useState(() => {
        const now = Date.now();

        return {
            startsAt: new Date(now - 18_000).toISOString(),
            endsAt: new Date(now + 42_000).toISOString(),
            past: new Date(now - 5 * 60_000).toISOString(),
        };
    });

    return (
        <StyleguideSection id="live" title={t('styleguide.live.title')}>
            <div className="flex flex-col gap-3">
                <h3 className="text-label-sm text-muted">
                    {t('styleguide.live.stepper')}
                </h3>
                <div className="flex flex-col gap-3">
                    {steps.map((_, index) => (
                        <Card key={index} tone="dark">
                            <LiveStepper
                                tone="dark"
                                steps={steps}
                                activeIndex={index}
                                subStep={t('styleguide.demo.sub_step')}
                            />
                        </Card>
                    ))}
                    <Card tone="dark">
                        <LiveStepper
                            tone="dark"
                            steps={steps}
                            activeIndex={2}
                            failedIndex={2}
                        />
                    </Card>
                </div>
            </div>
            <div className="flex flex-col gap-3">
                <h3 className="text-label-sm text-muted">
                    {t('styleguide.live.countdown')}
                </h3>
                <Card tone="dark">
                    <CountdownBar
                        startsAt={times.startsAt}
                        endsAt={times.endsAt}
                    />
                </Card>
            </div>
            <div className="flex flex-col gap-3">
                <h3 className="text-label-sm text-muted">
                    {t('styleguide.live.activity')}
                </h3>
                <Card tone="light">
                    <div className="flex flex-col gap-4">
                        {STATUSES.map((status) => (
                            <ActivityRow
                                key={status}
                                status={status}
                                title={t('styleguide.demo.activity_title')}
                                subtitle={t(`status.${status}`)}
                                time={times.past}
                            />
                        ))}
                    </div>
                </Card>
            </div>
        </StyleguideSection>
    );
}
