import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { initialDataFrom } from '@/data/define-query';
import { endpoints } from '@/data/endpoints';
import { buildDashboard } from '@/data/fixtures/handlers/dashboard';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { DashboardData, DashboardPeriod } from '@/types/contracts';

export function useDashboard(period: DashboardPeriod, initial?: DashboardData) {
    const real = () => {
        const e = endpoints.dashboard(period);

        return apiFetch<DashboardData>(e.url, { method: e.method });
    };
    const fixture = () =>
        fixtureCall(() => buildDashboard(period), {
            empty: () => buildDashboard(period, true),
        });

    return useQuery({
        queryKey: keys.dashboard(period),
        queryFn: fromSource({ real, fixture }),
        ...initialDataFrom(initial),
    });
}
