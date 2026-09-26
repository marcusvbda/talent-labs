import { useT } from '@/i18n/i18n-provider';
import { intlLocale } from '@/i18n/locale';
import type { Locale } from '@/types/shared';

export { intlLocale };

type DateInput = Date | string | number;

const toDate = (value: DateInput): Date =>
    value instanceof Date ? value : new Date(value);

export const formatNumber = (
    locale: Locale,
    value: number,
    opts?: Intl.NumberFormatOptions,
): string => new Intl.NumberFormat(intlLocale(locale), opts).format(value);

export const formatCurrency = (
    locale: Locale,
    value: number,
    currency: string,
): string =>
    new Intl.NumberFormat(intlLocale(locale), {
        style: 'currency',
        currency,
    }).format(value);

export const formatDate = (
    locale: Locale,
    value: DateInput,
    opts: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string =>
    new Intl.DateTimeFormat(intlLocale(locale), opts).format(toDate(value));

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['week', 604_800],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
    ['second', 1],
];

export const formatRelativeTime = (
    locale: Locale,
    value: DateInput,
    now: DateInput = Date.now(),
): string => {
    const seconds = Math.round(
        (toDate(value).getTime() - toDate(now).getTime()) / 1000,
    );
    const [unit, size] =
        RELATIVE_UNITS.find(([, size]) => Math.abs(seconds) >= size) ??
        RELATIVE_UNITS[RELATIVE_UNITS.length - 1];

    return new Intl.RelativeTimeFormat(intlLocale(locale), {
        numeric: 'auto',
    }).format(Math.trunc(seconds / size), unit);
};

export const formatList = (locale: Locale, items: string[]): string =>
    new Intl.ListFormat(intlLocale(locale), {
        style: 'long',
        type: 'conjunction',
    }).format(items);

export function useFormat() {
    const { locale } = useT();

    return {
        number: (value: number, opts?: Intl.NumberFormatOptions) =>
            formatNumber(locale, value, opts),
        currency: (value: number, currency: string) =>
            formatCurrency(locale, value, currency),
        date: (value: DateInput, opts?: Intl.DateTimeFormatOptions) =>
            formatDate(locale, value, opts),
        relativeTime: (value: DateInput) => formatRelativeTime(locale, value),
        list: (items: string[]) => formatList(locale, items),
    };
}
