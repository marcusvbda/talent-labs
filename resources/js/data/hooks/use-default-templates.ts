import { defaultTemplates } from '@/data/fixtures/catalog/profiles';
import { useFixtures } from '@/data/source';
import type { JobLanguage } from '@/types/contracts';

/**
 * Starter subject/body for a language. Fixtures own them (D3-A); against the
 * real API the server prefills the created profile, so this is empty.
 */
export function useDefaultTemplates(language: JobLanguage): {
    subject: string;
    body: string;
} {
    return useFixtures ? defaultTemplates[language] : { subject: '', body: '' };
}
