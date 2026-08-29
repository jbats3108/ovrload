<script setup lang="ts">
import BrandName from '@/components/BrandName.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/admin/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Admin" />
        <AdminLayout>
            <HeadingSmall title="Overview" description="Manage the shared exercise catalog, training profiles, and user accounts." />
            <div class="grid gap-3 sm:grid-cols-3">
                <Link :href="route('admin.exercises')" class="rounded-xl border border-border bg-card p-4 transition hover:border-primary">
                    <p class="text-sm font-semibold">Exercises</p>
                    <p class="mt-1 text-xs text-muted-foreground">Create and remove shared catalog lifts</p>
                </Link>
                <Link :href="route('admin.exercise-profiles')" class="rounded-xl border border-border bg-card p-4 transition hover:border-primary">
                    <p class="text-sm font-semibold">Exercise profiles</p>
                    <p class="mt-1 text-xs text-muted-foreground">Create and publish shared <BrandName class="text-xs" /> training profiles</p>
                </Link>
                <Link :href="route('admin.muscle-groups')" class="rounded-xl border border-border bg-card p-4 transition hover:border-primary">
                    <p class="text-sm font-semibold">Muscle groups</p>
                    <p class="mt-1 text-xs text-muted-foreground">Manage group labels used by the catalog</p>
                </Link>
                <Link :href="route('admin.users')" class="rounded-xl border border-border bg-card p-4 transition hover:border-primary">
                    <p class="text-sm font-semibold">Users</p>
                    <p class="mt-1 text-xs text-muted-foreground">View registered accounts</p>
                </Link>
                <Link :href="route('admin.invites')" class="rounded-xl border border-border bg-card p-4 transition hover:border-primary">
                    <p class="text-sm font-semibold">Invites</p>
                    <p class="mt-1 text-xs text-muted-foreground">Create and send registration links</p>
                </Link>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
