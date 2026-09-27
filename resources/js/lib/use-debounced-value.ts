import { useEffect, useState } from 'react';

// Trails `value` by `delayMs`; one timeout, restarted on every change.
export function useDebouncedValue<T>(value: T, delayMs: number): T {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(value), delayMs);

        return () => clearTimeout(timer);
    }, [value, delayMs]);

    return debounced;
}
