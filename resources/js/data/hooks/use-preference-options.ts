import { SENIORITY_KEYS, STACK_SUGGESTIONS } from '@/data/fixtures/catalog';
import type { Seniority } from '@/types/contracts';

// Static choice lists for the preferences editor (no request involved).
export function usePreferenceOptions(): {
    seniorities: Seniority[];
    stackSuggestions: string[];
} {
    return {
        seniorities: SENIORITY_KEYS,
        stackSuggestions: STACK_SUGGESTIONS,
    };
}
