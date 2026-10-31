import { ApiError } from '@/data/api';
import { toJobCard } from '@/data/fixtures/handlers/cards';
import { linksText } from '@/data/fixtures/handlers/profiles';
import { renderTemplate } from '@/data/fixtures/handlers/templates';
import { getDevState } from '@/data/fixtures/dev-state';
import { fixtureState } from '@/data/fixtures/state';
import { devEmitter } from '@/data/realtime/dev-emitter';
import type { CoverLetterResult, ReviewDraft } from '@/types/contracts';

const CLIENT_NAME = 'Ana Silva';

export const SUBJECT_MAX = 200;
export const BODY_MAX = 5000;

// Text the client approved per application id, shown by the detail view.
export const reviewedOverrides = new Map<
    number,
    { subject: string; body: string }
>();

export function buildDrafts(jobIds: number[]): ReviewDraft[] {
    const { jobs, profiles } = fixtureState.get();
    const drafts: ReviewDraft[] = [];

    for (const jobId of jobIds) {
        const job = jobs.find((row) => row.id === jobId);
        const profile = job
            ? profiles.find(
                  (row) =>
                      row.language === job.language &&
                      row.active &&
                      row.complete,
              )
            : undefined;

        if (!job || !profile) {
            continue;
        }

        const vars = {
            company: job.company.name,
            job_title: job.title,
            job_location: job.location ?? 'Remote',
            client_name: CLIENT_NAME,
            cover_letter: profile.coverLetter,
            links: linksText(profile.links),
        };

        drafts.push({
            job: toJobCard(job),
            language: job.language,
            subject: renderTemplate(profile.emailSubject, vars),
            body: renderTemplate(profile.emailBody, vars),
            cvFileName: profile.cv?.fileName ?? '',
            recipientLabel: `${job.company.name} careers team`,
            ai: getDevState().plan === 'max' ? { state: 'idle' } : null,
        });
    }

    if (drafts.length === 0) {
        throw new ApiError(422, 'No drafts available');
    }

    return drafts;
}

export function validateReviewed(subject: string, body: string): void {
    const errors: Record<string, string[]> = {};
    const s = subject.trim().length;
    const b = body.trim().length;

    if (s < 1) {
        errors.subject = ['The subject is required.'];
    } else if (s > SUBJECT_MAX) {
        errors.subject = [
            `The subject must be at most ${SUBJECT_MAX} characters.`,
        ];
    }

    if (b < 1) {
        errors.body = ['The message is required.'];
    } else if (b > BODY_MAX) {
        errors.body = [`The message must be at most ${BODY_MAX} characters.`];
    }

    if (Object.keys(errors).length > 0) {
        throw new ApiError(422, 'Invalid draft', errors);
    }
}

const AI_LETTERS = {
    en: [
        'I enjoy building reliable products with small, focused teams, and your open role matches the work I do best.',
        'Your team caught my attention because of the product you ship; I would bring steady delivery and clear communication.',
    ],
    pt: [
        'Gosto de construir produtos confiáveis com equipes pequenas e focadas, e a vaga se encaixa no que faço de melhor.',
        'O produto da sua equipe chamou minha atenção; eu levaria entregas constantes e comunicação clara.',
    ],
};

const AI_DELAY_MS = 1500;

// Returns "writing" at once; the letter lands later as `cover_letter.updated`.
export function generateCoverLetter(
    jobId: number,
    regenerate: boolean,
): CoverLetterResult {
    if (getDevState().plan !== 'max') {
        return { state: 'off', body: null };
    }

    const [draft] = buildDrafts([jobId]);
    const profile = fixtureState
        .get()
        .profiles.find((row) => row.language === draft.language && row.active);
    const sample = AI_LETTERS[draft.language][regenerate ? 1 : 0];
    const manual = profile?.coverLetter ?? '';
    const body =
        manual !== '' && draft.body.includes(manual)
            ? draft.body.replace(manual, sample)
            : `${draft.body}\n\n${sample}`;

    setTimeout(() => {
        // The test plan may have changed while the letter was being written.
        if (getDevState().plan !== 'max') {
            return;
        }

        fixtureState.bumpAiWritingUsed();

        const quota = fixtureState.accountStatus().aiWriting;

        if (quota === null) {
            return;
        }

        devEmitter.emit('cover_letter.updated', {
            jobId,
            state: 'ready',
            reason: null,
            body,
            aiQuota: quota,
        });
    }, AI_DELAY_MS);

    return { state: 'writing', body: null };
}
