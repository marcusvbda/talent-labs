import type { Locale } from '@/types/shared';
import { intlLocale } from './locale';

export type Dictionary = Record<string, string>;
export type Params = Record<string, string | number>;

const replace = (message: string, params?: Params): string => {
    if (!params) {
        return message;
    }

    return message.replace(/:(\w+)/g, (match, name: string) =>
        Object.prototype.hasOwnProperty.call(params, name)
            ? String(params[name])
            : match,
    );
};

export const translate = (
    dict: Dictionary,
    key: string,
    params?: Params,
): string => {
    const message = dict[key];

    if (message === undefined) {
        if (import.meta.env.DEV) {
            console.warn(`[i18n] Missing translation key: "${key}"`);
        }

        return key;
    }

    return replace(message, params);
};

export const pluralize = (
    dict: Dictionary,
    locale: Locale,
    key: string,
    count: number,
    params?: Params,
): string => {
    const category =
        new Intl.PluralRules(intlLocale(locale)).select(count) === 'one'
            ? 'one'
            : 'other';

    return translate(dict, `${key}.${category}`, { ...params, count });
};
