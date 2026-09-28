import { router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, UserRound } from 'lucide-react';
import { useLocaleEntries } from '@/components/patterns/language-switcher';
import { Avatar } from '@/components/ui/avatar';
import { Chip } from '@/components/ui/chip';
import { Menu } from '@/components/ui/menu';
import { useT } from '@/i18n/i18n-provider';
import { account, logout } from '@/routes';
import type { PlanKey } from '@/types/plans';
import type { SharedProps } from '@/types/shared';

export function UserMenu({ plan }: { plan?: PlanKey }) {
    const { t } = useT();
    const { auth } = usePage<SharedProps>().props;
    const user = auth?.user ?? null;
    const localeEntries = useLocaleEntries(`${t('user_menu.language')}: `);

    return (
        <Menu
            items={[
                {
                    label: t('user_menu.account'),
                    icon: UserRound,
                    href: account().url,
                },
                ...localeEntries,
                {
                    label: t('user_menu.logout'),
                    icon: LogOut,
                    onSelect: () => router.post(logout().url),
                },
            ]}
            trigger={
                <button
                    type="button"
                    aria-label={t('user_menu.open')}
                    className="inline-flex h-control-lg items-center gap-3 rounded-full bg-tile p-1.5 transition-colors hover:bg-hairline focus-visible:focus-ring desk:pr-4"
                >
                    <Avatar initials={user?.initials ?? '?'} size="lg" />
                    <span className="hidden max-w-40 truncate text-label-sm text-ink desk:inline">
                        {user?.name ?? ''}
                    </span>
                    {plan ? (
                        <Chip
                            variant="plan"
                            className="hidden desk:inline-flex"
                        >
                            {t(`plans.${plan}.name`)}
                        </Chip>
                    ) : null}
                    <ChevronDown
                        aria-hidden="true"
                        size={18}
                        strokeWidth={1.8}
                        className="hidden shrink-0 desk:block"
                    />
                </button>
            }
        />
    );
}
