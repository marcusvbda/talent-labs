import { Button } from '@/components/ui/button';
import { Menu } from '@/components/ui/menu';
import { useT } from '@/i18n/i18n-provider';
import type { RegionKey } from '@/types/contracts';

export const RegionMenu = ({
    region,
    regions,
    onChange,
}: {
    region: RegionKey;
    regions: RegionKey[];
    onChange: (region: RegionKey) => void;
}) => {
    const { t } = useT();

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
            <p className="text-body text-muted">
                {t('plans.region', { region: t(`plans.region.${region}`) })}
            </p>
            <Menu
                trigger={
                    <Button variant="secondary-tile" size="sm">
                        {t('plans.change_region')}
                    </Button>
                }
                items={regions.map((key) => ({
                    label: t(`plans.region.${key}`),
                    onSelect: () => onChange(key),
                }))}
            />
        </div>
    );
};
