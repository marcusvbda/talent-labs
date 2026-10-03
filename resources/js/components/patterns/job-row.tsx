import { ArrowUpRight } from 'lucide-react';
import { CompanyLogo } from '@/components/patterns/company-logo';
import { Checkbox } from '@/components/ui/checkbox';
import { Chip } from '@/components/ui/chip';
import { IconButton } from '@/components/ui/icon-button';
import { Tooltip } from '@/components/ui/tooltip';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import type { Locale } from '@/types/shared';

const ROW =
    'flex items-center gap-row-gap rounded-row border-row py-row-t pr-row-r pb-row-b pl-row-l transition-colors';

export function JobRow({
    selected,
    onSelectedChange,
    company,
    title,
    meta,
    stack,
    stackMatches = [],
    language,
    disabled = false,
    selectable = true,
    onOpen,
    jobUrl,
}: {
    selected: boolean;
    onSelectedChange: (selected: boolean) => void;
    company: string;
    title: string;
    meta: string;
    stack: string[];
    /** Subset of `stack` highlighted as matching the user's preference. */
    stackMatches?: string[];
    language: Locale;
    disabled?: boolean;
    /** Only used with `onOpen`: false renders a read-only row without checkbox. */
    selectable?: boolean;
    /** When set, the row body opens details and only the checkbox selects. */
    onOpen?: () => void;
    /** Captured job page; rendered as a link button when present (paid plans only). */
    jobUrl?: string | null;
}) {
    const { t } = useT();
    const checkbox = (
        <Checkbox
            checked={selected}
            disabled={disabled}
            aria-label={title}
            className="[&>input:not(:checked)]:border [&>input:not(:checked)]:border-hairline [&>input:not(:checked)]:bg-card"
            onChange={(event) => onSelectedChange(event.target.checked)}
        />
    );
    const body = (
        <>
            <CompanyLogo name={company} />
            <span className="flex min-w-0 flex-1 flex-col gap-1 md:flex-row md:items-center md:gap-row-gap">
                <span className="block min-w-0 md:flex-1">
                    <span className="block truncate text-row-title-sm text-ink md:text-row-title">
                        {title}
                    </span>
                    <span className="block truncate text-body text-muted">
                        {meta}
                    </span>
                </span>
                <span className="flex flex-wrap items-center gap-1.5 md:justify-end">
                    {stack.map((item) => (
                        <Chip
                            key={item}
                            variant="stack"
                            className={
                                stackMatches.includes(item)
                                    ? 'border border-accent-line bg-accent-soft text-accent-deep'
                                    : 'border border-hairline bg-card'
                            }
                        >
                            {item}
                        </Chip>
                    ))}
                    <Chip variant="language">{language.toUpperCase()}</Chip>
                </span>
            </span>
        </>
    );
    const jobLink = jobUrl ? (
        <Tooltip content={t('applications.open_job_page')}>
            <IconButton
                icon={ArrowUpRight}
                label={t('applications.open_job_page')}
                externalHref={jobUrl}
                onClick={(event) => event.stopPropagation()}
            />
        </Tooltip>
    ) : null;
    const tone = selected
        ? 'border-accent-line bg-accent-soft'
        : 'border-transparent bg-tile';

    if (onOpen) {
        return (
            <div className={cn(ROW, tone)}>
                {selectable && checkbox}
                <button
                    type="button"
                    onClick={onOpen}
                    className="flex min-w-0 flex-1 cursor-pointer items-center gap-row-gap text-left focus-visible:focus-ring"
                >
                    {body}
                </button>
                {jobLink}
            </div>
        );
    }

    return (
        <label
            className={cn(
                ROW,
                tone,
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            )}
        >
            {checkbox}
            {body}
            {jobLink}
        </label>
    );
}
