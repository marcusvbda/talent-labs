import type { LucideIcon } from 'lucide-react';
import { Chip } from '@/components/ui/chip';
import { Skeleton } from '@/components/ui/skeleton';
import { StatusDisc } from '@/components/ui/status-disc';
import { TickMeter } from '@/components/ui/tick-meter';
import { useT } from '@/i18n/i18n-provider';

type DiscTint = 'orange' | 'neutral' | 'green' | 'red';

export function StatTile({
    label,
    icon,
    tint,
    value,
    unit,
    delta,
    context,
    meter,
    loading = false,
}: {
    label: string;
    icon: LucideIcon;
    tint: DiscTint;
    value: string;
    unit?: string;
    delta?: { direction: 'up' | 'down'; label: string; context?: string };
    context?: string;
    meter?: { total: number; filled: number };
    loading?: boolean;
}) {
    const { t } = useT();

    return (
        <div className="flex flex-col gap-4 rounded-tile bg-tile px-tile-x pt-tile-t pb-tile-b">
            <div className="flex items-start justify-between gap-3">
                <span className="text-label-sm text-muted">{label}</span>
                <StatusDisc status="icon" icon={icon} tint={tint} size="lg" />
            </div>
            {loading ? (
                <div
                    className="flex flex-col gap-3"
                    role="status"
                    aria-busy="true"
                    aria-label={t('common.loading')}
                >
                    <Skeleton shape="line" className="h-10 w-2/3" />
                    <Skeleton shape="line" className="w-1/2" />
                </div>
            ) : (
                <>
                    <div className="mt-auto flex items-baseline gap-1.5">
                        <span className="text-numeral-lg-sm md:text-numeral-lg">
                            {value}
                        </span>
                        {unit && (
                            <span className="text-label text-muted">
                                {unit}
                            </span>
                        )}
                    </div>
                    {meter && (
                        <TickMeter
                            total={meter.total}
                            filled={meter.filled}
                            label={label}
                        />
                    )}
                    {delta ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <Chip variant={`delta-${delta.direction}`}>
                                {delta.label}
                            </Chip>
                            {delta.context && (
                                <span className="text-label-sm text-muted">
                                    {delta.context}
                                </span>
                            )}
                        </div>
                    ) : (
                        context && (
                            <p className="text-label-sm text-muted">
                                {context}
                            </p>
                        )
                    )}
                </>
            )}
        </div>
    );
}
