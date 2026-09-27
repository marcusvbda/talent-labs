import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/patterns/page-header';
import { Card } from '@/components/ui/card';
import { ErrorState } from '@/components/ui/error-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useProfiles } from '@/data/hooks/use-profiles';
import { ProfilesWorkspace } from '@/features/profiles/profiles-workspace';
import { useT } from '@/i18n/i18n-provider';
import { AppGrid, AppLayout } from '@/layouts/app-layout';

const ProfilesSkeleton = () => {
    const { t } = useT();

    return (
        <div
            role="status"
            aria-busy="true"
            aria-label={t('common.loading')}
            className="flex flex-col gap-4"
        >
            <div className="flex gap-2 p-1">
                {[0, 1, 2].map((index) => (
                    <Skeleton
                        key={index}
                        shape="line"
                        className="h-control-xs w-28"
                    />
                ))}
            </div>
            <AppGrid>
                <Card
                    tone="light"
                    className="flex min-w-0 flex-col gap-4 lg:col-span-7"
                >
                    <Skeleton shape="line" className="h-6 w-1/4" />
                    <Skeleton shape="block" />
                    <Skeleton shape="line" className="h-10" />
                    <Skeleton shape="block" className="h-40" />
                </Card>
                <Card
                    tone="light"
                    className="flex min-w-0 flex-col gap-4 lg:col-span-5"
                >
                    <Skeleton shape="line" className="h-6 w-1/3" />
                    <Skeleton shape="block" className="h-40" />
                    <Skeleton shape="line" className="w-2/3" />
                </Card>
            </AppGrid>
        </div>
    );
};

export default function Profiles() {
    const { t } = useT();
    const { data, isError, refetch } = useProfiles();

    return (
        <AppLayout>
            <Head title={t('profiles.title')} />
            {isError ? (
                <>
                    <PageHeader title={t('profiles.title')} />
                    <Card tone="light">
                        <ErrorState onRetry={() => void refetch()} />
                    </Card>
                </>
            ) : !data ? (
                <>
                    <PageHeader title={t('profiles.title')} />
                    <ProfilesSkeleton />
                </>
            ) : (
                <ProfilesWorkspace data={data} />
            )}
        </AppLayout>
    );
}
