type Handler = (event: string, payload: unknown) => void;

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
