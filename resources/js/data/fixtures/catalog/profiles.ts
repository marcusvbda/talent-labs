import type {
    ApplicationProfile,
    JobLanguage,
    TemplateVariable,
} from "../../../types/contracts";
import { daysAgo } from "./time";

// Order of the variable chips in the template editor (spec B.2).
export const TEMPLATE_VARIABLES: TemplateVariable[] = [
    "company",
    "job_title",
    "job_location",
    "job_url",
    "client_name",
    "cover_letter",
    "links",
];

// Copied verbatim from ApplicationTemplateRenderer::defaultsFor() in
// app/Outreach/Support/ApplicationTemplateRenderer.php.
export const defaultTemplates: Record<
    JobLanguage,
    { subject: string; body: string }
> = {
    en: {
        subject: "Application: {{ job_title }}",
        body: "Hello {{ company }} team,\n\nI'm writing to apply for the {{ job_title }} position ({{ job_url }}).\n\n{{ cover_letter }}\n\nMy CV is attached. Thank you for your time.\n\n\{{ links }}\n\nBest regards,\n{{ client_name }}",
    },
    pt: {
        subject: "Candidatura: {{ job_title }}",
        body: "Olá, equipe {{ company }},\n\nGostaria de me candidatar à vaga de {{ job_title }} ({{ job_url }}).\n\n{{ cover_letter }}\n\nMeu currículo está em anexo. Obrigado pelo seu tempo.\n\n{{ links }}\n\nAtenciosamente,\n{{ client_name }}",
    },
};

const CV = {
    fileName: "luke-skywalker-cv.pdf",
    sizeBytes: 284_612,
    uploadedAt: daysAgo(12),
};

// Only an en profile exists. There is deliberately no pt profile, so PT jobs
// are locked by language.
export const PROFILES: ApplicationProfile[] = [
    {
        language: "en",
        active: true,
        cv: { ...CV },
        emailSubject: defaultTemplates.en.subject,
        emailBody: defaultTemplates.en.body,
        coverLetter:
            "I am a backend-leaning full-stack engineer with six years of experience building reliable web products, and I enjoy working close to both the data and the interface.",
        links: [
            { label: "LinkedIn", url: "https://www.linkedin.com/in/example" },
        ],
        complete: true,
        missing: [],
    },
];
