import { CompanyLogo } from '@/components/patterns/company-logo';
import { Chip } from '@/components/ui/chip';
import type { Locale } from '@/types/shared';

export function QueueRow({
    company,
    title,
    meta,
    language,
    eta,
}: {
    company: string;
    title: string;
    meta: string;
    language: Locale;
    eta: string;
}) {
    return (
        <div className="flex items-center gap-3.5 rounded-queue bg-dark-2 py-3 pr-4 pl-3">
            <CompanyLogo name={company} size="sm" />
            <div className="min-w-0 flex-1">
                <p className="truncate text-label font-medium text-white">
                    {title}
                </p>
                <p className="truncate text-queue-meta text-dark-muted">
                    {meta}
                </p>
            </div>
            <Chip variant="language" className="bg-dark-line text-dark-soft">
                {language.toUpperCase()}
            </Chip>
            <span className="min-w-16 shrink-0 text-right text-queue-eta text-dark-soft tabular-nums">
                {eta}
            </span>
        </div>
    );
}
