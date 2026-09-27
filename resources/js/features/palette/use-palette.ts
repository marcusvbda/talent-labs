import { useEffect, useSyncExternalStore } from 'react';

// Module-level store (mirrors toast.tsx): `open` must be readable/settable
// from both AppLayout (global shortcut) and TopBar (pill/icon clicks).
let open = false;
const listeners = new Set<() => void>();

const emit = () => {
    listeners.forEach((listener) => listener());
};

const subscribe = (listener: () => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

const getSnapshot = () => open;

const setOpen = (next: boolean) => {
    open = next;
    emit();
};

export function usePalette() {
    const isOpen = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

    return {
        open: isOpen,
        setOpen,
        close: () => setOpen(false),
    };
}

// Global ⌘K / Ctrl+K shortcut, mounted once in AppLayout. It only reacts to
// the modifier+K combo, so typing a bare "k" in a focused input is untouched.
export function usePaletteShortcut() {
    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            const modifier = event.metaKey || event.ctrlKey;

            if (!modifier || event.key.toLowerCase() !== 'k') {
                return;
            }

            event.preventDefault();
            setOpen(true);
        };

        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
    }, []);
}
