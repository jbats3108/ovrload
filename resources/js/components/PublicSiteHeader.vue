<script setup lang="ts">
import BrandName from '@/components/BrandName.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import { Link } from '@inertiajs/vue3';

type PublicPage = 'home' | 'login' | 'about' | 'beta-tester-faqs' | 'privacy';

const props = defineProps<{
    current?: PublicPage;
}>();

const links = [
    { name: 'login' as const, label: 'Log in', routeName: 'login' },
    { name: 'about' as const, label: 'About', routeName: 'about' },
    { name: 'beta-tester-faqs' as const, label: 'Beta testers', routeName: 'beta-tester-faqs' },
    { name: 'privacy' as const, label: 'Privacy', routeName: 'privacy' },
];

const visibleLinks = links.filter((link) => {
    if (link.name === props.current) {
        return false;
    }

    // Home already has a primary Log in CTA.
    if (props.current === 'home' && link.name === 'login') {
        return false;
    }

    return true;
});
</script>

<template>
    <header class="relative z-10 flex items-center justify-between gap-4 px-6 py-5 sm:px-10">
        <Link :href="route('home')" class="text-lg hover:opacity-90">
            <BrandName />
        </Link>

        <div class="flex items-center gap-4 sm:gap-5">
            <nav class="flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-sm text-muted-foreground" aria-label="Site">
                <Link
                    v-for="link in visibleLinks"
                    :key="link.name"
                    :href="route(link.routeName)"
                    class="underline-offset-4 hover:text-foreground hover:underline"
                >
                    {{ link.label }}
                </Link>
            </nav>
            <DarkModeToggle />
        </div>
    </header>
</template>
