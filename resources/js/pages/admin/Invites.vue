<script setup lang="ts">
import type { InviteRow } from '@/admin/types';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/admin/Layout.vue';
import { confirmDialog } from '@/shared/lib/confirmDialog';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{
    invites: InviteRow[];
    master_enabled: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Invites', href: '/admin/invites' },
];

const page = usePage();
const flashUrl = computed(() => page.props.flash.invite_url ?? null);
const copiedId = ref<number | 'flash' | null>(null);
const mutating = ref(false);

const form = useForm({
    email: '',
    note: '',
    role: 'user',
    expires_in_days: 7 as number | null,
});

const submit = () => {
    form.post(route('admin.invites.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('email', 'note'),
    });
};

const copyUrl = async (url: string, id: number | 'flash') => {
    await navigator.clipboard.writeText(url);
    copiedId.value = id;
    window.setTimeout(() => {
        if (copiedId.value === id) copiedId.value = null;
    }, 2000);
};

const resend = (id: number) => {
    if (mutating.value) return;
    mutating.value = true;
    router.post(
        route('admin.invites.resend', id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                mutating.value = false;
            },
        },
    );
};

const revoke = async (id: number) => {
    if (mutating.value) return;
    const ok = await confirmDialog({
        title: 'Revoke this invite?',
        confirmLabel: 'Revoke',
        variant: 'destructive',
    });
    if (!ok) return;
    mutating.value = true;
    router.post(
        route('admin.invites.revoke', id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                mutating.value = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Admin · Invites" />
        <AdminLayout>
            <HeadingSmall title="Registration invites" description="Create a one-time registration link and email it directly." />

            <p v-if="master_enabled" class="rounded-xl border border-border bg-card px-4 py-3 text-sm text-muted-foreground">
                A shared registration code is also active. One-time invites below are still the best way to invite someone specific.
            </p>
            <p v-else class="text-sm text-muted-foreground">Registration is invite-only. Share the links you create below.</p>

            <div v-if="flashUrl" class="rounded-xl border border-primary/40 bg-primary/5 px-4 py-3">
                <p class="text-sm font-medium">Invite link (backup copy)</p>
                <p class="mt-1 font-mono text-xs break-all text-muted-foreground">{{ flashUrl }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Button type="button" size="sm" @click="copyUrl(flashUrl, 'flash')">
                        {{ copiedId === 'flash' ? 'Copied' : 'Copy' }}
                    </Button>
                </div>
            </div>

            <form class="space-y-3 rounded-xl border border-border p-4" @submit.prevent="submit">
                <p class="text-sm font-semibold">Create &amp; send invite</p>
                <label class="block text-xs text-muted-foreground">
                    Recipient email
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground"
                        placeholder="friend@example.com"
                    />
                </label>
                <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                <label class="block text-xs text-muted-foreground">
                    Note (optional, admin-only)
                    <input
                        v-model="form.note"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground"
                        placeholder="For Jamie’s gym buddy"
                    />
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-xs text-muted-foreground">
                        Role
                        <select
                            v-model="form.role"
                            class="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="user">user</option>
                            <option value="admin">admin</option>
                        </select>
                    </label>
                    <label class="block text-xs text-muted-foreground">
                        Expires (days)
                        <input
                            v-model.number="form.expires_in_days"
                            type="number"
                            min="1"
                            max="365"
                            class="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground"
                        />
                    </label>
                </div>
                <Button type="submit" :disabled="form.processing">Create &amp; send</Button>
            </form>

            <ul class="divide-y divide-border rounded-xl border border-border">
                <li v-for="invite in invites" :key="invite.id" class="space-y-2 px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-medium">
                                {{ invite.note || 'Invite' }}
                                <span class="font-mono text-xs text-muted-foreground">· {{ invite.role }}</span>
                            </p>
                            <p v-if="invite.email" class="text-xs text-muted-foreground">{{ invite.email }}</p>
                            <p class="text-xs text-muted-foreground">
                                by {{ invite.created_by ?? '?' }}
                                <span v-if="invite.created_at"> · {{ invite.created_at }}</span>
                                <span v-if="invite.expires_at"> · expires {{ invite.expires_at }}</span>
                            </p>
                            <p v-if="invite.used_at" class="text-xs text-muted-foreground">
                                used {{ invite.used_at }}
                                <span v-if="invite.used_by"> by {{ invite.used_by }}</span>
                            </p>
                            <p v-else-if="invite.revoked_at" class="text-xs text-destructive">revoked {{ invite.revoked_at }}</p>
                            <p v-else-if="invite.usable" class="text-xs text-primary">usable</p>
                            <p v-else class="text-xs text-muted-foreground">expired</p>
                        </div>
                        <div v-if="invite.usable" class="flex flex-wrap gap-2">
                            <Button type="button" size="sm" variant="outline" @click="copyUrl(invite.url, invite.id)">
                                {{ copiedId === invite.id ? 'Copied' : 'Copy' }}
                            </Button>
                            <Button v-if="invite.email" type="button" size="sm" variant="outline" :disabled="mutating" @click="resend(invite.id)">
                                Resend
                            </Button>
                            <Button type="button" size="sm" variant="ghost" :disabled="mutating" @click="revoke(invite.id)"> Revoke </Button>
                        </div>
                    </div>
                </li>
                <li v-if="!invites.length" class="px-4 py-8 text-center text-sm text-muted-foreground">No invites yet.</li>
            </ul>
        </AdminLayout>
    </AppLayout>
</template>
