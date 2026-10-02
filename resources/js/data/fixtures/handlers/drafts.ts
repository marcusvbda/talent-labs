import { ApiError } from '@/data/api';
import { toJobCard } from '@/data/fixtures/handlers/cards';
import { linksText } from '@/data/fixtures/handlers/profiles';
import { renderTemplate } from '@/data/fixtures/handlers/templates';
import { fixtureState } from '@/data/fixtures/state';
import type { ReviewDraft } from '@/types/contracts';

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
