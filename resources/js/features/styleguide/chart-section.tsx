import { BarChart } from '@/components/patterns/bar-chart';
import { Card } from '@/components/ui/card';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const SAMPLE = [34, 41, 50, 46, 0, 0, 50, 44, 50, 38, 49, 0, 0, 18].map(
    (value, index, all) => ({
        label: String(15 + index),
        value,
        isToday: index === all.length - 1,
    }),
);

export function ChartSection() {
    const { t } = useT();

    return (
        <StyleguideSection id="chart" title={t('styleguide.chart.title')}>
            <div className="grid gap-gap md:grid-cols-2 desk:grid-cols-3">
                <div className="flex flex-col gap-3 md:col-span-2 desk:col-span-1">
                    <h3 className="text-label-sm text-muted">
                        {t('styleguide.chart.default')}
                    </h3>
                    <Card tone="light">
                        <BarChart
                            title={t('styleguide.chart.sample_title')}
                            data={SAMPLE}
                        />
                    </Card>
                </div>
                <div className="flex flex-col gap-3">
                    <h3 className="text-label-sm text-muted">
                        {t('styleguide.chart.loading')}
                    </h3>
                    <Card tone="light">
                        <BarChart
                            title={t('styleguide.chart.sample_title')}
                            data={SAMPLE}
                            loading
                        />
                    </Card>
                </div>
                <div className="flex flex-col gap-3">
                    <h3 className="text-label-sm text-muted">
                        {t('styleguide.chart.empty')}
                    </h3>
                    <Card tone="light">
                        <BarChart
                            title={t('styleguide.chart.sample_title')}
                            data={[]}
                            empty
                        />
                    </Card>
                </div>
            </div>
        </StyleguideSection>
    );
}
