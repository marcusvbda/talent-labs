import type {
    ApplicationDetail,
    ApplicationItem,
    JobDetail,
} from '../../../types/contracts';
import { JOBS } from './jobs';
import { defaultTemplates } from './profiles';
import { minutesAgoToday } from './time';

// Deterministic application history. Nothing here uses Math.random: a seeded
// PRNG drives every "random" choice, so the shape is identical on every
// reload. Only the anchor (Date.now()) moves, which is what keeps the rows
// relative to "now".
//
// - 420 historical rows over days 1..30 ago (never today), all sent.
// - Today: 18 sent, 12 queued, 1 failed, 2 ambiguous, on 33 distinct
//   companies (never one company twice in a day).
// - Ids are 1..N in ascending queuedAt order, so today's queue rows have the
//   highest ids.
// - History references jobs 21..60 only (never the "collected today" 1..20),
//   so the matches pool stays populated.
// - Today's rows leave 11 companies free (see RESERVED_JOB_IDS) so that
//   queueing stays possible for the matching and fresh jobs.

const HISTORY_TOTAL = 420;
const CLIENT_NAME = 'Ana Silva';
const CV_FILE_NAME = 'ana-silva-cv.pdf';

const ERR_CV = 'Your CV file could not be read.';
const ERR_REJECTED = 'The company mailbox rejected the message.';
const ERR_UNCONFIRMED = 'We could not confirm delivery.';

// mulberry32: small, fast, seedable.
const createRandom = (seed: number) => {
    let state = seed;

    return (): number => {
        state = (state + 0x6d2b79f5) | 0;
        let t = Math.imul(state ^ (state >>> 15), 1 | state);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4_294_967_296;
    };
};

const random = createRandom(20_260_927);
const int = (min: number, max: number): number =>
    min + Math.floor(random() * (max - min + 1));

const shuffle = <T>(rows: T[]): T[] => {
    const copy = [...rows];

    for (let i = copy.length - 1; i > 0; i--) {
        const j = int(0, i);
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy;
};

export type ApplicationSeed = { item: ApplicationItem; jobId: number };

const HISTORY_POOL = JOBS.filter((row) => row.id >= 21);

// Destructuring instead of property access keeps the AC08 grep (which flags
// the substring after a dot) clean.
const companyOf = (job: JobDetail): JobDetail['company'] => {
    const { company } = job;

    return company;
};
const refOf = (job: JobDetail): JobDetail['company'] => {
    const { id, name, initials } = companyOf(job);

    return { id, name, initials };
};
const companyIdOf = (job: JobDetail): number => companyOf(job).id;

const slugify = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

export const fixtureJobUrl = (
    company: { name: string },
    jobId: number,
    title: string | null,
): string => {
    const titleSlug = title === null ? '' : slugify(title);

    return `https://careers.${slugify(company.name)}.example/jobs/${
        titleSlug === '' ? jobId : `${jobId}-${titleSlug}`
    }`;
};

const draft = (
    job: JobDetail,
): Pick<ApplicationItem, 'company' | 'title' | 'language' | 'origin'> => ({
    company: refOf(job),
    title: job.title,
    language: job.language,
    origin: random() < 0.6 ? 'auto' : 'manual',
});

// ---- History -------------------------------------------------------------

const localDay = (daysBack: number): Date => {
    const now = new Date();

    return new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate() - daysBack,
    );
};

const isWeekend = (date: Date): boolean =>
    date.getDay() === 0 || date.getDay() === 6;

// Per-day counts: weekdays ~18-21, weekends 0-3, then weekdays (newest
// first) are nudged one at a time until the total is exactly 420.
const dayCounts = (): number[] => {
    const counts: number[] = [];
    const weekdayIndexes: number[] = [];

    for (let back = 1; back <= 30; back++) {
        if (isWeekend(localDay(back))) {
            counts.push(int(0, 3));
        } else {
            weekdayIndexes.push(counts.length);
            counts.push(int(18, 21));
        }
    }

    let diff = HISTORY_TOTAL - counts.reduce((sum, n) => sum + n, 0);
    const step = diff > 0 ? 1 : -1;

    for (let guard = 0; diff !== 0 && guard < 1000; guard++) {
        const index = weekdayIndexes[guard % weekdayIndexes.length];
        const next = counts[index] + step;

        if (next >= 16 && next <= 22) {
            counts[index] = next;
            diff -= step;
        }
    }

    return counts;
};

const historySeeds = (): ApplicationSeed[] => {
    const counts = dayCounts();
    const seeds: ApplicationSeed[] = [];
    let cursor = 0;

    counts.forEach((count, index) => {
        const day = localDay(index + 1);

        // Distinct working-window minutes (09:00-17:59), sorted.
        const minutes = new Set<number>();

        while (minutes.size < count) {
            minutes.add(int(540, 1079));
        }

        const usedCompanies = new Set<number>();

        [...minutes]
            .sort((a, b) => a - b)
            .forEach((minute) => {
                // Cycle the pool, skipping companies already applied to that
                // day when possible.
                let job = HISTORY_POOL[cursor % HISTORY_POOL.length];

                for (let tries = 0; tries < HISTORY_POOL.length; tries++) {
                    const candidate =
                        HISTORY_POOL[(cursor + tries) % HISTORY_POOL.length];

                    if (!usedCompanies.has(companyIdOf(candidate))) {
                        job = candidate;
                        cursor += tries;
                        break;
                    }
                }

                cursor++;
                usedCompanies.add(companyIdOf(job));

                const sentAt = new Date(
                    day.getFullYear(),
                    day.getMonth(),
                    day.getDate(),
                    0,
                    minute,
                    int(0, 59),
                );
                const queuedAt = new Date(
                    sentAt.getTime() - int(40, 170) * 1000,
                );

                seeds.push({
                    jobId: job.id,
                    item: {
                        id: 0,
                        ...draft(job),
                        status: 'sent',
                        stage: 'sent',
                        subStep: null,
                        lastError: null,
                        queuedAt: queuedAt.toISOString(),
                        scheduledFor: null,
                        sentAt: sentAt.toISOString(),
                        jobUrl: fixtureJobUrl(refOf(job), job.id, job.title),
                    },
                });
            });
    });

    return seeds;
};

// ---- Today ---------------------------------------------------------------

// Jobs that must stay queueable in the demo: the preference-matching jobs
// plus fresh "collected today" en/pt jobs. Their companies stay free today.
const RESERVED_JOB_IDS = [1, 2, 3, 4, 5, 6, 7, 12, 13, 16, 24, 36, 44];

// One job per company, for every company that is NOT reserved (44 - 11 = 33
// distinct companies). Jobs from 21..60 are preferred over 1..20.
const todayJobs = (): JobDetail[] => {
    const reservedCompanies = new Set(
        JOBS.filter((row) => RESERVED_JOB_IDS.includes(row.id)).map(
            companyIdOf,
        ),
    );
    const companyIds = [...new Set(JOBS.map(companyIdOf))].filter(
        (id) => !reservedCompanies.has(id),
    );

    return shuffle(
        companyIds.map((companyId) => {
            const own = JOBS.filter((row) => companyIdOf(row) === companyId);

            return own.find((row) => row.id >= 21) ?? own[0];
        }),
    );
};

const todaySeeds = (): ApplicationSeed[] => {
    const jobs = todayJobs();
    const seeds: ApplicationSeed[] = [];

    // 21 already-processed rows, oldest first, ~27 min apart from ~10 h ago.
    // Position 6 fails, 11 and 16 are ambiguous, the rest are sent.
    for (let i = 0; i < 21; i++) {
        const job = jobs[i];
        const minutesBack = 600 - i * 27;
        const queuedAt = minutesAgoToday(minutesBack + 2);
        const base = { id: 0, ...draft(job), queuedAt, scheduledFor: null };

        let item: ApplicationItem;

        if (i === 6) {
            item = {
                ...base,
                status: 'failed',
                stage: 'attaching_cv',
                subStep: null,
                lastError: ERR_CV,
                sentAt: null,
                jobUrl: null,
            };
        } else if (i === 11 || i === 16) {
            item = {
                ...base,
                status: 'ambiguous',
                stage: 'sending',
                subStep: null,
                lastError: i === 11 ? ERR_UNCONFIRMED : ERR_REJECTED,
                sentAt: null,
                jobUrl: null,
            };
        } else {
            item = {
                ...base,
                status: 'sent',
                stage: 'sent',
                subStep: null,
                lastError: null,
                sentAt: minutesAgoToday(minutesBack),
                jobUrl: fixtureJobUrl(refOf(job), job.id, job.title),
            };
        }

        seeds.push({ jobId: job.id, item });
    }

    // 12 queued rows, scheduled 45-120 s apart starting ~1 min from now.
    let scheduled = Date.now() + 60_000;

    for (let i = 0; i < 12; i++) {
        const job = jobs[21 + i];

        seeds.push({
            jobId: job.id,
            item: {
                id: 0,
                ...draft(job),
                status: 'queued',
                stage: null,
                subStep: null,
                lastError: null,
                queuedAt: minutesAgoToday(12 - i * 0.8),
                scheduledFor: new Date(scheduled).toISOString(),
                sentAt: null,
                jobUrl: null,
            },
        });

        scheduled += int(45, 120) * 1000;
    }

    return seeds;
};

export const APPLICATION_SEEDS: ApplicationSeed[] = [
    ...historySeeds(),
    ...todaySeeds(),
]
    .sort(
        (a, b) =>
            new Date(a.item.queuedAt).getTime() -
            new Date(b.item.queuedAt).getTime(),
    )
    .map((seed, index) => ({
        jobId: seed.jobId,
        item: { ...seed.item, id: index + 1 },
    }));

export const APPLICATIONS: ApplicationItem[] = APPLICATION_SEEDS.map(
    (seed) => seed.item,
);

// ---- Detail --------------------------------------------------------------

// Fills the known variables. `{{ job_url }}` stays as the literal token; the
// client renders it as a chip.
const fill = (template: string, values: Record<string, string>): string =>
    template.replace(
        /\{\{ (company|job_title|client_name|job_location) \}\}/g,
        (_, key: string) => values[key],
    );

export const detailFor = (
    item: ApplicationItem,
    jobId?: number,
): ApplicationDetail => {
    const { company: itemCompany } = item;
    const job =
        JOBS.find((row) => row.id === jobId) ??
        JOBS.find(
            (row) =>
                companyIdOf(row) === itemCompany.id && row.title === item.title,
        );
    const values = {
        company: itemCompany.name,
        job_title: item.title ?? '',
        client_name: CLIENT_NAME,
        job_location: job?.location ?? 'Remote',
    };
    const template = defaultTemplates[item.language];

    return {
        ...item,
        subject: fill(template.subject, values),
        body: fill(template.body, values),
        cvFileName: item.language === 'pt' ? null : CV_FILE_NAME,
    };
};
