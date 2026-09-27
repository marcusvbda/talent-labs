import { fixtureState } from '@/data/fixtures/state';
import type {
    ApplicationFilters,
    ApplicationItem,
    ApplicationStatus,
    Paginated,
} from '@/types/contracts';

export const APPLICATIONS_PAGE_SIZE = 20;

export type ApplicationCounts = {
    all: number;
    in_progress: number;
    sent: number;
    attention: number;
};

const STATUS_GROUPS: Record<
    NonNullable<ApplicationFilters['status']>,
    ApplicationStatus[] | null
> = {
    all: null,
    in_progress: ['queued', 'sending'],
    attention: ['failed', 'ambiguous'],
    sent: ['sent'],
};

const fold = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

const latest = (item: ApplicationItem): number =>
    Math.max(
        item.sentAt ? new Date(item.sentAt).getTime() : 0,
        new Date(item.queuedAt).getTime(),
    );

const newestFirst = (a: ApplicationItem, b: ApplicationItem): number =>
    latest(b) - latest(a) || b.id - a.id;

const matchesQuery = (item: ApplicationItem, q: string): boolean => {
    const terms = fold(q).split(/\s+/).filter(Boolean);
    const haystack = fold(`${item.company.name} ${item.title ?? ''}`);

    return terms.every((term) => haystack.includes(term));
};

const allItems = (): ApplicationItem[] =>
    fixtureState.get().applications.map((row) => row.item);

export const emptyApplicationsPage = (): Paginated<ApplicationItem> => ({
    data: [],
    meta: { nextCursor: null, total: 0 },
});

export const emptyApplicationCounts = (): ApplicationCounts => ({
    all: 0,
    in_progress: 0,
    sent: 0,
    attention: 0,
});

export function listApplications(
    filters: ApplicationFilters,
    cursor: string | null = null,
): Paginated<ApplicationItem> {
    const statuses = STATUS_GROUPS[filters.status ?? 'all'];
    const language =
        filters.language !== undefined && filters.language !== 'all'
            ? filters.language
            : null;
    const q = filters.q?.trim() ?? '';

    const filtered = allItems()
        .filter((item) => statuses === null || statuses.includes(item.status))
        .filter((item) => language === null || item.language === language)
        .filter((item) => q === '' || matchesQuery(item, q))
        .sort(newestFirst);

    const parsed = cursor === null ? 0 : Number.parseInt(cursor, 10);
    const offset = Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    const end = offset + APPLICATIONS_PAGE_SIZE;

    return {
        data: filtered.slice(offset, end),
        meta: {
            nextCursor: end < filtered.length ? String(end) : null,
            total: filtered.length,
        },
    };
}

export function countApplications(): ApplicationCounts {
    const items = allItems();
    const count = (statuses: ApplicationStatus[]) =>
        items.filter((item) => statuses.includes(item.status)).length;

    return {
        all: items.length,
        in_progress: count(['queued', 'sending']),
        sent: count(['sent']),
        attention: count(['failed', 'ambiguous']),
    };
}
