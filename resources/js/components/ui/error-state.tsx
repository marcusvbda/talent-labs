import { TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

export function ErrorState({
    title,
    description,
    onRetry,
    className,
}: {
    title?: string;
    description?: string;
    onRetry?: () => void;
    className?: string;
}) {
    const { t } = useT();

    return (
        <div
            role="alert"
            className={cn(
                'flex flex-col items-center gap-3 px-4 py-10 text-center',
                className,
            )}
        >
            <span className="flex size-14 items-center justify-center rounded-full bg-danger-bg text-danger-text">
                <TriangleAlert aria-hidden="true" strokeWidth={1.8} size={24} />
            </span>
            <h3 className="text-card-title-sm text-ink">
                {title ?? t('states.error.title')}
            </h3>
            <p className="max-w-sm text-body text-muted">
                {description ?? t('states.error.description')}
            </p>
            {onRetry && (
                <Button
                    variant="secondary-tile"
                    size="sm"
                    onClick={onRetry}
                    className="mt-2"
                >
                    {t('states.error.retry')}
                </Button>
            )}
        </div>
    );
}
