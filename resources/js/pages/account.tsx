import { Head } from '@inertiajs/react';
import { DataCard } from '@/components/patterns/data-card';
import { PageHeader } from '@/components/patterns/page-header';
import { endpoints } from '@/data/endpoints';
import { useAccount } from '@/data/hooks/use-account';
import { useAccountStatus } from '@/data/hooks/use-account-status';
import { DangerZone } from '@/features/account/danger-zone';
import { GmailCard } from '@/features/account/gmail-card';
import { LanguageRegionCard } from '@/features/account/language-region-card';
import { PasswordCard } from '@/features/account/password-card';
import { ProfileCard } from '@/features/account/profile-card';
import { useT } from '@/i18n/i18n-provider';
import { AppLayout } from '@/layouts/app-layout';

export default function AccountPage() {
    const { t } = useT();
    const account = useAccount();
    const status = useAccountStatus();

    return (
        <AppLayout>
            <Head title={t('account.page.title')} />
            <PageHeader title={t('account.page.title')} />
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                {account.data ? (
                    <ProfileCard account={account.data} />
                ) : (
                    <DataCard
                        title={t('account.profile.title')}
                        state={account.isError ? 'error' : 'loading'}
                        onRetry={() => void account.refetch()}
                    />
                )}
                {account.data ? (
                    <LanguageRegionCard account={account.data} />
                ) : (
                    <DataCard
                        title={t('account.language_region.title')}
                        state={account.isError ? 'error' : 'loading'}
                        onRetry={() => void account.refetch()}
                    />
                )}
                {status.data ? (
                    <GmailCard gmail={status.data.gmail} />
                ) : (
                    <DataCard
                        title={t('account.gmail.title')}
                        state={status.isError ? 'error' : 'loading'}
                        onRetry={() => void status.refetch()}
                    />
                )}
                <PasswordCard />
                <DataCard title={t('account.data.title')}>
                    <a
                        href={endpoints.accountExport().url}
                        download
                        className="inline-flex h-control-md items-center rounded-full bg-tile px-6 text-label-sm text-ink transition-colors hover:bg-hairline focus-visible:focus-ring"
                    >
                        {t('account.data.download')}
                    </a>
                </DataCard>
                <DangerZone />
            </div>
        </AppLayout>
    );
}
