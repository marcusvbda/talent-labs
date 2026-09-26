export type Locale = 'en' | 'pt' | 'es';

export type SharedUser = {
    id: number;
    name: string;
    email: string;
    initials: string;
    locale: Locale;
};

export type SharedProps = {
    app: {
        brand: { name: string; wordmark: [string, string] };
        env: 'local' | 'production' | string;
        useFixtures: boolean;
    };
    auth: { user: SharedUser | null };
    locale: Locale;
    locales: Locale[];
    translations: Record<string, string>;
    flash: { success: string | null; error: string | null };
};
