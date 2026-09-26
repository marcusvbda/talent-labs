import type { ReactNode } from 'react';
import { LanguageSwitcher } from '@/components/patterns/language-switcher';
import { NavPills } from '@/components/patterns/nav-pills';
import { UserMenu } from '@/components/patterns/user-menu';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const Group = ({ title, children }: { title: string; children: ReactNode }) => (
    <div className="flex flex-col gap-3">
        <h3 className="text-label-sm text-muted">{title}</h3>
        <div className="flex flex-wrap items-center gap-4 rounded-card-sm bg-card p-card-sm">
            {children}
        </div>
    </div>
);

export function NavigationSection() {
    const { t } = useT();

    return (
        <StyleguideSection
            id="navigation"
            title={t('styleguide.navigation.title')}
        >
            <Group title={t('styleguide.navigation.pills')}>
                <NavPills
                    items={[
                        {
                            key: 'overview',
                            label: t('styleguide.navigation.demo_overview'),
                            href: '#navigation',
                            active: true,
                        },
                        {
                            key: 'saved',
                            label: t('styleguide.navigation.demo_saved'),
                            href: '#navigation',
                        },
                        {
                            key: 'archived',
                            label: t('styleguide.navigation.demo_archived'),
                            disabled: true,
                        },
                    ]}
                />
            </Group>
            <Group title={t('styleguide.navigation.language')}>
                <LanguageSwitcher />
            </Group>
            <Group title={t('styleguide.navigation.user_menu')}>
                <div className="flex flex-col gap-2">
                    <span className="text-chip text-muted">
                        {t('styleguide.navigation.with_plan')}
                    </span>
                    <UserMenu plan="pro" />
                </div>
                <div className="flex flex-col gap-2">
                    <span className="text-chip text-muted">
                        {t('styleguide.navigation.without_plan')}
                    </span>
                    <UserMenu />
                </div>
            </Group>
        </StyleguideSection>
    );
}
