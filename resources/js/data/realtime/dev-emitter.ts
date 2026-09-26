import { useSyncExternalStore } from 'react';

type Handler = (event: string, payload: unknown) => void;
type SimulationName = 'send' | 'newJobs' | 'failure';

const handlers = new Set<Handler>();

export const devEmitter = {
    emit(event: string, payload: unknown): void {
        handlers.forEach((handler) => handler(event, payload));
    },
    subscribe(handler: Handler): () => void {
        handlers.add(handler);

        return () => {
            handlers.delete(handler);
        };
    },
};

const simulations = new Map<SimulationName, () => void>();
const simulationListeners = new Set<() => void>();
// Replaced (never mutated) so useSyncExternalStore sees a stable snapshot.
let registered: SimulationName[] = [];

export function registerSimulation(
    name: SimulationName,
    run: () => void,
): void {
    simulations.set(name, run);
    registered = [...simulations.keys()];
    simulationListeners.forEach((listener) => listener());
}

export function runSimulation(name: SimulationName): void {
    simulations.get(name)?.();
}

const subscribeSimulations = (listener: () => void) => {
    simulationListeners.add(listener);

    return () => {
        simulationListeners.delete(listener);
    };
};

export const useSimulations = (): SimulationName[] =>
    useSyncExternalStore(
        subscribeSimulations,
        () => registered,
        () => registered,
    );
