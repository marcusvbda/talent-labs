export type ISODateTime = string;
export type ISODate = string; // YYYY-MM-DD in the user's timezone
export type Locale = 'en' | 'pt';
export type JobLanguage = 'en' | 'pt';
export type PlanKey = 'free' | 'starter' | 'pro';
export type SendMode = 'random' | 'select' | 'review';
export type RegionKey = 'br' | 'eu' | 'row';
export type Seniority =
    | 'intern'
    | 'junior'
    | 'mid'
    | 'senior'
    | 'lead'
    | 'unknown';
export type RemoteMode =
    | 'remote_only'
    | 'remote_or_locations'
    | 'locations_only';
export type ApplicationStatus =
    | 'queued'
    | 'sending'
    | 'sent'
    | 'failed'
    | 'ambiguous';
export type SendStage =
    | 'validating_recipient'
    | 'adapting_template'
    | 'attaching_cv'
    | 'sending'
    | 'sent'
    | 'failed';
export type SubStep =
    | 'checking_company'
    | 'confirming_recipient'
    | 'checking_gmail' // validating_recipient
    | 'filling_variables'
    | 'building_html' // adapting_template
    | 'opening_cv'
    | 'checking_pdf'
    | 'attaching_file' // attaching_cv
    | 'connecting_gmail'
    | 'delivering'; // sending
export type TemplateVariable =
    | 'company'
    | 'job_title'
    | 'job_location'
    | 'job_url'
    | 'client_name'
    | 'cover_letter'
    | 'links';

export type CompanyRef = { id: number; name: string; initials: string };

export type Quota = {
    usedToday: number;
    limit: number;
    remaining: number;
    resetsAt: ISODateTime;
};

export type AccountStatus = {
    plan: { key: PlanKey; name: string; mode: SendMode; dailyLimit: number };
    quota: Quota;
    gmail: {
        state: 'connected' | 'reauthorization_required' | 'disconnected';
        accountEmail: string | null;
    };
    sending: {
        paused: boolean;
        autoPausedReason:
            | 'reauthorization_required'
            | 'repeated_failures'
            | null;
    };
    onboarding: {
        complete: boolean;
        steps: {
            key: 'basics' | 'gmail' | 'profile' | 'preferences';
            done: boolean;
        }[];
    };
    profiles: { activeLanguages: JobLanguage[] }; // active AND complete profiles
    region: RegionKey;
    country: string | null; // ISO 3166-1 alpha-2
    timezone: string | null; // IANA
    unreadNotifications: number;
};

export type JobCard = {
    id: number;
    company: CompanyRef;
    title: string;
    location: string | null;
    isRemote: boolean | null;
    language: JobLanguage;
    seniority: Seniority;
    stack: string[]; // max 6 shown
    summary: string | null; // one sentence, client-safe
    firstSeenAt: ISODateTime;
    collectedToday: boolean;
};
export type JobDetail = JobCard & {
    locations: string[];
    employmentType: string | null;
    department: string | null;
    publishedAt: ISODateTime | null;
    sourceLabel: string; // e.g. "RemoteOK" — text only, never a link
    jobUrl: string | null; // captured job page; non-null only on paid plans
};
export type JobFilters = {
    q?: string;
    language?: JobLanguage | 'all';
    seniority?: Seniority[];
    remote?: 'any' | 'remote' | 'not_remote';
    today?: boolean;
    stack?: string[];
    cursor?: string | null;
};
export type Paginated<T> = {
    data: T[];
    meta: { nextCursor: string | null; total: number };
};
export type JobsPage = Paginated<JobCard> & {
    summary: {
        total: number;
        collectedToday: number;
        lockedByLanguage: { language: JobLanguage; count: number }[];
    };
};

export type ApplicationItem = {
    id: number;
    company: CompanyRef;
    title: string | null;
    language: JobLanguage;
    origin: 'auto' | 'manual';
    status: ApplicationStatus;
    stage: SendStage | null;
    subStep: SubStep | null;
    lastError: string | null; // client-safe sentence, already translated by the backend
    queuedAt: ISODateTime;
    scheduledFor: ISODateTime | null;
    sentAt: ISODateTime | null;
    jobUrl: string | null; // official job page; non-null only when status === 'sent'
};
export type ApplicationDetail = ApplicationItem & {
    subject: string;
    body: string; // client-safe (job links replaced by the chip token)
    cvFileName: string | null;
};
export type ApplicationFilters = {
    status?: 'all' | 'in_progress' | 'sent' | 'attention'; // in_progress = queued+sending, attention = failed+ambiguous
    language?: JobLanguage | 'all';
    q?: string;
    cursor?: string | null;
};

export type DashboardPeriod = 'today' | 'week' | 'month';
export type DashboardData = {
    period: DashboardPeriod;
    kpis: {
        collected: { value: number; previous: number };
        sent: { value: number; previous: number };
        totalSent: number;
        firstSentAt: ISODateTime | null;
    };
    hero: {
        sentToday: number;
        failedToday: number;
        queued: number;
        nextSendAt: ISODateTime | null;
        lastDays: { date: ISODate; count: number }[]; // the 5 days before today, oldest first
    };
    matches: { total: number; newToday: number; items: JobCard[] }; // items: up to 4, newest first
    activity: ApplicationItem[]; // up to 5, newest first
};
export type ChartData = {
    range: '14d' | '30d';
    days: { date: ISODate; count: number }[]; // oldest first, includes today
    averagePerActiveDay: number; // days with count > 0, one decimal
    limit: number;
};
export type LiveSending = {
    state:
        | 'idle'
        | 'sending'
        | 'waiting'
        | 'paused'
        | 'limit_reached'
        | 'outside_window';
    current: ApplicationItem | null; // the application in status 'sending'
    progress: { index: number; total: number } | null; // index = sent today + 1, total = daily limit
    queue: ApplicationItem[]; // next 3 queued, by scheduledFor
    queuedCount: number;
    nextSendAt: ISODateTime | null;
    waitStartedAt: ISODateTime | null; // for the countdown bar fill
    estimatedFinishAt: ISODateTime | null; // when the current queue should be done
    spacing: { minSeconds: number; maxSeconds: number };
    window: {
        start: string;
        end: string;
        weekdaysOnly: boolean;
        timezone: string;
    } | null;
};

export type ReviewDraft = {
    job: JobCard;
    language: JobLanguage;
    subject: string;
    body: string; // rendered, but `{{ job_url }}` kept as a token shown as a chip
    cvFileName: string;
    recipientLabel: string; // e.g. "Klarwerk careers team" — never the address
};
export type QueueResult = {
    queued: {
        jobId: number;
        applicationId: number;
        scheduledFor: ISODateTime;
    }[];
    rejected: { jobId: number; reason: string }[]; // reason: client-safe, translated
    quota: Quota;
};

export type Preferences = {
    titles: string[];
    seniorities: Seniority[];
    stack: string[];
    locations: string[];
    remoteMode: RemoteMode;
    excludeWords: string[];
};
export type PreferencesPreview = {
    matchCount: number;
    byLanguage: Record<JobLanguage, number>;
};

export type ProfileLink = { label: string; url: string };

export type ApplicationProfile = {
    language: JobLanguage;
    active: boolean;
    cv: { fileName: string; sizeBytes: number; uploadedAt: ISODateTime } | null;
    emailSubject: string;
    emailBody: string;
    coverLetter: string;
    links: ProfileLink[];
    complete: boolean;
    missing: ('cv' | 'subject' | 'body')[];
};
export type ProfilesData = {
    profiles: ApplicationProfile[]; // only created languages
    variables: TemplateVariable[];
    unlockCounts: Record<JobLanguage, number>; // pool jobs per language, ignoring the language rule
};
export type TemplatePreview = {
    subject: string;
    body: string;
    sampleJob: { company: string; title: string };
};

export type PlanOffer = {
    key: PlanKey;
    name: string;
    mode: SendMode;
    dailyLimit: number;
    price: number;
    currency: string;
    interval: 'month';
    highlighted: boolean;
};
export type PlansData = {
    region: RegionKey;
    regions: { key: RegionKey; currency: string }[];
    plans: PlanOffer[];
    current: PlanKey;
    billingAvailable: boolean; // true when billing is enabled and Stripe keys are set
    contactEmail: string | null;
    hasSubscription: boolean;
    checkoutBlocked: boolean;
};

export type BillingSummary = {
    hasCustomer: boolean;
    source: 'manual' | 'stripe';
    plan: { key: PlanKey; name: string };
    status: string | null;
    renewsAt: string | null;
    endsAt: string | null;
};

export type CheckoutResponse = { url: string };

export type NotificationType =
    | 'gmail_reauthorization_required'
    | 'application_failed'
    | 'daily_limit_reached'
    | 'jobs_collected'
    | 'sending_auto_paused'
    | 'payment_failed';
export type NotificationItem = {
    id: string;
    type: NotificationType;
    data: Record<string, string | number | null>;
    readAt: ISODateTime | null;
    createdAt: ISODateTime;
};

export type Account = {
    name: string;
    email: string;
    locale: Locale;
    timezone: string | null;
    country: string | null;
    region: RegionKey;
};
export type OnboardingBasics = {
    country: string;
    locale: Locale;
    timezone: string;
};

export type InviteCheck =
    | { valid: true; email: string | null }
    | { valid: false };
