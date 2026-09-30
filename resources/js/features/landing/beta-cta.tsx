import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { register } from '@/routes';

type ButtonStyleProps = Pick<
    ComponentProps<typeof Button>,
    'variant' | 'size' | 'fullWidth'
>;

export function BetaCta({
    betaClosed,
    variant,
    size,
    fullWidth,
}: { betaClosed: boolean } & ButtonStyleProps) {
    const { t } = useT();

    if (betaClosed) {
        return (
            <Button inert variant={variant} size={size} fullWidth={fullWidth}>
                {t('landing.cta.closed')}
            </Button>
        );
    }

    return (
        <Button
            href={register().url}
            variant={variant}
            size={size}
            fullWidth={fullWidth}
        >
            {t('landing.cta.open')}
        </Button>
    );
}
