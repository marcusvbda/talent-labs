import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { useT } from '@/i18n/i18n-provider';

export type AiLetterState =
    | 'idle'
    | 'writing'
    | 'ready'
    | 'manual'
    | 'quota'
    | 'off';

export const AiLetterBar = ({
    state,
    reason,
    edited,
    hasAiBody,
    regenerateDisabled,
    onRegenerate,
    onUse,
}: {
    state: AiLetterState;
    reason: 'quota' | 'failed' | null;
    edited: boolean;
    hasAiBody: boolean;
    regenerateDisabled: boolean;
    onRegenerate: () => void;
    onUse: () => void;
}) => {
    const { t } = useT();

    const regenerate = (disabled: boolean) => (
        <Button
            variant="secondary-tile"
            size="sm"
            disabled={disabled}
            onClick={onRegenerate}
        >
            {t('review.ai.regenerate')}
        </Button>
    );

    if (state === 'writing') {
        return (
            <div
                role="status"
                className="flex flex-wrap items-center gap-2 text-chip text-muted"
            >
                <Chip variant="plan">{t('review.ai.label')}</Chip>
                <span>{t('review.ai.writing')}</span>
            </div>
        );
    }

    if (state === 'ready') {
        return (
            <div className="flex flex-wrap items-center gap-2">
                {edited ? (
                    hasAiBody && (
                        <>
                            <Button
                                variant="secondary-tile"
                                size="sm"
                                onClick={onUse}
                            >
                                {t('review.ai.use')}
                            </Button>
                            <span className="text-chip text-muted">
                                {t('review.ai.use_hint')}
                            </span>
                        </>
                    )
                ) : (
                    <Chip variant="plan">{t('review.ai.label')}</Chip>
                )}
                {regenerate(regenerateDisabled)}
            </div>
        );
    }

    if (state === 'quota' || state === 'manual') {
        const quota = state === 'quota' || reason === 'quota';

        return (
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-chip text-muted">
                    {t(quota ? 'review.ai.quota' : 'review.ai.failed')}
                </span>
                {regenerate(quota || regenerateDisabled)}
            </div>
        );
    }

    return null;
};
