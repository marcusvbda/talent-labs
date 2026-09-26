import { router } from '@inertiajs/react';
import type { Page } from '@inertiajs/core';
import { createContext, useContext, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import type { Locale, SharedProps } from '@/types/shared';
import { pluralize, translate } from './translate';
import type { Dictionary, Params } from './translate';

type I18nState = {
    locale: Locale;
    locales: Locale[];
    translations: Dictionary;
};

type I18nContextValue = {
    t: (key: string, params?: Params) => string;
    plural: (key: string, count: number, params?: Params) => string;
    locale: Locale;
    locales: Locale[];
};

const I18nContext = createContext<I18nContextValue | null>(null);

const stateFrom = (props: Partial<SharedProps>): I18nState => ({
    locale: props.locale ?? 'en',
    locales: props.locales ?? ['en'],
    translations: props.translations ?? {},
});

export function I18nProvider({
    initialPage,
    children,
}: {
    initialPage: Page<SharedProps>;
    children: ReactNode;
}) {
    const [state, setState] = useState<I18nState>(() =>
        stateFrom(initialPage.props),
    );

    useEffect(() => {
        const update = (page: Page) =>
            setState(stateFrom(page.props as Partial<SharedProps>));

        const offNavigate = router.on('navigate', (event) =>
            update(event.detail.page),
        );
        const offSuccess = router.on('success', (event) =>
            update(event.detail.page),
        );

        return () => {
            offNavigate();
            offSuccess();
        };
    }, []);

    const value: I18nContextValue = {
        locale: state.locale,
        locales: state.locales,
        t: (key, params) => translate(state.translations, key, params),
        plural: (key, count, params) =>
            pluralize(state.translations, state.locale, key, count, params),
    };

    return <I18nContext value={value}>{children}</I18nContext>;
}

export function useT(): I18nContextValue {
    const context = useContext(I18nContext);

    if (!context) {
        throw new Error('useT must be used within an I18nProvider.');
    }

    return context;
}
