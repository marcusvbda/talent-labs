import { createInertiaApp, router } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { QueryClientProvider } from '@tanstack/react-query';
import { lazy, Suspense } from 'react';
import { Toaster } from '@/components/ui/toast';
import { queryClient } from '@/data/query-client';
import { I18nProvider } from '@/i18n/i18n-provider';
import type { SharedProps } from '@/types/shared';

configureEcho({
    broadcaster: 'reverb',
});

const ReactQueryDevtools = import.meta.env.DEV
    ? lazy(() =>
          import('@tanstack/react-query-devtools').then((module) => ({
              default: module.ReactQueryDevtools,
          })),
      )
    : null;

const DevToolbar = import.meta.env.DEV
    ? lazy(() => import('@/components/patterns/dev-toolbar'))
    : null;

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

        return (
            <QueryClientProvider client={queryClient}>
                <I18nProvider initialPage={page}>
                    {app}
                    <Toaster />
                    {DevToolbar && (
                        <Suspense fallback={null}>
                            <DevToolbar />
                        </Suspense>
                    )}
                </I18nProvider>
                {ReactQueryDevtools && (
                    <Suspense fallback={null}>
                        <ReactQueryDevtools initialIsOpen={false} />
                    </Suspense>
                )}
            </QueryClientProvider>
        );
    },
    progress: {
        color: 'var(--color-accent)',
    },
});
