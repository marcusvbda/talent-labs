import { ApiError } from '@/data/api';
import { defaultTemplates, TEMPLATE_VARIABLES } from '@/data/fixtures/catalog';
import { renderTemplate } from '@/data/fixtures/handlers/templates';
import { fixtureState } from '@/data/fixtures/state';
import type {
    ApplicationProfile,
    JobLanguage,
    ProfileLink,
    ProfilesData,
    TemplatePreview,
} from '@/types/contracts';

const LANGUAGES: JobLanguage[] = ['en', 'pt'];
const MAX_SUBJECT = 200;
const MAX_BODY = 5000;
const MAX_COVER_LETTER = 6000;

const MAX_CV_BYTES = 5 * 1024 * 1024;

export type SaveProfileInput = {
    language: JobLanguage;
    subject: string;
    body: string;
    coverLetter: string;
    active: boolean;
    links?: ProfileLink[];
};

const sanitizeLinks = (links: ProfileLink[]): ProfileLink[] =>
    links.filter((link) => link.label.trim() !== '' || link.url.trim() !== '');

// Mirrors ApplicationTemplateRenderer::linksText: one "label : url" line per
// complete link, in entry order; incomplete rows are skipped.
export const linksText = (links: ProfileLink[]): string =>
    links
        .map((link) => ({ label: link.label.trim(), url: link.url.trim() }))
        .filter((link) => link.label !== '' && link.url !== '')
        .map((link) => `${link.label} : ${link.url}`)
        .join('\n');

const find = (language: JobLanguage): ApplicationProfile | undefined =>
    fixtureState.get().profiles.find((row) => row.language === language);

const mustFind = (language: JobLanguage): ApplicationProfile => {
    const profile = find(language);

    if (!profile) {
        throw new ApiError(404, 'Profile not found.');
    }

    return profile;
};

const replace = (next: ApplicationProfile): ApplicationProfile => {
    fixtureState.set((state) => ({
        ...state,
        profiles: state.profiles.map((row) =>
            row.language === next.language ? next : row,
        ),
    }));

    return next;
};

// Recomputes `missing` and `complete` from the profile's own fields.
export function recomputeProfile(
    profile: ApplicationProfile,
): ApplicationProfile {
    const missing: ApplicationProfile['missing'] = [];

    if (profile.cv === null) {
        missing.push('cv');
    }

    if (profile.emailSubject.trim() === '') {
        missing.push('subject');
    }

    if (profile.emailBody.trim() === '') {
        missing.push('body');
    }

    return { ...profile, missing, complete: missing.length === 0 };
}

export function profilesData(): ProfilesData {
    const { profiles, jobs } = fixtureState.get();
    const unlockCounts: Record<JobLanguage, number> = { en: 0, pt: 0 };

    jobs.forEach((job) => {
        unlockCounts[job.language] += 1;
    });

    return {
        profiles: profiles.map((row) => ({ ...row })),
        variables: [...TEMPLATE_VARIABLES],
        unlockCounts,
    };
}

export const emptyProfilesData = (): ProfilesData => ({
    ...profilesData(),
    profiles: [],
});

export function createProfile(language: JobLanguage): ApplicationProfile {
    if (!LANGUAGES.includes(language)) {
        throw new ApiError(422, 'Invalid profile', {
            language: ['Choose a supported language.'],
        });
    }

    if (find(language)) {
        throw new ApiError(409, 'This language already has a profile.');
    }

    const profile = recomputeProfile({
        language,
        active: true,
        cv: null,
        emailSubject: defaultTemplates[language].subject,
        emailBody: defaultTemplates[language].body,
        coverLetter: '',
        links: [],
        complete: false,
        missing: ['cv'],
    });

    fixtureState.set((state) => ({
        ...state,
        profiles: [...state.profiles, profile],
    }));

    return { ...profile };
}

const unknownVariable = (text: string): string | null => {
    for (const match of text.matchAll(/\{\{\s*([^{}]*?)\s*\}\}/g)) {
        const name = match[1];

        if (!(TEMPLATE_VARIABLES as string[]).includes(name)) {
            return name;
        }
    }

    return null;
};

const validateTemplateField = (
    text: string,
    max: number,
    label: string,
    checkVariables: boolean,
): string | null => {
    if (text.length > max) {
        return `The ${label} must not be longer than ${max} characters.`;
    }

    const unknown = checkVariables ? unknownVariable(text) : null;

    return unknown === null ? null : `Unknown variable {{ ${unknown} }}.`;
};

export function saveProfile(input: SaveProfileInput): ApplicationProfile {
    const profile = mustFind(input.language);
    const errors: Record<string, string[]> = {};
    const fields: [string, string, number, string, boolean][] = [
        ['subject', input.subject, MAX_SUBJECT, 'subject', true],
        ['body', input.body, MAX_BODY, 'body', true],
        [
            'coverLetter',
            input.coverLetter,
            MAX_COVER_LETTER,
            'cover letter',
            false,
        ],
    ];

    for (const [field, text, max, label, checkVariables] of fields) {
        const message = validateTemplateField(text, max, label, checkVariables);

        if (message !== null) {
            errors[field] = [message];
        }
    }

    if (
        errors.coverLetter === undefined &&
        /\{\{\s*cover_letter\s*\}\}/.test(input.coverLetter)
    ) {
        errors.coverLetter = [
            'The cover letter cannot contain {{ cover_letter }}.',
        ];
    }

    if (Object.keys(errors).length > 0) {
        throw new ApiError(422, 'Invalid profile', errors);
    }

    return replace(
        recomputeProfile({
            ...profile,
            emailSubject: input.subject,
            emailBody: input.body,
            coverLetter: input.coverLetter,
            active: input.active,
            links:
                input.links === undefined
                    ? profile.links
                    : sanitizeLinks(input.links),
        }),
    );
}

export function deleteProfile(language: JobLanguage): Record<string, never> {
    mustFind(language);

    fixtureState.set((state) => ({
        ...state,
        profiles: state.profiles.filter((row) => row.language !== language),
    }));

    return {};
}

export function setProfileCv(
    language: JobLanguage,
    cv: ApplicationProfile['cv'],
): ApplicationProfile {
    return replace(recomputeProfile({ ...mustFind(language), cv }));
}

export function previewTemplate(input: {
    language: JobLanguage;
    subject: string;
    body: string;
    coverLetter: string;
    links?: ProfileLink[];
}): TemplatePreview {
    const { jobs } = fixtureState.get();
    const sample =
        jobs
            .filter((job) => job.language === input.language)
            .sort((a, b) => a.id - b.id)[0] ??
        [...jobs].sort((a, b) => a.id - b.id)[0];
    // `job_url` is left out on purpose: the client never sees a job URL.
    const vars = {
        company: sample.company.name,
        job_title: sample.title,
        job_location: sample.location ?? 'Remote',
        client_name: 'Ana Silva',
        cover_letter: input.coverLetter,
        links: linksText(input.links ?? []),
    };

    return {
        subject: renderTemplate(input.subject, vars),
        body: renderTemplate(input.body, vars),
        sampleJob: { company: sample.company.name, title: sample.title },
    };
}

export const validateCv = (file: File) => {
    const isPdf =
        file.type === 'application/pdf' ||
        file.name.toLowerCase().endsWith('.pdf');

    if (!isPdf) {
        throw new ApiError(422, 'Invalid CV', {
            cv: ['The CV must be a PDF file.'],
        });
    }

    if (file.size > MAX_CV_BYTES) {
        throw new ApiError(422, 'Invalid CV', {
            cv: ['The CV must not be larger than 5 MB.'],
        });
    }
};
