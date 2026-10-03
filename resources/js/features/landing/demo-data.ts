import type { JobLanguage, SendStage, SubStep } from '@/types/contracts';

/** Fictional data for the public demo. No real companies, people or jobs. */
export type DemoJob = {
    id: string;
    title: string;
    company: string;
    language: JobLanguage;
};

export type DemoActivity = {
    id: string;
    job: DemoJob;
    /** Epoch ms on the demo clock. */
    at: number;
};

export const DEMO_CANDIDATE = 'Ana Silva';

export const DEMO_DAILY_LIMIT = 50;
export const DEMO_START_SENT = 18;

/** Starting point of the demo clock (epoch ms); the loop advances it virtually. */
export const DEMO_EPOCH = Date.UTC(2026, 0, 12, 9, 12, 0);

export const DEMO_JOBS: DemoJob[] = [
    {
        id: 'job-1',
        title: 'Senior Backend Engineer',
        company: 'Northwind Systems',
        language: 'en',
    },
    {
        id: 'job-2',
        title: 'Engenheira de Dados',
        company: 'Lumen Data',
        language: 'pt',
    },
    {
        id: 'job-3',
        title: 'Platform Engineer',
        company: 'Via Cloud',
        language: 'en',
    },
    {
        id: 'job-4',
        title: 'Desenvolvedor Full Stack',
        company: 'Brightpath Labs',
        language: 'pt',
    },
    {
        id: 'job-5',
        title: 'Frontend Engineer',
        company: 'Harbor Analytics',
        language: 'en',
    },
    {
        id: 'job-6',
        title: 'Engenheiro de Software',
        company: 'Cedro Digital',
        language: 'pt',
    },
];

/** Sent per day for the six days before today, oldest first. */
export const DEMO_WEEK_BARS = [34, 41, 28, 46, 39, 44];
export const DEMO_WEEK_LABELS = ['6', '7', '8', '9', '10', '11'];

/** Stage and sub-step pairs shown per step; only public-safe sub-steps appear. */
export const DEMO_STAGES: SendStage[] = [
    'validating_recipient',
    'adapting_template',
    'attaching_cv',
    'sending',
    'sent',
];

export const DEMO_STEPS: { stageIndex: number; subStep: SubStep | null }[] = [
    { stageIndex: 0, subStep: 'confirming_recipient' },
    { stageIndex: 1, subStep: 'filling_variables' },
    { stageIndex: 2, subStep: 'opening_cv' },
    { stageIndex: 2, subStep: 'attaching_file' },
    { stageIndex: 3, subStep: 'connecting_gmail' },
    { stageIndex: 3, subStep: 'delivering' },
    { stageIndex: 4, subStep: null },
];

export const STEP_MS = 1300;
export const TICK_MS = 1000;
export const COUNTDOWN_MS = 6000;
/** Virtual gap between two queued sends, used for the queue ETAs. */
export const QUEUE_GAP_MS = 90_000;

export const DEMO_INITIAL_ACTIVITY: DemoActivity[] = [
    { id: 'seed-1', job: DEMO_JOBS[5], at: DEMO_EPOCH - 90_000 },
    { id: 'seed-2', job: DEMO_JOBS[4], at: DEMO_EPOCH - 200_000 },
    { id: 'seed-3', job: DEMO_JOBS[3], at: DEMO_EPOCH - 330_000 },
];

/** Stack tags for the static preferences panel, keyed by `DEMO_JOBS` id. */
export const DEMO_MATCH_STACK: Record<string, string[]> = {
    'job-1': ['laravel', 'php'],
    'job-2': ['python', 'aws'],
    'job-3': ['node', 'sql'],
};

/** Subset of `DEMO_MATCH_STACK` shown highlighted, as for real Stack preferences. */
export const DEMO_STACK_MATCHES: Record<string, string[]> = {
    'job-1': ['laravel', 'php'],
    'job-2': ['python'],
    'job-3': ['node'],
};

/** Token marking where the job link goes in a sample email. */
export const JOB_LINK_TOKEN = ':job_link';

/** Sample emails, fixed per sample language and independent of the UI language. */
export const SAMPLE_EMAILS: Record<
    JobLanguage,
    { subject: string; lines: string[] }
> = {
    en: {
        subject: 'Application: Backend Engineer',
        lines: [
            'Hello Northwind team,',
            `I'm writing to apply for the Backend Engineer position ${JOB_LINK_TOKEN}.`,
            "I'm a backend-leaning engineer with six years of experience building reliable web products. My CV is attached.",
            'Best regards,',
            'Luke Skywalker',
        ],
    },
    pt: {
        subject: 'Candidatura: Engenheiro Backend',
        lines: [
            'Olá, equipe Northwind,',
            `Escrevo para me candidatar à vaga de Engenheiro Backend ${JOB_LINK_TOKEN}.`,
            'Sou engenheira com foco em backend e seis anos de experiência construindo produtos web confiáveis. Meu currículo está em anexo.',
            'Atenciosamente,',
            'Luke Skywalker',
        ],
    },
};

export const SAMPLE_CV_FILE = 'luke-skywalker-cv.pdf';
