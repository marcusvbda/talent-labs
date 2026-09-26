import { Radio, RadioGroup } from '@headlessui/react';
import { cn } from '@/lib/utils';

export function Segmented<T>({
    value,
    onChange,
    options,
    ariaLabel,
    disabled,
    className,
}: {
    value: T;
    onChange: (value: T) => void;
    options: { value: T; label: string }[];
    ariaLabel: string;
    disabled?: boolean;
    className?: string;
}) {
    return (
        <RadioGroup
            value={value}
            onChange={onChange}
            disabled={disabled}
            aria-label={ariaLabel}
            className={cn(
                'flex max-w-full gap-1 overflow-x-auto rounded-full bg-tile p-1 md:inline-flex md:overflow-visible',
                className,
            )}
        >
            {options.map((option) => (
                <Radio
                    key={String(option.value)}
                    value={option.value}
                    className="inline-flex h-control-xs shrink-0 cursor-pointer items-center justify-center rounded-full px-6 text-label-sm whitespace-nowrap text-muted transition-colors hover:text-ink focus-visible:focus-ring data-checked:bg-card data-checked:text-ink data-disabled:cursor-not-allowed data-disabled:opacity-50"
                >
                    {option.label}
                </Radio>
            ))}
        </RadioGroup>
    );
}
