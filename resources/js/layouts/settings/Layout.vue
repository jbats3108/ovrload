<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useZiggyRoute } from '@/shared/composables/useZiggyRoute';
import { accountNavItems, isPathActive, preferencesNavItems } from '@/shared/lib/appNav';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    section: 'Preferences' | 'Account';
}

const props = defineProps<Props>();
const page = usePage();
const route = useZiggyRoute();
const logoutForm = useForm({});

const sectionNavItems = computed(() => (props.section === 'Preferences' ? preferencesNavItems(route) : accountNavItems(route)));
const sectionDescription = computed(() =>
    props.section === 'Preferences' ? 'Customize how workouts and the app work' : 'Manage your profile, password, and account data',
);

const currentPath = computed(() => page.url.split('?')[0]);

const logout = () => {
    if (logoutForm.processing) {
        return;
    }
    router.flushAll();
    logoutForm.post(route('logout'));
};
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="props.section" :description="sectionDescription" />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col gap-1">
                    <Button
                        v-for="item in sectionNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': isPathActive(currentPath, item.match) }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.label }}
                        </Link>
                    </Button>
                    <Button
                        v-if="props.section === 'Account'"
                        type="button"
                        variant="ghost"
                        class="w-full justify-start text-muted-foreground"
                        :disabled="logoutForm.processing"
                        @click="logout"
                    >
                        Log out
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
