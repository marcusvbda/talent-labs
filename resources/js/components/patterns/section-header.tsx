import type { ReactNode } from 'react';

export const SectionHeader = ({
    title,
    action,
}: {
    title: string;
    action?: ReactNode;
}) => (
    <div className="flex items-center justify-between gap-4">
        <h2 className="text-card-title-sm md:text-card-title">{title}</h2>
        {action}
    </div>
);
