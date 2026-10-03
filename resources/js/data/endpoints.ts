import AccountController from '@/actions/App/Http/Controllers/Client/Internal/AccountController';
import AccountExportController from '@/actions/App/Http/Controllers/Client/Internal/AccountExportController';
import AccountStatusController from '@/actions/App/Http/Controllers/Client/Internal/AccountStatusController';
import ApplicationsController from '@/actions/App/Http/Controllers/Client/Internal/ApplicationsController';
import BillingController from '@/actions/App/Http/Controllers/Client/Internal/BillingController';
import ChartController from '@/actions/App/Http/Controllers/Client/Internal/ChartController';
import DashboardController from '@/actions/App/Http/Controllers/Client/Internal/DashboardController';
import JobsController from '@/actions/App/Http/Controllers/Client/Internal/JobsController';
import NotificationsController from '@/actions/App/Http/Controllers/Client/Internal/NotificationsController';
import OnboardingBasicsController from '@/actions/App/Http/Controllers/Client/Internal/OnboardingBasicsController';
import PlansController from '@/actions/App/Http/Controllers/Client/Internal/PlansController';
import PreferencesController from '@/actions/App/Http/Controllers/Client/Internal/PreferencesController';
import ProfileCvController from '@/actions/App/Http/Controllers/Client/Internal/ProfileCvController';
import ProfilePreviewController from '@/actions/App/Http/Controllers/Client/Internal/ProfilePreviewController';
import ProfilesController from '@/actions/App/Http/Controllers/Client/Internal/ProfilesController';
import ReviewController from '@/actions/App/Http/Controllers/Client/Internal/ReviewController';
import SendingController from '@/actions/App/Http/Controllers/Client/Internal/SendingController';
import type {
    ApplicationFilters,
    ChartData,
    DashboardPeriod,
    JobFilters,
    JobLanguage,
    RegionKey,
} from '@/types/contracts';

export type Endpoint = {
    url: string;
    method: 'get' | 'post' | 'put' | 'delete';
};

type QueryValue = string | number | boolean | null | undefined;

const withQuery = (
    path: string,
    params: Record<string, QueryValue | QueryValue[]>,
): string => {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach((item) => {
                if (item !== undefined && item !== null) {
                    search.append(`${key}[]`, String(item));
                }
            });

            return;
        }

        if (value === undefined || value === null) {
            return;
        }

        search.append(key, String(value));
    });

    const query = search.toString();

    return query === '' ? path : `${path}?${query}`;
};

const omitDefault = <T extends string>(
    value: T | undefined,
    defaultValue: T,
): T | undefined => (value === defaultValue ? undefined : value);

// Entries below use generated Wayfinder helpers from
// resources/js/actions/App/Http/Controllers/Client/Internal/*. The remaining
// entries further down are typed placeholders for a later spec.
export const endpoints = {
    accountStatus: (): Endpoint => AccountStatusController(),
    dashboard: (period: DashboardPeriod): Endpoint => {
        const base = DashboardController.get();

        return { url: withQuery(base.url, { period }), method: base.method };
    },
    chart: (range: ChartData['range']): Endpoint => {
        const base = ChartController.get();

        return { url: withQuery(base.url, { range }), method: base.method };
    },
    sending: (): Endpoint => SendingController.show(),
    pauseSending: (): Endpoint => SendingController.pause(),
    resumeSending: (): Endpoint => SendingController.resume(),
    jobs: (filters: JobFilters = {}): Endpoint => {
        const base = JobsController.index();

        return {
            url: withQuery(base.url, {
                q: filters.q || undefined,
                language: omitDefault(filters.language, 'all'),
                seniority: filters.seniority,
                remote: omitDefault(filters.remote, 'any'),
                today: filters.today || undefined,
                stack: filters.stack,
                cursor: filters.cursor,
            }),
            method: base.method,
        };
    },
    job: (id: number | string): Endpoint => JobsController.show(id),
    queueApplications: (): Endpoint => ApplicationsController.store(),
    queueRandom: (): Endpoint => ApplicationsController.random(),
    reviewDrafts: (): Endpoint => ReviewController.drafts(),
    queueReviewed: (): Endpoint => ReviewController.reviewed(),
    queueReviewedBatch: (): Endpoint => ReviewController.reviewedBatch(),
    applications: (filters: ApplicationFilters = {}): Endpoint => {
        const base = ApplicationsController.index();

        return {
            url: withQuery(base.url, {
                status: omitDefault(filters.status, 'all'),
                language: omitDefault(filters.language, 'all'),
                q: filters.q || undefined,
                cursor: filters.cursor,
            }),
            method: base.method,
        };
    },
    applicationCounts: (): Endpoint => ApplicationsController.counts(),
    application: (id: number | string): Endpoint =>
        ApplicationsController.show(typeof id === 'string' ? Number(id) : id),
    preferences: (): Endpoint => PreferencesController.show(),
    savePreferences: (): Endpoint => PreferencesController.update(),
    preferencesPreview: (): Endpoint => PreferencesController.preview(),
    profiles: (): Endpoint => ProfilesController.index(),
    createProfile: (): Endpoint => ProfilesController.store(),
    saveProfile: (language: JobLanguage): Endpoint =>
        ProfilesController.update(language),
    deleteProfile: (language: JobLanguage): Endpoint =>
        ProfilesController.destroy(language),
    uploadCv: (language: JobLanguage): Endpoint =>
        ProfileCvController.store(language),
    deleteCv: (language: JobLanguage): Endpoint =>
        ProfileCvController.destroy(language),
    templatePreview: (language: JobLanguage): Endpoint =>
        ProfilePreviewController(language),
    plans: (region?: RegionKey): Endpoint => {
        const base = PlansController();

        return { url: withQuery(base.url, { region }), method: base.method };
    },
    billing: (): Endpoint => BillingController.show(),
    billingCheckout: (): Endpoint => BillingController.checkout(),
    billingPortal: (): Endpoint => BillingController.portal(),
    notifications: (): Endpoint => NotificationsController.index(),
    markAllNotificationsRead: (): Endpoint => NotificationsController.readAll(),
    account: (): Endpoint => AccountController.show(),
    saveAccount: (): Endpoint => AccountController.update(),
    changePassword: (): Endpoint => AccountController.updatePassword(),
    deleteAccount: (): Endpoint => AccountController.destroy(),
    saveOnboardingBasics: (): Endpoint => OnboardingBasicsController(),
    accountExport: (): Endpoint => AccountExportController(),
};
