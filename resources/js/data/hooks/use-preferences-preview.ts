import { keepPreviousData, useQuery } from '@tanstack/react-query';
import {
    previewPreferences,
    zeroPreview,
} from '@/data/fixtures/handlers/preferences';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { Preferences } from '@/types/contracts';

// The screen debounces the draft; the previous count stays visible meanwhile.
export function usePreferencesPreview(draft: Preferences) {
    const fixture = () =>
        fixtureCall(() => previewPreferences(draft), { empty: zeroPreview });

    return useQuery({
        queryKey: keys.preferences.preview(draft),
        queryFn: fromSource({ fixture }),
        placeholderData: keepPreviousData,
    });
}
