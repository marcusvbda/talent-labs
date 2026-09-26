import {
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/react';
import { Check, ChevronDown } from 'lucide-react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import type { FieldControlProps } from './field';
import { CONTROL_BASE } from './input';

export type Option<T> = { value: T; label: string };

export const LISTBOX_PANEL =
    'z-20 max-h-72 w-(--button-width) overflow-auto rounded-row bg-card p-2 shadow-shell outline-2 outline-hairline transition duration-150 focus:outline-hairline data-closed:opacity-0';

export const LISTBOX_OPTION =
    'flex cursor-pointer items-center justify-between gap-3 rounded-checkbox px-4 py-3 text-body text-ink select-none data-focus:bg-tile data-selected:text-accent-deep';

export const LISTBOX_EMPTY = 'px-4 py-3 text-body text-muted';

export const TRIGGER =
    'flex h-control-md items-center justify-between gap-3 px-6 text-left';

export function SelectOptions<T>({
    options,
    open,
}: {
    options: Option<T>[];
    open?: boolean;
}) {
    const { t } = useT();

    return (
        <ListboxOptions
            anchor={open ? undefined : { to: 'bottom start', gap: 8 }}
            static={open}
            transition={!open}
            className={cn(LISTBOX_PANEL, open && 'mt-2')}
        >
            {options.length === 0 && (
                <p className={LISTBOX_EMPTY}>{t('forms.select.empty')}</p>
            )}
            {options.map((option) => (
                <ListboxOption
                    key={String(option.value)}
                    value={option.value}
                    className={LISTBOX_OPTION}
                >
                    {option.label}
                    <Check
                        aria-hidden="true"
                        strokeWidth={1.8}
                        className="invisible size-5 shrink-0 in-data-selected:visible"
                    />
                </ListboxOption>
            ))}
        </ListboxOptions>
    );
}

export function Select<T>({
    value,
    onChange,
    options,
    placeholder,
    disabled,
    open,
    className,
    ...aria
}: {
    value: T | null;
    onChange: (value: T) => void;
    options: Option<T>[];
    placeholder?: string;
    disabled?: boolean;
    /** Renders the option list inline and always open (styleguide preview). */
    open?: boolean;
    className?: string;
} & Partial<FieldControlProps>) {
    const { t } = useT();
    const selected = options.find((option) => option.value === value);

    return (
        <Listbox
            value={value ?? undefined}
            onChange={onChange}
            disabled={disabled}
        >
            <ListboxButton
                {...aria}
                className={cn(CONTROL_BASE, TRIGGER, className)}
            >
                <span className={cn('truncate', !selected && 'text-faint')}>
                    {selected?.label ??
                        placeholder ??
                        t('forms.select.placeholder')}
                </span>
                <ChevronDown
                    aria-hidden="true"
                    strokeWidth={1.8}
                    className="size-5 shrink-0 text-muted"
                />
            </ListboxButton>
            <SelectOptions options={options} open={open} />
        </Listbox>
    );
}
