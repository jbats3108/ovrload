import { clientsClaim } from 'workbox-core';
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

declare let self: ServiceWorkerGlobalScope & {
    __WB_MANIFEST: Array<string | { revision?: string | null; url: string }>;
};

self.skipWaiting();
clientsClaim();
cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST);

type RestNotificationPayload = {
    body?: string;
    tag?: string;
    title?: string;
    url?: string;
};

const defaultNotification: Required<Pick<RestNotificationPayload, 'body' | 'tag' | 'title' | 'url'>> = {
    body: 'Time for the next set.',
    tag: 'ovrload-rest-end',
    title: 'Rest over',
    url: '/dashboard',
};

function notificationPayload(event: PushEvent): RestNotificationPayload {
    if (!event.data) {
        return {};
    }

    try {
        const value: unknown = event.data.json();
        if (typeof value !== 'object' || value === null) {
            return {};
        }

        const payload = value as Record<string, unknown>;

        return {
            body: typeof payload.body === 'string' ? payload.body : undefined,
            tag: typeof payload.tag === 'string' ? payload.tag : undefined,
            title: typeof payload.title === 'string' ? payload.title : undefined,
            url: typeof payload.url === 'string' ? payload.url : undefined,
        };
    } catch {
        return {};
    }
}

function sameOriginUrl(path: string): string {
    try {
        const url = new URL(path, self.location.origin);
        return url.origin === self.location.origin ? url.href : new URL(defaultNotification.url, self.location.origin).href;
    } catch {
        return new URL(defaultNotification.url, self.location.origin).href;
    }
}

self.addEventListener('push', (event: PushEvent) => {
    const payload = notificationPayload(event);
    const options: NotificationOptions & { renotify: boolean } = {
        badge: '/pwa-192x192.png',
        body: payload.body ?? defaultNotification.body,
        data: {
            url: sameOriginUrl(payload.url ?? defaultNotification.url),
        },
        icon: '/pwa-192x192.png',
        renotify: true,
        silent: false,
        tag: payload.tag ?? defaultNotification.tag,
    };

    event.waitUntil(self.registration.showNotification(payload.title ?? defaultNotification.title, options));
});

self.addEventListener('notificationclick', (event: NotificationEvent) => {
    event.notification.close();

    const url = sameOriginUrl(
        event.notification.data && typeof event.notification.data.url === 'string' ? event.notification.data.url : defaultNotification.url,
    );

    event.waitUntil(
        self.clients.matchAll({ includeUncontrolled: true, type: 'window' }).then(async (windowClients) => {
            const client = windowClients[0];

            if (client) {
                await client.focus();
                await client.navigate(url);
                return;
            }

            await self.clients.openWindow(url);
        }),
    );
});
