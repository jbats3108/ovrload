import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { registerSW } from 'virtual:pwa-register';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import type { AppPageProps } from './types';

function isGuestAuthPath(pathname: string): boolean {
    return (
        pathname === '/' ||
        pathname === '/login' ||
        pathname === '/register' ||
        pathname === '/forgot-password' ||
        pathname.startsWith('/reset-password/')
    );
}

if (typeof window !== 'undefined') {
    window.addEventListener('pageshow', (event: PageTransitionEvent) => {
        if (!event.persisted || !isGuestAuthPath(window.location.pathname)) {
            return;
        }

        router.reload();
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'OVRLOAD';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    pages: './pages',
    withApp(app, { ssr, page }) {
        if (ssr) {
            const ziggy = (page.props as AppPageProps).ziggy;

            app.use(ZiggyVue, {
                ...ziggy,
                location: new URL(ziggy.location),
            });

            return;
        }

        app.use(ZiggyVue);
    },
    progress: {
        color: '#c8ff00',
    },
});

initializeTheme();

if (import.meta.env.PROD) {
    registerSW({ immediate: true });
}
