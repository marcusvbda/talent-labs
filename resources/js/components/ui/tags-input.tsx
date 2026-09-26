import { X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ClipboardEvent, KeyboardEvent } from 'react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import type { FieldControlProps } from './field';

export function TagsInput({
    value,
    onChange,
    placeholder,
    disabled,
    className,
    ...aria
}: {
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
} & Partial<FieldControlProps>) {
    const { t } = useT();
    const [draft, setDraft] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    const add = (raw: string[]) => {
        const next = [...value];

        raw.map((tag) => tag.trim())
            .filter(Boolean)
            .forEach((tag) => {
                if (!next.includes(tag)) {
                    next.push(tag);
                }
            });

        if (next.length !== value.length) {
            onChange(next);
        }
    };

    const commit = () => {
        add([draft]);
        setDraft('');
    };

    const remove = (tag: string) => {
        onChange(value.filter((item) => item !== tag));
        inputRef.current?.focus();
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            commit();
        } else if (event.key === 'Backspace' && draft === '') {
            if (value.length > 0) {
                onChange(value.slice(0, -1));
            }
        }
    };

    const onPaste = (event: ClipboardEvent<HTMLInputElement>) => {
        const text = event.clipboardData.getData('text');

        if (!text.includes(',')) {
            return;
        }

        event.preventDefault();
        add(text.split(','));
    };

    return (
        <div
            aria-disabled={disabled || undefined}
            aria-invalid={aria['aria-invalid']}
            onClick={() => inputRef.current?.focus()}
            className={cn(
                'flex min-h-control-md w-full flex-wrap items-center gap-2 rounded-tile bg-tile px-4 py-2 outline-2 outline-transparent transition-colors focus-within:focus-ring aria-disabled:cursor-not-allowed aria-disabled:opacity-50 aria-invalid:bg-danger-bg aria-invalid:outline-danger',
                className,
            )}
        >
            {value.map((tag) => (
                <span
                    key={tag}
                    className="inline-flex items-center gap-1 rounded-full bg-card py-1 pr-1 pl-3 text-chip text-ink"
                >
                    {tag}
                    <button
                        type="button"
                        disabled={disabled}
                        aria-label={t('forms.tags.remove', { tag })}
                        onClick={(event) => {
                            event.stopPropagation();
                            remove(tag);
                        }}
                        className="inline-flex size-5 items-center justify-center rounded-full text-muted hover:bg-tile hover:text-ink focus-visible:focus-ring"
                    >
                        <X
                            aria-hidden="true"
                            strokeWidth={1.8}
                            className="size-3.5"
                        />
                    </button>
                </span>
            ))}
            <input
                ref={inputRef}
                id={aria.id}
                aria-describedby={aria['aria-describedby']}
                aria-invalid={aria['aria-invalid']}
                type="text"
                value={draft}
                disabled={disabled}
                placeholder={value.length === 0 ? placeholder : undefined}
                onChange={(event) => setDraft(event.target.value)}
                onKeyDown={onKeyDown}
                onPaste={onPaste}
                onBlur={commit}
                className="min-w-24 flex-1 bg-transparent py-2 text-body text-ink outline-none placeholder:text-faint disabled:cursor-not-allowed"
            />
        </div>
    );
}
