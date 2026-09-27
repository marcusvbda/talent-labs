import type {
    ApplicationProfile,
    JobLanguage,
    TemplateVariable,
} from '../../../types/contracts';
import { daysAgo } from './time';

// Order of the variable chips in the template editor (spec B.2).
export const TEMPLATE_VARIABLES: TemplateVariable[] = [
    'company',
    'job_title',
    'job_location',
    'job_url',
    'client_name',
    'cover_letter',
];

// EN is copied verbatim from the PHP constants DEFAULT_SUBJECT / DEFAULT_BODY
// in ApplicationTemplateRenderer (including the stray ")" after the job_url
// token, per owner decision D3). PT is a faithful translation of the
// same two strings. None of them contains the cover_letter token: the client
// inserts it through the variable chips.
export const defaultTemplates: Record<
    JobLanguage,
    { subject: string; body: string }
> = {
    en: {
        subject: 'Application — {{ job_title }}',
        body: "Hello {{ company }} team,\n\nI'd like to apply for the '{{ job_title }}' position {{ job_url }}). My CV is attached.\n\nThank you for your time.\n\nBest regards,\n{{ client_name }}",
    },
    pt: {
        subject: 'Candidatura — {{ job_title }}',
        body: "Olá, equipe da {{ company }},\n\nGostaria de me candidatar à vaga de '{{ job_title }}' {{ job_url }}). Meu currículo está anexado.\n\nObrigado pelo seu tempo.\n\nAtenciosamente,\n{{ client_name }}",
    },
};

const CV = {
    fileName: 'ana-silva-cv.pdf',
    sizeBytes: 284_612,
    uploadedAt: daysAgo(12),
};

// Only an en profile exists. There is deliberately no pt profile, so PT jobs
// are locked by language.
export const PROFILES: ApplicationProfile[] = [
    {
        language: 'en',
        active: true,
        cv: { ...CV },
        emailSubject: defaultTemplates.en.subject,
        emailBody: defaultTemplates.en.body,
        coverLetter:
            'I am a backend-leaning full-stack engineer with six years of experience building reliable web products, and I enjoy working close to both the data and the interface.',
        complete: true,
        missing: [],
    },
];
