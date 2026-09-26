import {
    Radio,
    RadioGroup as HeadlessRadioGroup,
} from '@headlessui/react';
import { CircleCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { FieldControlProps } from './field';

export function RadioGroup<T>({
    value,
    onChange,
    options,
    disabled,
    className,
    ...aria
}: {
    value: T;
    onChange: (value: T) => void;
    options: { value: T; title: string; description?: string }[];
    disabled?: boolean;
    className?: string;
} & Partial<FieldControlProps> & { 'aria-label'?: string }) {
    return (
        <HeadlessRadioGroup
            value={value}
            onChange={onChange}
            disabled={disabled}
            {...aria}
            className={cn('grid gap-3', className)}
        >
            {options.map((option) => (
                <Radio
                    key={String(option.value)}
                    value={option.value}
                    className="group flex cursor-pointer items-start gap-4 rounded-tile bg-tile p-card-sm outline-2 outline-transparent transition-colors focus-visible:focus-ring data-checked:bg-accent-soft data-checked:outline-accent-line data-disabled:cursor-not-allowed data-disabled:opacity-50"
                >
                    <span className="flex min-w-0 flex-1 flex-col gap-1">
                        <span className="text-label-sm text-ink">
                            {option.title}
                        </span>
                        {option.description && (
                            <span className="text-chip text-muted">
                                {option.description}
                            </span>
                        )}
                    </span>
                    <span
                        aria-hidden="true"
                        className="mt-0.5 size-5 shrink-0 rounded-full bg-card outline-2 outline-hairline group-data-checked:hidden"
                    />
                    <CircleCheck
                        aria-hidden="true"
                        strokeWidth={1.8}
                        className="mt-0.5 hidden size-5 shrink-0 text-accent-deep group-data-checked:block"
                    />
                </Radio>
            ))}
        </HeadlessRadioGroup>
    );
}
