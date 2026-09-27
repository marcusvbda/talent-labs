import { ApiError } from '@/data/api';
import { fixtureState } from '@/data/fixtures/state';
import type {
    Account,
    AccountStatus,
    Locale,
    NotificationItem,
    OnboardingBasics,
} from '@/types/contracts';

export type SaveAccountInput = {
    name: string;
    locale: Locale;
    timezone: string;
    country: string;
};

export type ChangePasswordInput = {
    currentPassword: string;
    password: string;
    passwordConfirmation: string;
};

const LOCALES: string[] = ['en', 'pt'];

const validTimezone = (timeZone: string): boolean => {
    try {
        new Intl.DateTimeFormat('en', { timeZone });

        return true;
    } catch {
        return false;
    }
};

const basicsErrors = (input: OnboardingBasics): Record<string, string[]> => {
    const errors: Record<string, string[]> = {};

    if (!LOCALES.includes(input.locale)) {
        errors.locale = ['Choose a supported language.'];
    }

    if (!/^[A-Z]{2}$/.test(input.country)) {
        errors.country = ['Choose a valid country.'];
    }

    if (input.timezone === '' || !validTimezone(input.timezone)) {
        errors.timezone = ['Choose a valid timezone.'];
    }

    return errors;
};

const fail = (message: string, errors: Record<string, string[]>): never => {
    throw new ApiError(422, message, errors);
};

export function saveAccount(input: SaveAccountInput): Account {
    const name = input.name.trim();
    const errors = basicsErrors(input);

    if (name.length < 1 || name.length > 120) {
        errors.name = ['Enter a name between 1 and 120 characters.'];
    }

    if (Object.keys(errors).length > 0) {
        fail('Invalid account', errors);
    }

    fixtureState.set((state) => ({
        ...state,
        account: {
            ...state.account,
            name,
            locale: input.locale,
            timezone: input.timezone,
            country: input.country,
        },
    }));

    return fixtureState.account();
}

export function changePassword(
    input: ChangePasswordInput,
): Record<string, never> {
    const errors: Record<string, string[]> = {};

    if (input.currentPassword !== 'password') {
        errors.current_password = ['The current password is incorrect.'];
    }

    if (input.password.length < 8) {
        errors.password = ['The password must be at least 8 characters.'];
    } else if (input.password !== input.passwordConfirmation) {
        errors.password = ['The password confirmation does not match.'];
    }

    if (Object.keys(errors).length > 0) {
        fail('Invalid password', errors);
    }

    return {};
}

export function saveOnboardingBasics(input: OnboardingBasics): AccountStatus {
    const errors = basicsErrors(input);

    if (Object.keys(errors).length > 0) {
        fail('Invalid basics', errors);
    }

    fixtureState.set((state) => ({
        ...state,
        account: {
            ...state.account,
            country: input.country,
            locale: input.locale,
            timezone: input.timezone,
        },
        onboarding: { ...state.onboarding, basicsDone: true },
    }));

    return fixtureState.accountStatus();
}

export const sortedNotifications = (): NotificationItem[] =>
    [...fixtureState.get().notifications]
        .sort(
            (a, b) =>
                new Date(b.createdAt).getTime() -
                new Date(a.createdAt).getTime(),
        )
        .map((row) => ({ ...row, data: { ...row.data } }));

export function markAllNotificationsRead(): Record<string, never> {
    const now = new Date().toISOString();

    fixtureState.set((state) => ({
        ...state,
        notifications: state.notifications.map((row) =>
            row.readAt === null ? { ...row, readAt: now } : row,
        ),
    }));

    return {};
}
