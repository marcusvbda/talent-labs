import { useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import {
    usePreferences,
    useSavePreferences,
} from '@/data/hooks/use-preferences';
import { usePreferencesPreview } from '@/data/hooks/use-preferences-preview';
import {
    PreferencesSections,
    hasMissingLocations,
    preferencesFieldErrors,
} from '@/features/preferences/preferences-sections';
import { PreferencesSummary } from '@/features/preferences/preferences-summary';
import { useT } from '@/i18n/i18n-provider';
import { AppGrid } from '@/layouts/app-layout';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import type { Preferences } from '@/types/contracts';

const PREVIEW_DEBOUNCE_MS = 400;

const Editor = ({
    saved,
    onBack,
    onFinish,
}: {
    saved: Preferences;
    onBack: () => void;
    onFinish: () => void;
}) => {
    const { t } = useT();
    const [draft, setDraft] = useState(saved);
    const save = useSavePreferences();
    const preview = usePreferencesPreview(
        useDebouncedValue(draft, PREVIEW_DEBOUNCE_MS),
    );
    const errors = preferencesFieldErrors(save.error?.errors);

    const onSave = () =>
        save.mutate(draft, {
            onSuccess: onFinish,
            onError: (error) => {
                if (error.status !== 422) {
                    toast.error(t('preferences.save_failed'));
                }
            },
        });

    return (
        <AppGrid>
            <div className="flex min-w-0 flex-col gap-gap md:col-span-2 lg:col-span-7">
                <PreferencesSections
                    value={draft}
                    onChange={setDraft}
                    errors={errors}
                />
            </div>
            <aside className="flex min-w-0 flex-col gap-4 md:col-span-2 lg:col-span-5">
                <DataCard title={t('onboarding.preferences.summary')}>
                    <PreferencesSummary
                        draft={draft}
                        preview={preview.data}
                        previewError={preview.isError}
                        onRetry={() => void preview.refetch()}
                        blocked={hasMissingLocations(draft)}
                        saving={save.isPending}
                        onSave={onSave}
                        actionLabel={t('onboarding.finish')}
                    />
                </DataCard>
                <Button variant="secondary-tile" onClick={onBack}>
                    {t('onboarding.back')}
                </Button>
            </aside>
        </AppGrid>
    );
};

export const StepPreferences = ({
    onBack,
    onFinish,
}: {
    onBack: () => void;
    onFinish: () => void;
}) => {
    const { data, isError, refetch } = usePreferences();

    if (isError || !data) {
        return (
            <DataCard
                title=""
                state={isError ? 'error' : 'loading'}
                onRetry={() => void refetch()}
            />
        );
    }

    return <Editor saved={data} onBack={onBack} onFinish={onFinish} />;
};
