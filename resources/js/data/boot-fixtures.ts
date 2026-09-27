import { useFixtures } from '@/data/source';

// Starts the simulated sender. Kept in data/ so app.tsx never touches the
// fixtures flag; the engine chunk is only reachable behind it.
export function bootFixtures(): void {
    if (useFixtures) {
        void import('@/data/fixtures/engine');
    }
}
