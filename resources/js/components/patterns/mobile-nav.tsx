import { Link, router } from '@inertiajs/react';
import { Check, LogOut } from 'lucide-react';
import { useLocaleEntries } from '@/components/patterns/language-switcher';
import { navItemState } from '@/components/patterns/nav-pills';
import type { NavPillItem } from '@/components/patterns/nav-pills';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { Sheet } from '@/components/ui/sheet';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import type { PlanKey } from '@/types/plans';

const TONES = {
    active: 'bg-ink text-white',
    normal: 'text-ink hover:bg-tile',
    disabled: 'cursor-not-allowed text-muted opacity-60',
};

const ITEM =
    'flex h-control-sm w-full items-center gap-3 rounded-full px-5 text-label transition-colors focus-visible:focus-ring';

export function MobileNav({
    open,
    onClose,
    nav,
    plan,
}: {
    open: boolean;
    onClose: () => void;
    nav: NavPillItem[];
    plan?: PlanKey;
}) {
    const { t } = useT();
    const localeEntries = useLocaleEntries();

    return (
        <Sheet
            open={open}
            onClose={onClose}
            side="right"
            title={t('topbar.menu_title')}
        >
            <nav aria-label={t('topbar.menu_title')}>
                <ul className="flex flex-col gap-1">
                    {nav.map((item) => {
                        const { inactive, tone: toneKey } = navItemState(item);
                        const tone = TONES[toneKey];

                        return (
                            <li key={item.key}>
                                {inactive ? (
                                    <span
                                        aria-disabled="true"
                                        aria-current={
                                            item.active ? 'page' : undefined
                                        }
                                        className={cn(ITEM, tone)}
                                    >
                                        {item.label}
                                    </span>
                                ) : (
                                    <Link
                                        href={item.href as string}
                                        aria-current={
                                            item.active ? 'page' : undefined
                                        }
                                        onClick={onClose}
                                        className={cn(ITEM, tone)}
                                    >
                                        {item.label}
                                    </Link>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </nav>
            <div className="flex flex-col gap-2">
                <h3 className="text-label-sm text-muted">
                    {t('language_switcher.label')}
                </h3>
                <ul className="flex flex-wrap gap-2">
                    {localeEntries.map((entry) => (
                        <li key={entry.label}>
                            <button
                                type="button"
                                aria-pressed={Boolean(entry.icon)}
                                onClick={() => entry.onSelect?.()}
                                className={cn(
                                    'inline-flex h-control-xs items-center gap-2 rounded-full px-5 text-label-sm transition-colors focus-visible:focus-ring',
                                    entry.icon
                                        ? 'bg-ink text-white'
                                        : 'bg-tile text-ink hover:bg-hairline',
                                )}
                            >
                                {entry.icon ? (
                                    <Check
                                        aria-hidden="true"
                                        size={18}
                                        strokeWidth={1.8}
                                    />
                                ) : null}
                                {entry.label}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
            {plan ? (
                <div className="flex items-center justify-between gap-3">
                    <span className="text-label-sm text-muted">
                        {t('topbar.plan')}
                    </span>
                    <Chip variant="plan">{t(`plans.${plan}.name`)}</Chip>
                </div>
            ) : null}
            <Button
                variant="secondary-tile"
                iconLeft={LogOut}
                fullWidth
                onClick={() => router.post(logout().url)}
            >
                {t('user_menu.logout')}
            </Button>
        </Sheet>
    );
}
