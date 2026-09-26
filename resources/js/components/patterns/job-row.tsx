import { CompanyLogo } from '@/components/patterns/company-logo';
import { Checkbox } from '@/components/ui/checkbox';
import { Chip } from '@/components/ui/chip';
import { cn } from '@/lib/utils';
import type { Locale } from '@/types/shared';

export function JobRow({
    selected,
    onSelectedChange,
    company,
    title,
    meta,
    stack,
    language,
    disabled = false,
}: {
    selected: boolean;
    onSelectedChange: (selected: boolean) => void;
    company: string;
    title: string;
    meta: string;
    stack: string[];
    language: Locale;
    disabled?: boolean;
}) {
    return (
        <label
            className={cn(
                'flex items-center gap-row-gap rounded-row border-row py-row-t pr-row-r pb-row-b pl-row-l transition-colors',
                selected
                    ? 'border-accent-line bg-accent-soft'
                    : 'border-transparent bg-tile',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            )}
        >
            <Checkbox
                checked={selected}
                disabled={disabled}
                className="[&>input:not(:checked)]:border [&>input:not(:checked)]:border-hairline [&>input:not(:checked)]:bg-card"
                onChange={(event) => onSelectedChange(event.target.checked)}
            />
            <CompanyLogo name={company} />
            <div className="flex min-w-0 flex-1 flex-col gap-1 md:flex-row md:items-center md:gap-row-gap">
                <div className="min-w-0 md:flex-1">
                    <p className="truncate text-row-title-sm text-ink md:text-row-title">
                        {title}
                    </p>
                    <p className="truncate text-body text-muted">{meta}</p>
                </div>
                <div className="flex flex-wrap items-center gap-1.5 md:justify-end">
                    {stack.map((item) => (
                        <Chip
                            key={item}
                            variant="stack"
                            className="border border-hairline bg-card"
                        >
                            {item}
                        </Chip>
                    ))}
                    <Chip variant="language">{language.toUpperCase()}</Chip>
                </div>
            </div>
        </label>
    );
}
