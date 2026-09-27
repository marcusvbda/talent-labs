import { ApiError } from '@/data/api';
import { fixtureState, jobMatchesPreferences } from '@/data/fixtures/state';
import type { Preferences, PreferencesPreview } from '@/types/contracts';

const MAX_ENTRIES = 20;
const MAX_LENGTH = 60;

// Trim, drop empties, dedupe case-insensitively, cap the list and each entry.
const normalizeList = (values: string[]): string[] => {
    const seen = new Set<string>();
    const result: string[] = [];

    for (const raw of values) {
        const value = raw.trim().slice(0, MAX_LENGTH).trim();
        const key = value.toLowerCase();

        if (value === '' || seen.has(key)) {
            continue;
        }

        seen.add(key);
        result.push(value);

        if (result.length === MAX_ENTRIES) {
            break;
        }
    }

    return result;
};

export const currentPreferences = (): Preferences => {
    const prefs = fixtureState.get().preferences;

    return {
        ...prefs,
        titles: [...prefs.titles],
        seniorities: [...prefs.seniorities],
        stack: [...prefs.stack],
        locations: [...prefs.locations],
        excludeWords: [...prefs.excludeWords],
    };
};

export function savePreferences(input: Preferences): Preferences {
    const normalized: Preferences = {
        titles: normalizeList(input.titles),
        seniorities: [...new Set(input.seniorities)],
        stack: normalizeList(input.stack),
        locations: normalizeList(input.locations),
        remoteMode: input.remoteMode,
        excludeWords: normalizeList(input.excludeWords),
    };

    if (
        normalized.remoteMode === 'locations_only' &&
        normalized.locations.length === 0
    ) {
        throw new ApiError(422, 'Invalid preferences', {
            locations: ['Add at least one location.'],
        });
    }

    fixtureState.set((state) => ({
        ...state,
        preferences: normalized,
        onboarding: { ...state.onboarding, preferencesSaved: true },
    }));

    return currentPreferences();
}

export const zeroPreview = (): PreferencesPreview => ({
    matchCount: 0,
    byLanguage: { en: 0, pt: 0 },
});

// Counts the whole pool: ignores the language rule and today's applications.
export function previewPreferences(draft: Preferences): PreferencesPreview {
    const preview = zeroPreview();

    for (const job of fixtureState.get().jobs) {
        if (jobMatchesPreferences(job, draft)) {
            preview.matchCount += 1;
            preview.byLanguage[job.language] += 1;
        }
    }

    return preview;
}
