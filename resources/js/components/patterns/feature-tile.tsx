import type { LucideIcon } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { StatusDisc } from '@/components/ui/status-disc';

export function FeatureTile({
    icon,
    title,
    description,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <Card tone="light" className="flex h-full flex-col gap-4">
            <StatusDisc status="icon" tint="orange" size="lg" icon={icon} />
            <div className="flex flex-col gap-1">
                <h3 className="text-row-title-sm text-ink md:text-row-title">
                    {title}
                </h3>
                <p className="text-body text-muted">{description}</p>
            </div>
        </Card>
    );
}
