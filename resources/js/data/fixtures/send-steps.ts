import type { SendStage, SubStep } from '@/types/contracts';

export const STEP_DELAY_MS = 1200;

// Every sub-step lasts STEP_DELAY_MS; the last one is followed by `sent`.
export const SEND_STEPS: { stage: SendStage; subStep: SubStep }[] = [
    { stage: 'validating_recipient', subStep: 'checking_company' },
    { stage: 'validating_recipient', subStep: 'confirming_recipient' },
    { stage: 'validating_recipient', subStep: 'checking_gmail' },
    { stage: 'adapting_template', subStep: 'filling_variables' },
    { stage: 'adapting_template', subStep: 'building_html' },
    { stage: 'attaching_cv', subStep: 'opening_cv' },
    { stage: 'attaching_cv', subStep: 'checking_pdf' },
    { stage: 'attaching_cv', subStep: 'attaching_file' },
    { stage: 'sending', subStep: 'connecting_gmail' },
    { stage: 'sending', subStep: 'delivering' },
];

export const SEND_DURATION_MS = SEND_STEPS.length * STEP_DELAY_MS;
