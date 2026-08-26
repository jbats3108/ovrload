<script setup lang="ts">
import { useZiggyRoute } from '@/shared/composables/useZiggyRoute';
import { isAccountActive, isPathActive, isPreferencesActive, mobileNavItems } from '@/shared/lib/appNav';
import { Link, usePage } from '@inertiajs/vue3';
import { History, LayoutGrid, Shield, SlidersHorizontal, UserRound } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const page = usePage();
const route = useZiggyRoute();
const path = computed(() => page.url.split('?')[0]);
const isAdmin = computed(() => Boolean(page.props.auth.user?.is_admin));

const tabIcons: Record<string, Component> = {
    '/dashboard': LayoutGrid,
    '/history': History,
    '/settings/training': SlidersHorizontal,
    '/settings/profile': UserRound,
    '/admin': Shield,
};

type Tab = { href: string; label: string; icon: Component; active: boolean };

const isTabActive = (match: string): boolean => {
    if (match === '/settings/training') {
        return isPreferencesActive(path.value);
    }

    if (match === '/settings/profile') {
        return isAccountActive(path.value);
    }

    return isPathActive(path.value, match);
};

const tabs = computed((): Tab[] =>
    mobileNavItems(route, { isAdmin: isAdmin.value }).map((link) => ({
        href: link.href,
        label: link.label,
        icon: tabIcons[link.match],
        active: isTabActive(link.match),
    })),
);
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card pb-[env(safe-area-inset-bottom,0px)] md:hidden" aria-label="Primary">
        <ul class="grid h-14" :class="tabs.length === 5 ? 'grid-cols-5' : 'grid-cols-4'">
            <li v-for="tab in tabs" :key="tab.label">
                <Link
                    :href="tab.href"
                    class="flex h-full flex-col items-center justify-center gap-0.5 text-[10px] font-medium"
                    :class="tab.active ? 'text-primary' : 'text-muted-foreground'"
                    :aria-current="tab.active ? 'page' : undefined"
                    prefetch
                >
                    <component :is="tab.icon" class="size-5" aria-hidden="true" />
                    <span class="whitespace-nowrap">{{ tab.label }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
