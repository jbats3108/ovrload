export type PushContentEncoding = 'aes128gcm' | 'aesgcm';

export type PushSubscriptionPayload = {
    endpoint: string;
    public_key: string;
    auth_token: string;
    content_encoding: PushContentEncoding;
};

type IosNavigator = Navigator & {
    standalone?: boolean;
};

export function canUsePushNotifications(): boolean {
    return (
        typeof window !== 'undefined' && window.isSecureContext && 'Notification' in window && 'PushManager' in window && 'serviceWorker' in navigator
    );
}

export function isStandalonePwa(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(display-mode: standalone)').matches || (navigator as IosNavigator).standalone === true;
}

export async function getCurrentPushSubscription(): Promise<PushSubscription | null> {
    if (!canUsePushNotifications()) {
        return null;
    }

    const registration = await navigator.serviceWorker.ready;

    return registration.pushManager.getSubscription();
}

export async function subscribeToPush(vapidPublicKey: string): Promise<PushSubscriptionPayload> {
    if (!canUsePushNotifications()) {
        throw new Error('Push notifications are not available in this browser.');
    }

    const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('Notification permission was not granted.');
    }

    const registration = await navigator.serviceWorker.ready;
    const existing = await registration.pushManager.getSubscription();
    const subscription =
        existing ??
        (await registration.pushManager.subscribe({
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            userVisibleOnly: true,
        }));

    return serializePushSubscription(subscription);
}

export async function unsubscribeFromPush(): Promise<PushSubscriptionPayload | null> {
    const subscription = await getCurrentPushSubscription();
    if (!subscription) {
        return null;
    }

    const payload = serializePushSubscription(subscription);
    await subscription.unsubscribe();

    return payload;
}

export function serializePushSubscription(subscription: PushSubscription): PushSubscriptionPayload {
    const json = subscription.toJSON();
    const p256dh = json.keys?.p256dh;
    const auth = json.keys?.auth;

    if (!json.endpoint || !p256dh || !auth) {
        throw new Error('The browser returned an incomplete push subscription.');
    }

    return {
        endpoint: json.endpoint,
        public_key: p256dh,
        auth_token: auth,
        content_encoding: 'aes128gcm',
    };
}

export function urlBase64ToUint8Array(value: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = `${value}${padding}`.replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const bytes = new Uint8Array(new ArrayBuffer(rawData.length));

    for (let index = 0; index < rawData.length; index += 1) {
        bytes[index] = rawData.charCodeAt(index);
    }

    return bytes;
}
