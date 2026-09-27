import { Send } from 'lucide-react';
import { PlanGate } from '@/components/patterns/plan-gate';
import { StickyActionBar } from '@/components/patterns/sticky-action-bar';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import type { SendMode } from '@/types/contracts';
import type { PlanKey } from '@/types/plans';

const SELECTION_PLANS: PlanKey[] = ['starter', 'pro'];

type SendBarProps = {
    mode: SendMode;
    count: number;
    left: number;
    overQuota: boolean;
    onClear: () => void;
    onSend: () => void;
    onReview: () => void;
};

const BarContent = ({
    mode,
    count,
    left,
    overQuota,
    onClear,
    onSend,
    onReview,
}: SendBarProps) => {
    const { t, plural } = useT();
    const blocked = count === 0 || overQuota;

    return (
        <>
            <div className="mr-auto flex min-w-0 flex-col gap-0.5">
                <p className="text-body text-muted">
                    <b className="font-medium text-ink">
                        {t('send.selected', { count })}
                    </b>{' '}
                    · {plural('send.left', left)}
                </p>
                {overQuota && (
                    <p role="alert" className="text-label-sm text-danger-text">
                        {plural('send.over_quota', left)}
                    </p>
                )}
            </div>
            <Button
                variant="secondary-tile"
                disabled={count === 0}
                onClick={onClear}
            >
                {t('send.clear')}
            </Button>
            <Button
                iconLeft={Send}
                disabled={blocked}
                onClick={mode === 'review' ? onReview : onSend}
            >
                {plural(mode === 'review' ? 'send.review' : 'send.send', count)}
            </Button>
        </>
    );
};

/** Selection bar for select / review; auto mode shows the plan gate instead. */
export function SendBar(props: SendBarProps) {
    if (props.mode === 'auto') {
        return (
            <PlanGate
                locked
                requiredPlans={SELECTION_PLANS}
                featureKey="jobs.gate"
            >
                <div className="flex items-center justify-end gap-3 py-6">
                    <BarContent {...props} count={0} overQuota={false} />
                </div>
            </PlanGate>
        );
    }

    return (
        <StickyActionBar>
            <BarContent {...props} />
        </StickyActionBar>
    );
}
