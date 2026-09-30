import { createContext, useContext, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { useDemoLoop } from './use-demo-loop';
import type { DemoLoopState } from './use-demo-loop';

type DemoLoopContextValue = {
    state: DemoLoopState;
    /** Adds (true) or removes (false) one in-view demo surface. */
    register: (inView: boolean) => void;
};

const DemoLoopContext = createContext<DemoLoopContextValue | null>(null);

/** Owns the single loop; it runs while at least one demo surface is in view. */
export function DemoLoopProvider({ children }: { children: ReactNode }) {
    const [visible, setVisible] = useState(0);
    const state = useDemoLoop({ running: visible > 0 });
    const register = (inView: boolean) =>
        setVisible((count) => Math.max(0, count + (inView ? 1 : -1)));

    return (
        <DemoLoopContext.Provider value={{ state, register }}>
            {children}
        </DemoLoopContext.Provider>
    );
}

export function useDemoLoopContext(): DemoLoopContextValue {
    const context = useContext(DemoLoopContext);

    if (!context) {
        throw new Error('useDemoLoop must be used inside DemoLoopProvider.');
    }

    return context;
}

/** The shared loop state. */
export const useDemoLoopState = (): DemoLoopState => useDemoLoopContext().state;

/** Registers a demo surface while `inView` is true. */
export function useRegisterDemoInView(inView: boolean): void {
    const { register } = useDemoLoopContext();

    useEffect(() => {
        if (!inView) {
            return;
        }

        register(true);

        return () => register(false);
        // `register` only closes over the state setter.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [inView]);
}
