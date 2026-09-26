// Only files under data/ may import this flag; components must go through the
// data hooks, which pick real or fixture sources via fromSource().
export const useFixtures = import.meta.env.VITE_USE_FIXTURES === 'true';

export function fromSource<F>(pair: { real: F; fixture: F }): F {
    return useFixtures ? pair.fixture : pair.real;
}
