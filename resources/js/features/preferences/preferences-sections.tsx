import { DataCard } from '@/components/patterns/data-card';
import { Field } from '@/components/ui/field';
import { RadioGroup } from '@/components/ui/radio-group';
import { TagsInput } from '@/components/ui/tags-input';
import { usePreferenceOptions } from '@/data/hooks/use-preference-options';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import type { Preferences, RemoteMode, Seniority } from '@/types/contracts';

const REMOTE_MODES: RemoteMode[] = [
    'remote_only',
    'remote_or_locations',
    'locations_only',
];

const SUGGESTION_LIMIT = 10;

const CHIP =
    'inline-flex h-control-xs items-center rounded-full px-5 text-label-sm whitespace-nowrap transition-colors focus-visible:focus-ring';

export const showsLocations = (mode: RemoteMode) => mode !== 'remote_only';

// "Only these locations" is useless without at least one location.
export const hasMissingLocations = (value: Preferences) =>
    value.remoteMode === 'locations_only' && value.locations.length === 0;

const FIELDS: (keyof Preferences)[] = [
    'titles',
    'seniorities',
    'stack',
    'locations',
    'remoteMode',
    'excludeWords',
];

// Maps a raw 422 error bag (e.g. `{ "titles.0": [...] }`) to the first
// message for each `Preferences` field, so each field can show its own
// server-side validation error.
export const preferencesFieldErrors = (
    errors: Record<string, string[]> | undefined,
): Partial<Record<keyof Preferences, string>> => {
    if (!errors) {
        return {};
    }

    const result: Partial<Record<keyof Preferences, string>> = {};

    FIELDS.forEach((field) => {
        const key = Object.keys(errors).find(
            (candidate) =>
                candidate === field || candidate.startsWith(`${field}.`),
        );
        const message = key ? errors[key]?.[0] : undefined;

        if (message) {
            result[field] = message;
        }
    });

    return result;
};

export const PreferencesSections = ({
    value,
    onChange,
    errors,
}: {
    value: Preferences;
    onChange: (value: Preferences) => void;
    errors?: Partial<Record<keyof Preferences, string>>;
}) => {
    const { t } = useT();
    const { seniorities, stackSuggestions } = usePreferenceOptions();
    const set = <K extends keyof Preferences>(key: K, next: Preferences[K]) =>
        onChange({ ...value, [key]: next });

    const toggleSeniority = (level: Seniority) =>
        set(
            'seniorities',
            seniorities.filter((key) =>
                key === level
                    ? !value.seniorities.includes(key)
                    : value.seniorities.includes(key),
            ),
        );

    const suggestions = stackSuggestions
        .filter(
            (item) =>
                !value.stack.some(
                    (tag) => tag.toLowerCase() === item.toLowerCase(),
                ),
        )
        .slice(0, SUGGESTION_LIMIT);

    return (
        <>
            <DataCard title={t('preferences.roles.title')}>
                <Field
                    label={t('preferences.titles.label')}
                    hint={t('preferences.titles.help')}
                    error={errors?.titles}
                >
                    {(control) => (
                        <TagsInput
                            {...control}
                            value={value.titles}
                            onChange={(next) => set('titles', next)}
                            placeholder={t('preferences.titles.placeholder')}
                        />
                    )}
                </Field>
            </DataCard>
            <DataCard title={t('preferences.seniority.title')}>
                <div
                    role="group"
                    aria-label={t('preferences.seniority.title')}
                    className="flex flex-wrap gap-2"
                >
                    {seniorities.map((level) => {
                        const on = value.seniorities.includes(level);

                        return (
                            <button
                                key={level}
                                type="button"
                                aria-pressed={on}
                                onClick={() => toggleSeniority(level)}
                                className={cn(
                                    CHIP,
                                    on
                                        ? 'bg-ink text-white'
                                        : 'bg-tile text-muted hover:text-ink',
                                )}
                            >
                                {t(`preferences.seniority.${level}`)}
                            </button>
                        );
                    })}
                </div>
                <p className="mt-3 text-chip text-muted">
                    {t('preferences.seniority.note')}
                </p>
                {errors?.seniorities && (
                    <p role="alert" className="mt-1 text-chip text-danger-text">
                        {errors.seniorities}
                    </p>
                )}
            </DataCard>
            <DataCard title={t('preferences.stack.title')}>
                <Field
                    label={t('preferences.stack.label')}
                    error={errors?.stack}
                >
                    {(control) => (
                        <TagsInput
                            {...control}
                            value={value.stack}
                            onChange={(next) => set('stack', next)}
                            placeholder={t('preferences.stack.placeholder')}
                        />
                    )}
                </Field>
                {suggestions.length > 0 && (
                    <div className="mt-4 flex flex-col gap-2">
                        <p className="text-chip text-muted">
                            {t('preferences.stack.suggestions')}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {suggestions.map((item) => (
                                <button
                                    key={item}
                                    type="button"
                                    onClick={() =>
                                        set('stack', [...value.stack, item])
                                    }
                                    className="rounded-full bg-tile px-3 py-1 text-chip text-muted transition-colors hover:text-ink focus-visible:focus-ring"
                                >
                                    {item}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </DataCard>
            <DataCard title={t('preferences.location.title')}>
                <div className="flex flex-col gap-5">
                    <RadioGroup
                        aria-label={t('preferences.location.title')}
                        value={value.remoteMode}
                        onChange={(next) => set('remoteMode', next)}
                        options={REMOTE_MODES.map((mode) => ({
                            value: mode,
                            title: t(`preferences.location.${mode}.title`),
                            description: t(
                                `preferences.location.${mode}.description`,
                            ),
                        }))}
                    />
                    {errors?.remoteMode && (
                        <p role="alert" className="text-chip text-danger-text">
                            {errors.remoteMode}
                        </p>
                    )}
                    {showsLocations(value.remoteMode) && (
                        <Field
                            label={t('preferences.locations.label')}
                            error={
                                hasMissingLocations(value)
                                    ? t('preferences.locations.required')
                                    : errors?.locations
                            }
                        >
                            {(control) => (
                                <TagsInput
                                    {...control}
                                    value={value.locations}
                                    onChange={(next) => set('locations', next)}
                                    placeholder={t(
                                        'preferences.locations.placeholder',
                                    )}
                                />
                            )}
                        </Field>
                    )}
                </div>
            </DataCard>
            <DataCard title={t('preferences.exclude.title')}>
                <Field
                    label={t('preferences.exclude.label')}
                    hint={t('preferences.exclude.help')}
                    error={errors?.excludeWords}
                >
                    {(control) => (
                        <TagsInput
                            {...control}
                            value={value.excludeWords}
                            onChange={(next) => set('excludeWords', next)}
                            placeholder={t('preferences.exclude.placeholder')}
                        />
                    )}
                </Field>
            </DataCard>
        </>
    );
};
