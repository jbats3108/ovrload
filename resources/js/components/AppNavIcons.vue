<script setup lang="ts">
import { useZiggyRoute } from '@/shared/composables/useZiggyRoute';
import { accountNavItems, isPathActive, preferencesNavItems, primaryNavItems } from '@/shared/lib/appNav';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { History, LayoutGrid, LogOut, Palette, Shield, SlidersHorizontal, UserRound } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const props = withDefaults(
    defineProps<{
        /** `rail` = icon-only strip; `drawer` = labeled list (desktop sidebar + mobile sheet) */
        variant?: 'rail' | 'drawer';
    }>(),
    { variant: 'rail' },
);

const emit = defineEmits<{
    navigate: [];
}>();

const page = usePage();
const route = useZiggyRoute();
const path = computed(() => page.url.split('?')[0]);
const isAdmin = computed(() => Boolean(page.props.auth.user?.is_admin));
const labeled = computed(() => props.variant === 'drawer');
const logoutForm = useForm({});

const isActive = (match: string) => isPathActive(path.value, match);

const itemClass = (active: boolean) =>
    [
        labeled.value
            ? 'flex w-full items-center gap-3 rounded-md px-3 py-3 text-base font-medium transition-colors'
            : 'flex size-11 items-center justify-center rounded-md transition-colors',
        active ? 'bg-secondary text-primary' : 'text-muted-foreground hover:bg-secondary hover:text-foreground',
    ].join(' ');

const iconClass = computed(() => (labeled.value ? 'size-5 shrink-0' : 'size-6 shrink-0'));

const onNavigate = () => emit('navigate');

const logout = () => {
    if (logoutForm.processing) {
        return;
    }
    router.flushAll();
    logoutForm.post(route('logout'));
    onNavigate();
};

const primaryIcons: Record<string, Component> = {
    '/dashboard': LayoutGrid,
    '/history': History,
    '/admin': Shield,
};

const preferenceIcons: Record<string, Component> = {
    '/settings/training': SlidersHorizontal,
    '/settings/appearance': Palette,
};

const accountIcons: Record<string, Component> = {
    '/settings/profile': UserRound,
};

type NavLink = { href: string; label: string; icon: Component; match: string };

const primaryLinks = computed((): NavLink[] =>
    primaryNavItems(route, { isAdmin: isAdmin.value }).map((link) => ({
        ...link,
        icon: primaryIcons[link.match],
    })),
);

const preferenceLinks = computed((): NavLink[] =>
    preferencesNavItems(route).map((link) => ({
        ...link,
        icon: preferenceIcons[link.match],
    })),
);

const accountLinks = computed((): NavLink[] =>
    accountNavItems(route).map((link) => ({
        ...link,
        icon: accountIcons[link.match],
    })),
);
</script>

<template>
    <Link
        :href="route('dashboard')"
        class="mb-6 font-bold tracking-wide"
        :class="labeled ? 'self-start px-3 text-base' : 'text-sm'"
        aria-label="OVRLOAD home"
        prefetch
        @click="onNavigate"
    >
        <span class="text-primary">OVR</span>
        <span v-if="labeled" class="text-foreground">LOAD</span>
    </Link>

    <nav class="flex flex-1 flex-col gap-1.5" :class="labeled ? 'items-stretch' : 'items-center'">
        <Link
            v-for="link in primaryLinks"
            :key="link.match"
            :href="link.href"
            :class="itemClass(isActive(link.match))"
            :aria-label="link.label"
            :title="labeled ? undefined : link.label"
            prefetch
            @click="onNavigate"
        >
            <component :is="link.icon" :class="iconClass" />
            <span v-if="labeled">{{ link.label }}</span>
        </Link>
    </nav>

    <div class="flex flex-col gap-1.5" :class="labeled ? 'items-stretch' : 'items-center'">
        <template v-if="labeled">
            <p class="mb-1 px-3 text-xs tracking-[0.15em] text-muted-foreground uppercase">Preferences</p>
        </template>
        <Link
            v-for="link in preferenceLinks"
            :key="link.match"
            :href="link.href"
            :class="itemClass(isActive(link.match))"
            :aria-label="link.label"
            :title="labeled ? undefined : link.label"
            prefetch
            @click="onNavigate"
        >
            <component :is="link.icon" :class="iconClass" />
            <span v-if="labeled">{{ link.label }}</span>
        </Link>
        <template v-if="labeled">
            <p class="mt-3 mb-1 px-3 text-xs tracking-[0.15em] text-muted-foreground uppercase">Account</p>
        </template>
        <Link
            v-for="link in accountLinks"
            :key="link.match"
            :href="link.href"
            :class="itemClass(isActive(link.match))"
            :aria-label="link.label"
            :title="labeled ? undefined : link.label"
            prefetch
            @click="onNavigate"
        >
            <component :is="link.icon" :class="iconClass" />
            <span v-if="labeled">{{ link.label }}</span>
        </Link>

        <button
            type="button"
            :class="[itemClass(false), labeled ? 'justify-start' : '']"
            aria-label="Log out"
            :title="labeled ? undefined : 'Log out'"
            :disabled="logoutForm.processing"
            @click="logout"
        >
            <LogOut :class="iconClass" />
            <span v-if="labeled">Log out</span>
        </button>
    </div>
</template>
