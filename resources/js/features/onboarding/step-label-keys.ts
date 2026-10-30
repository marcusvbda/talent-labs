import type { AccountStatus } from '@/types/contracts';

/** Translation key per onboarding step; the email step reuses the existing `gmail` copy. */
export const STEP_LABEL_KEYS: Record<
    AccountStatus['onboarding']['steps'][number]['key'],
    string
> = {
    basics: 'onboarding.steps.basics',
    email: 'onboarding.steps.gmail',
    profile: 'onboarding.steps.profile',
    preferences: 'onboarding.steps.preferences',
};
