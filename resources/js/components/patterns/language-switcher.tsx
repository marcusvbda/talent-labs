import { usePage } from '@inertiajs/react';
import { Check, Globe } from 'lucide-react';
import { Menu } from '@/components/ui/menu';
import type { MenuEntry } from '@/components/ui/menu';
import { useSetLocale } from '@/data/hooks/use-set-locale';
import { useT } from '@/i18n/i18n-provider';
import type { SharedProps } from '@/types/shared';

/** Locale options as menu entries; the current one carries a check icon. */
export function useLocaleEntries(prefix = ''): MenuEntry[] {
    const { t } = useT();
    const { locale, locales } = usePage<SharedProps>().props;
    const setLocale = useSetLocale();

    return locales.map((key) => ({
        label: `${prefix}${t(`locale.${key}`)}`,
        icon: key === locale ? Check : undefined,
        onSelect: () => setLocale.mutate(key),
    }));
}

export function LanguageSwitcher() {
    const { t } = useT();
    const { locale } = usePage<SharedProps>().props;
    const entries = useLocaleEntries();

    return (
        <Menu
            items={entries}
            trigger={
                <button
                    type="button"
                    aria-label={t('language_switcher.label')}
                    className="inline-flex h-control-sm items-center gap-2 rounded-full bg-tile px-4 text-label-sm text-ink transition-colors hover:bg-hairline focus-visible:focus-ring"
                >
                    <Globe aria-hidden="true" size={20} strokeWidth={1.8} />
                    {locale.toUpperCase()}
                </button>
            }
        />
    );
}
