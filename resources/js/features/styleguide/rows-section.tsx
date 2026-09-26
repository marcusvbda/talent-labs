import { useState } from 'react';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { JobRow } from '@/components/patterns/job-row';
import { QueueRow } from '@/components/patterns/queue-row';
import { Card } from '@/components/ui/card';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const Group = ({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) => (
    <div className="flex flex-col gap-3">
        <h3 className="text-label font-medium">{title}</h3>
        {children}
    </div>
);

export function RowsSection() {
    const { t } = useT();
    const [first, setFirst] = useState(false);
    const [second, setSecond] = useState(true);
    const stack = ['React', 'TypeScript', 'Node.js'];
    const meta = t('styleguide.rows.meta');
    const companies = ['Acme Labs', 'Northwind', 'Globex', 'Initech', ''];

    return (
        <StyleguideSection id="rows" title={t('styleguide.rows.title')}>
            <Group title={t('styleguide.rows.logos')}>
                <div className="flex flex-wrap items-center gap-3">
                    {companies.map((name) => (
                        <CompanyLogo key={name} name={name} />
                    ))}
                    <CompanyLogo name="Acme Labs" size="sm" />
                    <CompanyLogo name="Acme Labs" size="lg" />
                </div>
            </Group>
            <Group title={t('styleguide.rows.queue')}>
                <Card tone="dark" className="flex flex-col gap-row-gap">
                    <QueueRow
                        company="Acme Labs"
                        title={t('styleguide.rows.title_one')}
                        meta={meta}
                        language="en"
                        eta="0:42"
                    />
                    <QueueRow
                        company="Northwind"
                        title={t('styleguide.rows.title_two')}
                        meta={meta}
                        language="pt"
                        eta="1:30"
                    />
                </Card>
            </Group>
            <Group title={t('styleguide.rows.jobs')}>
                <div className="flex flex-col gap-row-gap">
                    <JobRow
                        selected={first}
                        onSelectedChange={setFirst}
                        company="Acme Labs"
                        title={t('styleguide.rows.title_one')}
                        meta={meta}
                        stack={stack}
                        language="en"
                    />
                    <JobRow
                        selected={second}
                        onSelectedChange={setSecond}
                        company="Northwind"
                        title={t('styleguide.rows.title_two')}
                        meta={meta}
                        stack={stack}
                        language="pt"
                    />
                    <JobRow
                        selected={false}
                        onSelectedChange={() => undefined}
                        company="Globex"
                        title={t('styleguide.rows.title_three')}
                        meta={meta}
                        stack={stack}
                        language="es"
                        disabled
                    />
                </div>
            </Group>
        </StyleguideSection>
    );
}
