import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Field } from '@/components/ui/field';
import { Segmented } from '@/components/ui/segmented';
import { Select } from '@/components/ui/select';
import { toast } from '@/components/ui/toast';
import { useSaveOnboardingBasics } from '@/data/hooks/use-onboarding';
import { StepFooter } from '@/features/onboarding/step-footer';
import { useT } from '@/i18n/i18n-provider';
import type { Locale } from '@/types/contracts';

const LOCALES: Locale[] = ['en', 'pt'];
const COUNTRIES = [
    'AR',
    'AU',
    'AT',
    'BE',
    'BR',
    'CA',
    'CL',
    'CO',
    'CR',
    'CZ',
    'DK',
    'DO',
    'EC',
    'ES',
    'FI',
    'FR',
    'DE',
    'GR',
    'GT',
    'IE',
    'IN',
    'IT',
    'MX',
    'NL',
    'NZ',
    'NO',
    'PA',
    'PE',
    'PL',
    'PT',
    'RO',
    'SG',
    'ZA',
    'SE',
    'CH',
    'GB',
    'US',
    'UY',
    'VE',
];

const browserTimezone = () => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return 'UTC';
    }
};

const browserCountry = (): string | null => {
    const region = navigator.language.split('-')[1]?.toUpperCase();

    return region && COUNTRIES.includes(region) ? region : null;
};

export const StepBasics = ({ onDone }: { onDone: () => void }) => {
    const { t, locale } = useT();
    const save = useSaveOnboardingBasics();
    const [country, setCountry] = useState<string | null>(browserCountry);
    const [language, setLanguage] = useState<Locale>(locale);
    const [timezone, setTimezone] = useState<string>(browserTimezone);

    const names = new Intl.DisplayNames([locale], { type: 'region' });
    const countryOptions = COUNTRIES.map((code) => ({
        value: code,
        label: names.of(code) ?? code,
    })).sort((a, b) => a.label.localeCompare(b.label, locale));
    const zones = Intl.supportedValuesOf('timeZone');
    const timezoneOptions = (
        zones.includes(timezone) ? zones : [timezone, ...zones]
    ).map((zone) => ({ value: zone, label: zone.replaceAll('_', ' ') }));

    const onContinue = () => {
        if (!country) {
            return;
        }

        save.mutate(
            { country, locale: language, timezone },
            {
                onSuccess: onDone,
                onError: () => toast.error(t('onboarding.basics.save_failed')),
            },
        );
    };

    return (
        <DataCard
            title={t('onboarding.basics.title')}
            subtitle={t('onboarding.basics.subtitle')}
        >
            <div className="flex flex-col gap-6">
                <Field label={t('onboarding.basics.country')}>
                    {(control) => (
                        <Select
                            {...control}
                            value={country}
                            onChange={setCountry}
                            options={countryOptions}
                            placeholder={t(
                                'onboarding.basics.country_placeholder',
                            )}
                        />
                    )}
                </Field>
                <div className="flex flex-col gap-2">
                    <span className="text-label-sm text-ink">
                        {t('onboarding.basics.language')}
                    </span>
                    <Segmented
                        value={language}
                        onChange={setLanguage}
                        ariaLabel={t('onboarding.basics.language')}
                        options={LOCALES.map((value) => ({
                            value,
                            label: t(`locale.${value}`),
                        }))}
                    />
                </div>
                <Field label={t('onboarding.basics.timezone')}>
                    {(control) => (
                        <Select
                            {...control}
                            value={timezone}
                            onChange={setTimezone}
                            options={timezoneOptions}
                        />
                    )}
                </Field>
                <StepFooter
                    onContinue={onContinue}
                    disabled={!country}
                    loading={save.isPending}
                />
            </div>
        </DataCard>
    );
};
