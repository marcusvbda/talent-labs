import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { previewTemplate } from '@/data/fixtures/handlers/profiles';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { JobLanguage } from '@/types/contracts';

type Input = {
    language: JobLanguage;
    subject: string;
    body: string;
    coverLetter: string;
};

// The screen debounces the input (500 ms); the previous preview stays visible.
export function useTemplatePreview(input: Input) {
    const fixture = () => fixtureCall(() => previewTemplate(input));

    return useQuery({
        queryKey: keys.profilesPreview(input),
        queryFn: fromSource({ fixture }),
        placeholderData: keepPreviousData,
    });
}
