import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Segmented } from '@/components/ui/segmented';
import { Select } from '@/components/ui/select';
import { toast } from '@/components/ui/toast';
import { useSaveAccount } from '@/data/hooks/use-account';
import { useSetLocale } from '@/data/hooks/use-set-locale';
import { COUNTRIES } from '@/features/onboarding/step-basics';
import { useT } from '@/i18n/i18n-provider';
import type { Account, Locale } from '@/types/contracts';

const LOCALES: Locale[] = ['en', 'pt'];

const browserTimezone = () => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return 'UTC';
    }
};

export const LanguageRegionCard = ({
    account,
    className,
}: {
    account: Account;
    className?: string;
}) => {
    const { t } = useT();
    const save = useSaveAccount();
    const setLocale = useSetLocale();
    const [locale, setLocaleState] = useState<Locale>(account.locale);
    const [country, setCountry] = useState<string | null>(account.country);
    const [timezone, setTimezone] = useState<string>(
        account.timezone || browserTimezone(),
    );

    const names = new Intl.DisplayNames([locale], { type: 'region' });
    const countryOptions = COUNTRIES.map((code) => ({
        value: code,
        label: names.of(code) ?? code,
    })).sort((a, b) => a.label.localeCompare(b.label, locale));
    const zones = Intl.supportedValuesOf('timeZone');
    const timezoneOptions = (
        zones.includes(timezone) ? zones : [timezone, ...zones]
    ).map((zone) => ({ value: zone, label: zone.replaceAll('_', ' ') }));

    const onLocaleChange = (value: Locale) => {
        setLocaleState(value);
        setLocale.mutate(value);
    };

    const onSave = () => {
        save.mutate(
            { name: account.name, locale, timezone, country: country ?? '' },
            {
                onSuccess: () => toast.success(t('account.saved')),
                onError: () => toast.error(t('account.save_failed')),
            },
        );
    };

    return (
        <DataCard
            title={t('account.language_region.title')}
            className={className}
        >
            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <span className="text-label-sm text-ink">
                        {t('account.language_region.interface_language')}
                    </span>
                    <Segmented
                        value={locale}
                        onChange={onLocaleChange}
                        ariaLabel={t(
                            'account.language_region.interface_language',
                        )}
                        options={LOCALES.map((value) => ({
                            value,
                            label: t(`locale.${value}`),
                        }))}
                    />
                </div>
                <Field label={t('account.language_region.country')}>
                    {(control) => (
                        <Select
                            {...control}
                            value={country}
                            onChange={setCountry}
                            options={countryOptions}
                        />
                    )}
                </Field>
                <Field label={t('account.language_region.timezone')}>
                    {(control) => (
                        <Select
                            {...control}
                            value={timezone}
                            onChange={setTimezone}
                            options={timezoneOptions}
                        />
                    )}
                </Field>
                <div>
                    <Button loading={save.isPending} onClick={onSave}>
                        {t('account.save')}
                    </Button>
                </div>
            </div>
        </DataCard>
    );
};
