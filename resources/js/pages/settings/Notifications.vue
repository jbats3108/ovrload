<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    canUsePushNotifications,
    getCurrentPushSubscription,
    isStandalonePwa,
    subscribeToPush,
    unsubscribeFromPush,
} from '@/shared/lib/pushNotifications';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    vapid_public_key: string | null;
    has_subscription: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notifications settings',
        href: '/settings/notifications',
    },
];

const form = useForm({
    endpoint: '',
    public_key: '',
    auth_token: '',
    content_encoding: 'aes128gcm' as const,
});

const browserSupportsPush = ref(false);
const browserIsStandalone = ref(false);
const browserHasSubscription = ref(false);
const clientError = ref<string | null>(null);

const isIos = computed(() => {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
});

const enableLabel = computed(() => (form.processing ? 'Enabling…' : 'Enable rest notifications'));

onMounted(async () => {
    browserSupportsPush.value = canUsePushNotifications();
    browserIsStandalone.value = isStandalonePwa();

    if (!browserSupportsPush.value) {
        return;
    }

    try {
        browserHasSubscription.value = (await getCurrentPushSubscription()) !== null;
    } catch {
        browserHasSubscription.value = false;
    }
});

const enable = async (): Promise<void> => {
    if (form.processing || props.vapid_public_key === null) {
        return;
    }

    clientError.value = null;

    try {
        const payload = await subscribeToPush(props.vapid_public_key);

        form.transform(() => payload).post(route('notifications.subscription.store'), {
            preserveScroll: true,
            onSuccess: () => {
                browserHasSubscription.value = true;
            },
            onError: () => {
                clientError.value = 'The subscription could not be saved. Please try again.';
            },
        });
    } catch (error) {
        clientError.value = error instanceof Error ? error.message : 'Notifications could not be enabled.';
    }
};

const disable = async (): Promise<void> => {
    if (form.processing) {
        return;
    }

    clientError.value = null;

    try {
        const payload = await unsubscribeFromPush();
        browserHasSubscription.value = false;

        if (!payload) {
            return;
        }

        form.transform(() => ({ endpoint: payload.endpoint })).delete(route('notifications.subscription.destroy'), {
            preserveScroll: true,
            onError: () => {
                clientError.value = 'The browser subscription was removed, but the server could not be updated yet.';
            },
        });
    } catch (error) {
        clientError.value = error instanceof Error ? error.message : 'Notifications could not be disabled.';
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notifications settings" />

        <SettingsLayout section="Preferences">
            <div class="space-y-6">
                <HeadingSmall title="Rest notifications" description="Get one lock-screen alert when a rest period ends." />

                <p class="text-sm text-muted-foreground">
                    Notifications are sent by the app when you have network access. Your phone can be locked, but notification permissions, Focus
                    modes, and notification sounds remain controlled by your phone.
                </p>

                <p v-if="props.vapid_public_key === null" class="text-sm text-muted-foreground">
                    Lock-screen notifications are not configured on this environment yet.
                </p>

                <p v-else-if="!browserSupportsPush" class="text-sm text-muted-foreground">
                    This browser does not support push notifications. On iPhone, use the app from its Home Screen icon.
                </p>

                <p v-else-if="isIos && !browserIsStandalone" class="text-sm text-muted-foreground">
                    On iPhone, open this site in Safari, tap Share, choose <span class="font-medium text-foreground">Add to Home Screen</span>, then
                    open OVRLOAD from that new icon before enabling notifications.
                </p>

                <div v-else class="space-y-4">
                    <p v-if="browserHasSubscription" class="text-sm text-primary">Rest notifications are enabled on this device.</p>
                    <p v-else-if="props.has_subscription" class="text-sm text-muted-foreground">
                        Rest notifications are enabled on another device. Enable them here too if this is the phone you train with.
                    </p>

                    <div class="flex flex-wrap gap-3">
                        <Button v-if="!browserHasSubscription" type="button" :disabled="form.processing" @click="enable">
                            {{ enableLabel }}
                        </Button>
                        <Button v-else type="button" variant="secondary" :disabled="form.processing" @click="disable">
                            {{ form.processing ? 'Disabling…' : 'Disable on this device' }}
                        </Button>
                    </div>
                </div>

                <InputError :message="form.errors.endpoint || clientError || undefined" />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
