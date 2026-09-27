import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import { endpoints } from '@/data/endpoints';
import { buildChart } from '@/data/fixtures/handlers/chart';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { ChartData } from '@/types/contracts';

export function useChart(range: ChartData['range'], initial?: ChartData) {
    const real = () => {
        const e = endpoints.chart(range);

        return apiFetch<ChartData>(e.url, { method: e.method });
    };
    const fixture = () =>
        fixtureCall(() => buildChart(range), {
            empty: () => buildChart(range, true),
        });

    return useQuery({
        queryKey: keys.chart(range),
        queryFn: fromSource({ real, fixture }),
        ...initialDataFrom(initial),
    });
}
