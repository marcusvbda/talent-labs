import type { Locale } from '@/types/shared';

const INTL_LOCALES: Record<Locale, string> = {
    en: 'en-US',
    pt: 'pt-BR',
    es: 'es-ES',
};

export const intlLocale = (locale: Locale): string =>
    INTL_LOCALES[locale] ?? INTL_LOCALES.en;
