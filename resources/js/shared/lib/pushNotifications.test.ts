import { serializePushSubscription, urlBase64ToUint8Array, type PushSubscriptionPayload } from '@/shared/lib/pushNotifications';
import { describe, expect, it } from 'vitest';

describe('pushNotifications', () => {
    it('decodes a URL-safe VAPID key', () => {
        expect(Array.from(urlBase64ToUint8Array('AQID-_w'))).toEqual([1, 2, 3, 251, 252]);
    });

    it('serializes a browser subscription for the server', () => {
        const subscription = {
            toJSON: () => ({
                endpoint: 'https://push.example.test/endpoint',
                keys: {
                    p256dh: 'public-key',
                    auth: 'auth-token',
                },
            }),
        } as unknown as PushSubscription;

        const payload: PushSubscriptionPayload = serializePushSubscription(subscription);

        expect(payload).toEqual({
            endpoint: 'https://push.example.test/endpoint',
            public_key: 'public-key',
            auth_token: 'auth-token',
            content_encoding: 'aes128gcm',
        });
    });

    it('rejects an incomplete browser subscription', () => {
        const subscription = {
            toJSON: () => ({
                endpoint: 'https://push.example.test/endpoint',
                keys: {},
            }),
        } as unknown as PushSubscription;

        expect(() => serializePushSubscription(subscription)).toThrow('incomplete push subscription');
    });
});
