import type { ReactNode } from 'react';

export function StyleguideSection({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-title`}
            className="scroll-mt-24"
        >
            <h2
                id={`${id}-title`}
                className="mb-6 text-card-title-sm md:text-card-title"
            >
                {title}
            </h2>
            <div className="flex flex-col gap-gap">{children}</div>
        </section>
    );
}
