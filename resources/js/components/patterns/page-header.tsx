import type { ReactNode } from 'react';

export function PageHeader({
    eyebrow,
    title,
    summary,
    actions,
}: {
    eyebrow?: ReactNode;
    title: ReactNode;
    summary?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between md:gap-gap">
            <div className="min-w-0">
                {eyebrow ? (
                    <div className="mb-2 text-label text-muted">{eyebrow}</div>
                ) : null}
                <h1 className="text-display-sm wrap-break-word md:text-display">
                    {title}
                </h1>
                {summary ? (
                    <p className="mt-3.5 text-row-title-sm font-normal text-muted [&_b]:font-medium [&_b]:text-ink [&_strong]:font-medium [&_strong]:text-ink">
                        {summary}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-col items-center gap-3.5 md:flex-row">
                    {actions}
                </div>
            ) : null}
        </section>
    );
}
