import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

type SpinnerSize = 'sm' | 'md';
type SpinnerTone = 'ink' | 'white' | 'accent';

const SIZES: Record<SpinnerSize, string> = {
    sm: 'size-4',
    md: 'size-5',
};

const TONES: Record<SpinnerTone, string> = {
    ink: 'text-ink',
    white: 'text-white',
    accent: 'text-accent',
};

export function Spinner({
    size = 'md',
    tone = 'ink',
    label,
    className,
}: {
    size?: SpinnerSize;
    tone?: SpinnerTone;
    label?: string;
    className?: string;
}) {
    const { t } = useT();

    return (
        <span
            role="status"
            aria-label={label ?? t('common.loading')}
            className={cn(
                'inline-block shrink-0 animate-spin-ring rounded-full border-2 border-current border-t-transparent',
                SIZES[size],
                TONES[tone],
                className,
            )}
        />
    );
}
