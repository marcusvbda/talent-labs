export function createFixtureStore<S>(initial: S) {
    let state = initial;

    return {
        get: (): S => state,
        set: (updater: (s: S) => S): void => {
            state = updater(state);
        },
        reset: (): void => {
            state = initial;
        },
    };
}
