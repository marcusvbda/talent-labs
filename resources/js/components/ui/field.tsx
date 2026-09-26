import { useId } from 'react';
import type { ReactNode } from 'react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

export type FieldControlProps = {
    id: string;
    'aria-describedby'?: string;
    'aria-invalid'?: true;
};

export function Field({
    label,
    hint,
    error,
    id,
    optional,
    className,
    children,
}: {
    label: string;
    hint?: string;
    error?: string;
    id?: string;
    optional?: boolean;
    className?: string;
    children: (control: FieldControlProps) => ReactNode;
}) {
    const { t } = useT();
    const generated = useId();
    const controlId = id ?? generated;
    const hintId = hint ? `${controlId}-hint` : undefined;
    const errorId = error ? `${controlId}-error` : undefined;
    const describedBy = [errorId, hintId].filter(Boolean).join(' ');

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <label
                htmlFor={controlId}
                className="flex items-baseline justify-between gap-2 text-label-sm text-ink"
            >
                {label}
                {optional && (
                    <span className="text-chip text-muted">
                        {t('forms.optional')}
                    </span>
                )}
            </label>
            {children({
                id: controlId,
                'aria-describedby': describedBy || undefined,
                'aria-invalid': error ? true : undefined,
            })}
            {hint && (
                <p id={hintId} className="text-chip text-muted">
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-chip text-danger-text"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
