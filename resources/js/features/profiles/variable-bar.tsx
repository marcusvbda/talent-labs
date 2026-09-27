import { useT } from '@/i18n/i18n-provider';
import type { TemplateVariable } from '@/types/contracts';

// Chips that insert a `{{ variable }}` token into the focused template field.
export const VariableBar = ({
    variables,
    onInsert,
}: {
    variables: TemplateVariable[];
    onInsert: (token: string) => void;
}) => {
    const { t } = useT();

    return (
        <div className="flex flex-col gap-2">
            <p className="text-chip text-muted">
                {t('profiles.variables.title')}
            </p>
            <ul
                aria-label={t('profiles.variables.title')}
                className="flex flex-wrap gap-2"
            >
                {variables.map((variable) => {
                    const token = `{{ ${variable} }}`;

                    return (
                        <li key={variable}>
                            <button
                                type="button"
                                // Keeps the caret and focus in the field.
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => onInsert(token)}
                                className="cursor-pointer rounded-full bg-tile px-2.5 py-1 font-mono text-chip text-muted transition-colors hover:bg-hairline hover:text-ink focus-visible:focus-ring"
                            >
                                {token}
                            </button>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
};
