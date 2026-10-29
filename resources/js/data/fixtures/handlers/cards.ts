import { fixtureState } from '@/data/fixtures/state';
import type {
    JobCard,
    JobDetail,
    JobMatch,
    MatchCriterion,
    Preferences,
} from '@/types/contracts';

const WEIGHTS = { stack: 50, seniority: 25, location: 15, title: 10 };

// Approximates the backend match scorer for fixture mode.
const fixtureMatch = (job: JobDetail, prefs: Preferences): JobMatch | null => {
    const criteria: MatchCriterion[] = [];
    let credit = 0;
    let weight = 0;
    const lower = (v: string) => v.trim().toLowerCase();

    if (prefs.stack.length > 0) {
        const jobStack = new Set(job.stack.map(lower));
        const matched = prefs.stack.filter((s) => jobStack.has(lower(s)));
        const missing = prefs.stack.filter((s) => !jobStack.has(lower(s)));
        const result =
            matched.length === 0
                ? 'missed'
                : missing.length === 0
                  ? 'met'
                  : 'partial';
        weight += WEIGHTS.stack;
        credit += (WEIGHTS.stack * matched.length) / prefs.stack.length;
        criteria.push({ key: 'stack', result, matched, missing });
    }

    if (prefs.seniorities.length > 0) {
        weight += WEIGHTS.seniority;
        if (prefs.seniorities.includes(job.seniority)) {
            credit += WEIGHTS.seniority;
            criteria.push({ key: 'seniority', result: 'met' });
        } else if (job.seniority === 'unknown') {
            credit += WEIGHTS.seniority / 2;
            criteria.push({ key: 'seniority', result: 'not_stated' });
        } else {
            criteria.push({ key: 'seniority', result: 'missed' });
        }
    }

    const mode = prefs.remoteMode;
    if (
        mode === 'remote_only' ||
        (mode !== 'any' && prefs.locations.length > 0)
    ) {
        weight += WEIGHTS.location;
        const places = [job.location, ...job.locations]
            .filter((v): v is string => !!v)
            .map(lower);
        const inLocation = prefs.locations.some((l) =>
            places.some((p) => p.includes(lower(l))),
        );
        if (job.isRemote === true && mode !== 'locations_only') {
            credit += WEIGHTS.location;
            criteria.push({ key: 'location', result: 'remote' });
        } else if (inLocation && mode !== 'remote_only') {
            credit += WEIGHTS.location;
            criteria.push({ key: 'location', result: 'location' });
        } else {
            criteria.push({ key: 'location', result: 'missed' });
        }
    }

    if (prefs.titles.length > 0) {
        weight += WEIGHTS.title;
        const title = lower(job.title);
        const met = prefs.titles.some((t) => title.includes(lower(t)));
        if (met) {
            credit += WEIGHTS.title;
        }
        criteria.push({ key: 'title', result: met ? 'met' : 'missed' });
    }

    if (weight === 0) {
        return null;
    }

    return { score: Math.round((credit * 100) / weight), criteria };
};

// Drops the detail-only fields of a stored job; matching stack tags come first.
export const toJobCard = (job: JobDetail): JobCard => {
    const wanted = new Set(
        fixtureState.get().preferences.stack.map((s) => s.trim().toLowerCase()),
    );
    const isMatch = (s: string) => wanted.has(s.trim().toLowerCase());
    const matches = job.stack.filter(isMatch);
    const rest = job.stack.filter((s) => !isMatch(s));

    return {
        id: job.id,
        company: job.company,
        title: job.title,
        location: job.location,
        isRemote: job.isRemote,
        language: job.language,
        seniority: job.seniority,
        stack: [...matches, ...rest].slice(0, 6),
        stackMatches: matches.slice(0, 6),
        summary: job.summary,
        firstSeenAt: job.firstSeenAt,
        collectedToday: job.collectedToday,
        jobUrl: job.jobUrl,
        match: fixtureMatch(job, fixtureState.get().preferences),
    };
};
