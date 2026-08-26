<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    routines: { slug: string; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'History', href: '/history' },
    { title: 'Add historical', href: '/history/create' },
];
</script>

<template>
    <Head title="Add historical workout" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4 text-foreground">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Add historical workout</h1>
                <p class="mt-1 text-sm text-muted-foreground">Pick a routine to log against.</p>
            </div>

            <ul v-if="routines.length" class="divide-y divide-border rounded-xl border border-border">
                <li v-for="routine in routines" :key="routine.slug">
                    <Link :href="route('history.create', routine.slug)" class="block px-4 py-3 font-medium transition-colors hover:bg-card">
                        {{ routine.name }}
                    </Link>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">No routines yet. Create one from Dashboard first.</p>
        </div>
    </AppLayout>
</template>
