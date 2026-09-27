import type { TemplateVariable } from '@/types/contracts';

// Replaces `{{ name }}` (optional inner whitespace). Variables absent from
// `vars` (notably job_url) and unknown names stay as the literal token.
export function renderTemplate(
    text: string,
    vars: Partial<Record<TemplateVariable, string>>,
): string {
    return text.replace(/\{\{\s*([a-z_]+)\s*\}\}/g, (token, name: string) => {
        const value = (vars as Record<string, string | undefined>)[name];

        return value === undefined ? token : value;
    });
}
