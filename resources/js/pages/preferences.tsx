import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/patterns/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ErrorState } from '@/components/ui/error-state';
import { Sheet } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from '@/components/ui/toast';
import {
    usePreferences,
    useSavePreferences,
} from '@/data/hooks/use-preferences';
import { usePreferencesPreview } from '@/data/hooks/use-preferences-preview';
import {
    PreferencesSections,
    hasMissingLocations,
} from '@/features/preferences/preferences-sections';
import { PreferencesSummary } from '@/features/preferences/preferences-summary';
import { useT } from '@/i18n/i18n-provider';
import { AppGrid, AppLayout } from '@/layouts/app-layout';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import type { Preferences as PreferencesValue } from '@/types/contracts';

const PREVIEW_DEBOUNCE_MS = 400;

// Order-insensitive for seniorities, which the server may return in any order.
const signature = (value: PreferencesValue) =>
    JSON.stringify({ ...value, seniorities: [...value.seniorities].sort() });

const PreferencesSkeleton = () => {
    const { t } = useT();

    return (
        <AppGrid>
            <div
                role="status"
                aria-busy="true"
                aria-label={t('common.loading')}
                className="flex min-w-0 flex-col gap-gap lg:col-span-8"
            >
                {[0, 1, 2].map((index) => (
                    <Card
                        key={index}
                        tone="light"
                        className="flex flex-col gap-4"
                    >
                        <Skeleton shape="line" className="h-6 w-1/4" />
                        <Skeleton shape="block" />
                    </Card>
                ))}
            </div>
            <Card tone="light" className="flex flex-col gap-4 lg:col-span-4">
                <Skeleton shape="line" className="h-6 w-1/3" />
                <Skeleton shape="line" />
                <Skeleton shape="line" className="w-2/3" />
            </Card>
        </AppGrid>
    );
};

const PreferencesEditor = ({ saved }: { saved: PreferencesValue }) => {
    const { t } = useT();
    const [draft, setDraft] = useState(saved);
    const [summaryOpen, setSummaryOpen] = useState(false);
    const save = useSavePreferences();
    const debounced = useDebouncedValue(draft, PREVIEW_DEBOUNCE_MS);
    const preview = usePreferencesPreview(debounced);
    const dirty = signature(draft) !== signature(saved);
    const blocked = !dirty || hasMissingLocations(draft);

    const onSave = () =>
        save.mutate(draft, {
            onSuccess: (next) => {
                setDraft(next);
                toast.success(t('preferences.saved'));
            },
            onError: () => toast.error(t('preferences.save_failed')),
        });

    const summary = (
        <PreferencesSummary
            draft={draft}
            preview={preview.data}
            previewError={preview.isError}
            onRetry={() => void preview.refetch()}
            blocked={blocked}
            saving={save.isPending}
            onSave={onSave}
        />
    );

    return (
        <>
            <AppGrid className="pb-24 lg:pb-0">
                <div className="flex min-w-0 flex-col gap-gap lg:col-span-8">
                    <PreferencesSections value={draft} onChange={setDraft} />
                </div>
                <aside className="hidden min-w-0 lg:col-span-4 lg:block">
                    <Card tone="light" className="lg:sticky lg:top-6">
                        {summary}
                    </Card>
                </aside>
            </AppGrid>
            <div className="fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 border-t border-hairline bg-card px-card-sm pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
                <Button
                    variant="secondary-tile"
                    size="md"
                    className="flex-1"
                    onClick={() => setSummaryOpen(true)}
                >
                    {t('preferences.summary.open')}
                </Button>
                <Button
                    size="md"
                    className="flex-1"
                    loading={save.isPending}
                    disabled={blocked}
                    onClick={onSave}
                >
                    {t('preferences.save')}
                </Button>
            </div>
            <div className="lg:hidden">
                <Sheet
                    open={summaryOpen}
                    onClose={() => setSummaryOpen(false)}
                    side="bottom"
                    title={t('preferences.summary.sheet_title')}
                >
                    {summary}
                </Sheet>
            </div>
        </>
    );
};

export default function Preferences() {
    const { t } = useT();
    const { data, isError, refetch } = usePreferences();

    return (
        <AppLayout>
            <Head title={t('preferences.title')} />
            <PageHeader
                eyebrow={t('preferences.eyebrow')}
                title={t('preferences.title')}
                summary={t('preferences.intro')}
            />
            <div
                role="note"
                className="flex items-start gap-3 rounded-tile bg-accent-soft p-card-sm text-body text-accent-deep"
            >
                <Info
                    aria-hidden="true"
                    strokeWidth={1.8}
                    className="mt-0.5 size-5 shrink-0"
                />
                {t('preferences.rule')}
            </div>
            {isError ? (
                <Card tone="light">
                    <ErrorState onRetry={() => void refetch()} />
                </Card>
            ) : !data ? (
                <PreferencesSkeleton />
            ) : (
                <PreferencesEditor saved={data} />
            )}
        </AppLayout>
    );
}
