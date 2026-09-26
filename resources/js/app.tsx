import { createInertiaApp, router } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { I18nProvider } from '@/i18n/i18n-provider';
import type { SharedProps } from '@/types/shared';

configureEcho({
    broadcaster: 'reverb',
});

let brand = '';

router.on('navigate', (event) => {
    brand = (event.detail.page.props as unknown as SharedProps).app.brand.name;
});

void createInertiaApp({
    title: (title) => {
        return title ? `${title} - ${brand}` : brand;
    },
    withApp(app, { page }) {
        brand = page.props.app.brand.name;

        return <I18nProvider initialPage={page}>{app}</I18nProvider>;
    },
    progress: {
        color: 'var(--color-accent)',
    },
});
