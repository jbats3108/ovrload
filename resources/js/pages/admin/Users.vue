<script setup lang="ts">
import type { AdminUser } from '@/admin/types';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/admin/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    users: AdminUser[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Admin · Users" />
        <AdminLayout>
            <HeadingSmall title="Users" description="Registered accounts and their roles." />

            <ul class="divide-y divide-border rounded-xl border border-border">
                <li v-for="user in users" :key="user.id" class="px-4 py-3">
                    <p class="font-medium">{{ user.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                    <p class="mt-1 font-mono text-xs text-muted-foreground">
                        {{ user.roles.join(', ') || 'no role' }}
                        <span v-if="user.created_at"> · {{ user.created_at }}</span>
                    </p>
                </li>
            </ul>
        </AdminLayout>
    </AppLayout>
</template>
