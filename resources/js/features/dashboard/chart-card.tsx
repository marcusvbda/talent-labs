import { useState } from 'react';
import { BarChart } from '@/components/patterns/bar-chart';
import { DataCard } from '@/components/patterns/data-card';
import { Segmented } from '@/components/ui/segmented';
import { useChart } from '@/data/hooks/use-chart';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type { ChartData } from '@/types/contracts';

type ChartRange = ChartData['range'];

export function ChartCard() {
    const { t } = useT();
    const format = useFormat();
    const [range, setRange] = useState<ChartRange>('14d');
    const chart = useChart(range);
    const data = chart.data;
    const days = range === '14d' ? 14 : 30;

    return (
        <DataCard
            title={t('dashboard.chart.title')}
            subtitle={t('dashboard.chart.subtitle', { days })}
            state={chart.isError ? 'error' : 'ready'}
            onRetry={() => void chart.refetch()}
            actions={
                <Segmented<ChartRange>
                    value={range}
                    onChange={setRange}
                    ariaLabel={t('dashboard.chart.range')}
                    options={[
                        { value: '14d', label: t('dashboard.chart.range_14') },
                        { value: '30d', label: t('dashboard.chart.range_30') },
                    ]}
                />
            }
            className="flex flex-col"
        >
            <p className="mb-4 flex items-baseline gap-2.5">
                <span className="text-numeral-lg-sm md:text-numeral-lg">
                    {format.number(data?.averagePerActiveDay ?? 0, {
                        maximumFractionDigits: 1,
                    })}
                </span>
                <span className="text-body text-muted">
                    {t('dashboard.chart.average')}
                </span>
            </p>
            <BarChart
                loading={!data}
                data={(data?.days ?? []).map((day, index, all) => ({
                    label: String(Number(day.date.slice(8, 10))),
                    value: day.count,
                    isToday: index === all.length - 1,
                }))}
                limit={data?.limit}
                title={t('dashboard.chart.title')}
            />
            <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 pt-4 text-label-sm text-muted">
                <span className="flex items-center gap-4">
                    <span className="flex items-center gap-2">
                        <i className="size-2.5 rounded-full bg-bar-cap" />
                        {t('dashboard.chart.legend_sent')}
                    </span>
                    <span className="flex items-center gap-2">
                        <i className="size-2.5 rounded-full bg-accent" />
                        {t('dashboard.chart.legend_today')}
                    </span>
                </span>
                {data && (
                    <span>
                        {t('dashboard.chart.limit', {
                            limit: format.number(data.limit),
                        })}
                    </span>
                )}
            </div>
        </DataCard>
    );
}
