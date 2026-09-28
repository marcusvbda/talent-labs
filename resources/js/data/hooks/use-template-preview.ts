import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { endpoints } from '@/data/endpoints';
import { previewTemplate } from '@/data/fixtures/handlers/profiles';
import { fixtureCall } from '@/data/fixtures/runtime';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import type { JobLanguage, TemplatePreview } from '@/types/contracts';

type Input = {
    language: JobLanguage;
    subject: string;
    body: string;
    coverLetter: string;
};

// The screen debounces the input (500 ms); the previous preview stays visible.
export function useTemplatePreview(input: Input) {
    const real = () => {
        const e = endpoints.templatePreview(input.language);

        return apiFetch<TemplatePreview>(e.url, {
            method: e.method,
            body: {
                subject: input.subject,
                body: input.body,
                coverLetter: input.coverLetter,
            },
        });
    };
    const fixture = () => fixtureCall(() => previewTemplate(input));

    return useQuery({
        queryKey: keys.profilesPreview(input),
        queryFn: fromSource({ real, fixture }),
        placeholderData: keepPreviousData,
    });
}
