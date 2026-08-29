<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const sidebarNavItems: NavItem[] = [
    { title: 'Overview', href: route('admin.index') },
    { title: 'Exercises', href: route('admin.exercises') },
    { title: 'Exercise profiles', href: route('admin.exercise-profiles') },
    { title: 'Muscle groups', href: route('admin.muscle-groups') },
    { title: 'Users', href: route('admin.users') },
    { title: 'Invites', href: route('admin.invites') },
];

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Admin" description="Shared catalog and users" />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col gap-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">{{ item.title }}</Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-3xl">
                <section class="space-y-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
