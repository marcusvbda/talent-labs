import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/patterns/page-header';
import { TopBar } from '@/components/patterns/top-bar';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { StyleguideSection } from './styleguide-section';

const Group = ({
    title,
    wide = false,
    children,
}: {
    title: string;
    wide?: boolean;
    children: ReactNode;
}) => (
    <div className={cn('flex flex-col gap-3', wide && 'desk:-mx-24')}>
        <h3 className="text-label-sm text-muted">{title}</h3>
        <div className="rounded-card-sm bg-shell p-card-sm">{children}</div>
    </div>
);

/** Turns **text** segments of a translated string into <b> elements. */
const bold = (text: string): ReactNode =>
    text
        .split(/\*\*(.+?)\*\*/)
        .map((part, index) =>
            index % 2 === 1 ? <b key={index}>{part}</b> : part,
        );

export function ShellSection() {
    const { t } = useT();

    const nav = [
        {
            key: 'dashboard',
            label: t('nav.dashboard'),
            href: '#shell',
            active: true,
        },
        { key: 'jobs', label: t('nav.jobs'), href: '#shell' },
        { key: 'applications', label: t('nav.applications'), href: '#shell' },
        { key: 'profiles', label: t('nav.profiles'), href: '#shell' },
        { key: 'preferences', label: t('nav.preferences'), href: '#shell' },
        { key: 'plans', label: t('nav.plans'), href: '#shell' },
    ];

    return (
        <StyleguideSection id="shell" title={t('styleguide.shell.title')}>
            <Group wide title={t('styleguide.shell.top_bar')}>
                <TopBar nav={nav} plan="starter" notificationsDot />
            </Group>
            <Group wide title={t('styleguide.shell.top_bar_plain')}>
                <TopBar nav={nav} />
            </Group>
            <Group title={t('styleguide.shell.page_header')}>
                <PageHeader
                    eyebrow={t('styleguide.shell.demo_eyebrow')}
                    title={t('styleguide.shell.demo_title')}
                    summary={bold(
                        t('styleguide.shell.demo_summary', {
                            sent: 18,
                            queued: 61,
                        }),
                    )}
                    actions={
                        <Button iconLeft={Plus}>
                            {t('styleguide.shell.demo_action')}
                        </Button>
                    }
                />
            </Group>
            <Group title={t('styleguide.shell.page_header_minimal')}>
                <PageHeader title={t('styleguide.shell.demo_title')} />
            </Group>
        </StyleguideSection>
    );
}
