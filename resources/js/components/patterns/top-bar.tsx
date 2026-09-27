import { Menu, Search } from 'lucide-react';
import { useState } from 'react';
import { LanguageSwitcher } from '@/components/patterns/language-switcher';
import { Logo } from '@/components/patterns/logo';
import { MobileNav } from '@/components/patterns/mobile-nav';
import { NavPills } from '@/components/patterns/nav-pills';
import type { NavPillItem } from '@/components/patterns/nav-pills';
import { UserMenu } from '@/components/patterns/user-menu';
import { IconButton } from '@/components/ui/icon-button';
import { Kbd } from '@/components/ui/kbd';
import { NotificationsPopover } from '@/features/notifications/notifications-popover';
import { usePalette } from '@/features/palette/use-palette';
import { useT } from '@/i18n/i18n-provider';
import type { PlanKey } from '@/types/plans';

export function TopBar({
    nav,
    plan,
    notificationsDot = false,
}: {
    nav: NavPillItem[];
    plan?: PlanKey;
    notificationsDot?: boolean;
}) {
    const { t } = useT();
    const [menuOpen, setMenuOpen] = useState(false);
    const palette = usePalette();

    return (
        <header className="flex items-center gap-2.5 md:gap-3.5">
            <Logo variant="mark" className="shrink-0 2xl:hidden" />
            <Logo className="mr-2 hidden shrink-0 gap-3 2xl:inline-flex" />
            <div className="hidden min-w-0 shrink md:block">
                <NavPills items={nav} />
            </div>
            <div className="min-w-0 flex-1" />
            <button
                type="button"
                onClick={() => palette.setOpen(true)}
                className="hidden h-control-lg w-41 shrink-0 items-center gap-2.5 rounded-full bg-card px-5.5 text-label text-faint transition-colors hover:bg-tile focus-visible:focus-ring 2xl:flex"
            >
                <Search aria-hidden="true" size={20} strokeWidth={1.8} />
                <span className="min-w-0 flex-1 truncate text-left">
                    {t('topbar.search')}
                </span>
                <Kbd>⌘K</Kbd>
            </button>
            <IconButton
                icon={Search}
                label={t('topbar.search')}
                size={60}
                bg="white"
                className="hidden md:inline-grid 2xl:hidden"
                onClick={() => palette.setOpen(true)}
            />
            <NotificationsPopover dot={notificationsDot} />
            <div className="hidden xl:block">
                <LanguageSwitcher />
            </div>
            <UserMenu plan={plan} />
            <IconButton
                icon={Menu}
                label={t('topbar.menu')}
                size={60}
                bg="white"
                className="md:hidden"
                aria-haspopup="dialog"
                aria-expanded={menuOpen}
                onClick={() => setMenuOpen(true)}
            />
            <MobileNav
                open={menuOpen}
                onClose={() => setMenuOpen(false)}
                nav={nav}
                plan={plan}
            />
        </header>
    );
}
