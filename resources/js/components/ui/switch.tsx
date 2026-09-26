import { Switch as HeadlessSwitch } from '@headlessui/react';
import { cn } from '@/lib/utils';

export function Switch({
    checked,
    onChange,
    disabled,
    className,
    ...aria
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
    className?: string;
    id?: string;
    'aria-label'?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: true;
}) {
    return (
        <HeadlessSwitch
            checked={checked}
            onChange={onChange}
            disabled={disabled}
            className={cn(
                'group relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full bg-hairline transition-colors focus-visible:focus-ring data-checked:bg-accent data-disabled:cursor-not-allowed data-disabled:opacity-50',
                className,
            )}
            {...aria}
        >
            <span
                aria-hidden="true"
                className="inline-block size-5 translate-x-1 rounded-full bg-card transition-transform group-data-checked:translate-x-6"
            />
        </HeadlessSwitch>
    );
}
