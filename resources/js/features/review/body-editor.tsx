import { useLayoutEffect, useRef } from 'react';
import type { ChangeEvent, CompositionEvent, KeyboardEvent } from 'react';
import { Chip } from '@/components/ui/chip';
import { Textarea } from '@/components/ui/textarea';
import type { FieldControlProps } from '@/components/ui/field';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

export const JOB_URL_TOKEN = '{{ job_url }}';

// The highlight layer is only aligned while it wraps exactly like the textarea, so both get
// the same text metrics. `geometricPrecision` forces fractional glyph advances everywhere: some
// browsers round them inside form controls only, which makes the textarea wider than the layer.
const TEXT_METRICS =
    '[font-feature-settings:inherit] [font-kerning:normal] [text-rendering:geometricPrecision]';

type Range = { start: number; end: number };

const findToken = (value: string): Range[] => {
    const ranges: Range[] = [];
    let from = value.indexOf(JOB_URL_TOKEN);

    while (from !== -1) {
        ranges.push({ start: from, end: from + JOB_URL_TOKEN.length });
        from = value.indexOf(JOB_URL_TOKEN, from + JOB_URL_TOKEN.length);
    }

    return ranges;
};

// Applies an edit so that a token touched by the change is removed whole.
const protectToken = (
    previous: string,
    next: string,
    selectionStart: number,
): { value: string; caret: number } => {
    let prefix = 0;
    const limit = Math.min(previous.length, next.length);

    while (prefix < limit && previous[prefix] === next[prefix]) {
        prefix += 1;
    }

    if (next.length >= previous.length) {
        // Anchor a pure insertion on the real caret, not on the greedy diff.
        prefix = Math.min(
            prefix,
            Math.max(0, selectionStart - (next.length - previous.length)),
        );
    }

    let suffix = 0;

    while (
        suffix < limit - prefix &&
        previous[previous.length - 1 - suffix] ===
            next[next.length - 1 - suffix]
    ) {
        suffix += 1;
    }

    const removedEnd = previous.length - suffix;
    const inserted = next.slice(prefix, next.length - suffix);
    const touched = findToken(previous).find(
        (token) =>
            prefix < token.end &&
            removedEnd > token.start &&
            !(prefix <= token.start && removedEnd >= token.end),
    );
    const damaged =
        touched ??
        findToken(previous).find(
            (token) =>
                prefix === removedEnd &&
                prefix > token.start &&
                prefix < token.end,
        );

    if (!damaged) {
        return { value: next, caret: prefix + inserted.length };
    }

    const start = Math.min(prefix, damaged.start);
    const end = Math.max(removedEnd, damaged.end);

    return {
        value: previous.slice(0, start) + inserted + previous.slice(end),
        caret: start + inserted.length,
    };
};

const Overlay = ({ value }: { value: string }) => {
    const parts: { text: string; token: boolean }[] = [];
    let cursor = 0;

    findToken(value).forEach((token) => {
        parts.push({ text: value.slice(cursor, token.start), token: false });
        parts.push({ text: JOB_URL_TOKEN, token: true });
        cursor = token.end;
    });
    parts.push({ text: value.slice(cursor), token: false });

    return (
        <>
            {parts.map((part, index) =>
                part.token ? (
                    <span
                        key={index}
                        className="rounded-full bg-accent-soft py-0.5 text-accent-deep"
                    >
                        {part.text}
                    </span>
                ) : (
                    <span key={index}>{part.text}</span>
                ),
            )}
            {/* Keeps a trailing newline measurable. */}
            {'\n'}
        </>
    );
};

export function BodyEditor({
    value,
    onChange,
    control,
    invalid,
    disabled,
}: {
    value: string;
    onChange: (value: string) => void;
    control: FieldControlProps;
    invalid: boolean;
    disabled?: boolean;
}) {
    const { t } = useT();
    const textarea = useRef<HTMLTextAreaElement>(null);
    const mirror = useRef<HTMLDivElement>(null);
    const caret = useRef<number | null>(null);

    useLayoutEffect(() => {
        if (caret.current !== null && textarea.current) {
            textarea.current.setSelectionRange(caret.current, caret.current);
            caret.current = null;
        }
    }, [value]);

    const composingFrom = useRef<string | null>(null);

    const onInput = (event: ChangeEvent<HTMLTextAreaElement>) => {
        if ((event.nativeEvent as InputEvent).isComposing) {
            composingFrom.current ??= value;
            onChange(event.target.value);

            return;
        }

        const result = protectToken(
            value,
            event.target.value,
            event.target.selectionStart,
        );

        caret.current = result.caret;
        onChange(result.value);
    };

    const onCompositionEnd = (event: CompositionEvent<HTMLTextAreaElement>) => {
        const from = composingFrom.current;

        composingFrom.current = null;

        if (from === null) {
            return;
        }

        const result = protectToken(
            from,
            event.currentTarget.value,
            event.currentTarget.selectionStart,
        );

        caret.current = result.caret;
        onChange(result.value);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        const { selectionStart, selectionEnd } = event.currentTarget;

        if (selectionStart !== selectionEnd) {
            return;
        }

        const backward = event.key === 'Backspace';

        if (!backward && event.key !== 'Delete') {
            return;
        }

        const token = findToken(value).find((range) =>
            backward
                ? selectionStart > range.start && selectionStart <= range.end
                : selectionStart >= range.start && selectionStart < range.end,
        );

        if (!token) {
            return;
        }

        event.preventDefault();
        caret.current = token.start;
        onChange(value.slice(0, token.start) + value.slice(token.end));
    };

    return (
        <div className="flex flex-col gap-2">
            <div
                className={cn(
                    'relative rounded-tile',
                    invalid ? 'bg-danger-bg' : 'bg-tile',
                )}
            >
                <div
                    ref={mirror}
                    aria-hidden="true"
                    className={cn(
                        TEXT_METRICS,
                        'pointer-events-none absolute inset-0 [scrollbar-gutter:stable] overflow-y-scroll px-6 py-4 text-body wrap-break-word whitespace-pre-wrap text-transparent',
                    )}
                >
                    <Overlay value={value} />
                </div>
                <Textarea
                    {...control}
                    ref={textarea}
                    rows={10}
                    value={value}
                    disabled={disabled}
                    onChange={onInput}
                    onKeyDown={onKeyDown}
                    onCompositionEnd={onCompositionEnd}
                    onScroll={(event) => {
                        if (mirror.current) {
                            mirror.current.scrollTop =
                                event.currentTarget.scrollTop;
                        }
                    }}
                    className={cn(
                        TEXT_METRICS,
                        'relative block resize-none [scrollbar-gutter:stable] overflow-y-scroll bg-transparent aria-invalid:bg-transparent',
                    )}
                />
            </div>
            {value.includes(JOB_URL_TOKEN) && (
                <p className="flex flex-wrap items-center gap-2 text-chip text-muted">
                    <Chip variant="plan">{t('review.job_link')}</Chip>
                    {t('review.job_link.hint')}
                </p>
            )}
        </div>
    );
}
