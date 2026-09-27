import type { ReactNode } from 'react';
import { AppGrid } from '@/layouts/app-layout';

const Cell = ({ span, children }: { span: string; children: ReactNode }) => (
    <div className={`flex min-w-0 flex-col *:flex-1 ${span}`}>{children}</div>
);

/** Dashboard layout: hero + KPI, live + chart, matches + activity. */
export function DashboardGrid({
    hero,
    kpi,
    setup,
    live,
    chart,
    matches,
    activity,
}: {
    hero: ReactNode;
    kpi: ReactNode;
    /** When set, replaces the hero + kpi row with a single full-width cell. */
    setup?: ReactNode;
    live: ReactNode;
    chart: ReactNode;
    matches: ReactNode;
    activity: ReactNode;
}) {
    return (
        <AppGrid>
            {setup ? (
                <Cell span="md:col-span-2 lg:col-span-12">{setup}</Cell>
            ) : (
                <>
                    <Cell span="md:col-span-2 lg:col-span-5">{hero}</Cell>
                    <Cell span="md:col-span-2 lg:col-span-7">{kpi}</Cell>
                </>
            )}
            <Cell span="lg:col-span-7">{live}</Cell>
            <Cell span="lg:col-span-5">{chart}</Cell>
            <Cell span="lg:col-span-7">{matches}</Cell>
            <Cell span="lg:col-span-5">{activity}</Cell>
        </AppGrid>
    );
}
