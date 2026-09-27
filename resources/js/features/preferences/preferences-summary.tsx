import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { ErrorState } from '@/components/ui/error-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';
import type {
    JobLanguage,
    Preferences,
    PreferencesPreview,
} from '@/types/contracts';

const LANGUAGES: JobLanguage[] = ['en', 'pt', 'es'];

const capitalize = (text: string, locale: string) =>
    text.charAt(0).toLocaleUpperCase(locale) + text.slice(1);

// Builds the one-sentence summary on the client: "or" inside a field, a plain
// list between fields. Each fragment is its own key so PT/ES can word it
// naturally.
export function usePreferencesSentence(draft: Preferences): string {
    const { t, locale } = useT();
    const format = useFormat();
    const or = (items: string[]) => format.list(items, 'disjunction');
    const parts: string[] = [];

    if (draft.titles.length > 0) {
        parts.push(
            t('preferences.summary.roles', {
                list: or(
                    draft.titles.map((title) =>
                        title.toLocaleLowerCase(locale),
                    ),
                ),
            }),
        );
    }

    if (draft.seniorities.length > 0) {
        parts.push(
            t('preferences.summary.seniority', {
                list: or(
                    draft.seniorities.map((level) =>
                        t(`preferences.seniority.${level}`).toLocaleLowerCase(
                            locale,
                        ),
                    ),
                ),
            }),
        );
    }

    if (draft.stack.length > 0) {
        parts.push(t('preferences.summary.stack', { list: or(draft.stack) }));
    }

    if (draft.remoteMode === 'remote_only') {
        parts.push(t('preferences.summary.remote'));
    } else if (draft.locations.length > 0) {
        parts.push(
            t(
                draft.remoteMode === 'remote_or_locations'
                    ? 'preferences.summary.remote_or_locations'
                    : 'preferences.summary.locations',
                { list: or(draft.locations) },
            ),
        );
    } else if (draft.remoteMode === 'remote_or_locations') {
        parts.push(t('preferences.summary.remote'));
    }

    if (draft.excludeWords.length > 0) {
        parts.push(
            t('preferences.summary.exclude', { list: or(draft.excludeWords) }),
        );
    }

    return parts.length === 0
        ? t('preferences.summary.empty')
        : `${capitalize(format.list(parts, 'unit'), locale)}.`;
}

export const PreferencesSummary = ({
    draft,
    preview,
    previewError,
    onRetry,
    blocked,
    saving,
    onSave,
}: {
    draft: Preferences;
    preview: PreferencesPreview | undefined;
    previewError: boolean;
    onRetry: () => void;
    blocked: boolean;
    saving: boolean;
    onSave: () => void;
}) => {
    const { t, plural } = useT();
    const format = useFormat();
    const sentence = usePreferencesSentence(draft);

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-2">
                <h3 className="text-label-sm text-muted">
                    {t('preferences.summary.title')}
                </h3>
                <p className="text-row-title-sm text-ink">{sentence}</p>
            </div>
            <div className="flex flex-col gap-3 border-t border-hairline pt-5">
                <h3 className="text-label-sm text-muted">
                    {t('preferences.counter.title')}
                </h3>
                {previewError && !preview ? (
                    <ErrorState onRetry={onRetry} />
                ) : !preview ? (
                    <div
                        role="status"
                        aria-busy="true"
                        aria-label={t('common.loading')}
                        className="flex flex-col gap-3"
                    >
                        <Skeleton shape="line" className="h-10 w-1/2" />
                        <Skeleton shape="line" className="w-2/3" />
                    </div>
                ) : (
                    <>
                        <p className="flex items-baseline gap-2">
                            <span className="text-display-sm text-ink tabular-nums">
                                {format.number(preview.matchCount)}
                            </span>
                            <span className="text-body text-muted">
                                {plural(
                                    'preferences.counter.jobs',
                                    preview.matchCount,
                                )}
                            </span>
                        </p>
                        <div className="flex flex-col gap-2">
                            <p className="text-chip text-muted">
                                {t('preferences.counter.by_language')}
                            </p>
                            <ul className="flex flex-wrap gap-2">
                                {LANGUAGES.map((language) => (
                                    <li
                                        key={language}
                                        className="flex items-center gap-2"
                                    >
                                        <Chip variant="language">
                                            {language.toUpperCase()}
                                        </Chip>
                                        <span className="text-body text-ink tabular-nums">
                                            {format.number(
                                                preview.byLanguage[language],
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <p className="text-chip text-muted">
                            {t('preferences.counter.note')}
                        </p>
                    </>
                )}
            </div>
            <Button
                size="md"
                fullWidth
                loading={saving}
                disabled={blocked}
                onClick={onSave}
            >
                {t('preferences.save')}
            </Button>
        </div>
    );
};
