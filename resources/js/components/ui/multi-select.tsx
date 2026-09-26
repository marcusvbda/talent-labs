import { Listbox, ListboxButton } from '@headlessui/react';
import { ChevronDown } from 'lucide-react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { Chip } from './chip';
import type { FieldControlProps } from './field';
import { CONTROL_BASE } from './input';
import { SelectOptions } from './select';
import type { Option } from './select';

export function MultiSelect<T>({
    value,
    onChange,
    options,
    placeholder,
    disabled,
    open,
    className,
    ...aria
}: {
    value: T[];
    onChange: (value: T[]) => void;
    options: Option<T>[];
    placeholder?: string;
    disabled?: boolean;
    /** Renders the option list inline and always open (styleguide preview). */
    open?: boolean;
    className?: string;
} & Partial<FieldControlProps>) {
    const { t } = useT();
    const selected = options.filter((option) => value.includes(option.value));

    return (
        <Listbox
            multiple
            value={value}
            onChange={onChange}
            disabled={disabled}
        >
            <ListboxButton
                {...aria}
                className={cn(
                    CONTROL_BASE,
                    'flex min-h-control-md items-center justify-between gap-3 px-6 py-2 text-left',
                    className,
                )}
            >
                <span className="flex flex-wrap items-center gap-2">
                    {selected.length === 0 ? (
                        <span className="text-faint">
                            {placeholder ?? t('forms.select.placeholder')}
                        </span>
                    ) : (
                        selected.map((option) => (
                            <Chip key={String(option.value)} variant="stack">
                                {option.label}
                            </Chip>
                        ))
                    )}
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
