import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';

export const StepFooter = ({
    onBack,
    onContinue,
    continueLabel,
    disabled,
    loading,
}: {
    onBack?: () => void;
    onContinue: () => void;
    continueLabel?: string;
    disabled?: boolean;
    loading?: boolean;
}) => {
    const { t } = useT();

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-hairline pt-6">
            {onBack ? (
                <Button variant="secondary-tile" onClick={onBack}>
                    {t('onboarding.back')}
                </Button>
            ) : (
                <span />
            )}
            <Button loading={loading} disabled={disabled} onClick={onContinue}>
                {continueLabel ?? t('onboarding.continue')}
            </Button>
        </div>
    );
};
