<script setup lang="ts">
import BrandName from '@/components/BrandName.vue';
import { formatRest } from '@/routines/lib/formatRest';
import type { ExerciseProfileOption } from '@/settings/types';
import { computed } from 'vue';

const model = defineModel<number | null>({ required: true });

const props = withDefaults(
    defineProps<{
        profiles: ExerciseProfileOption[];
        required?: boolean;
        disabled?: boolean;
        outdated?: boolean;
        label?: string;
        variant?: 'routine' | 'compact';
    }>(),
    {
        required: true,
        disabled: false,
        outdated: false,
        label: 'Profile',
        variant: 'routine',
    },
);

const selected = computed(() => props.profiles.find((profile) => profile.id === model.value) ?? null);
const visibleProfiles = computed(() => props.profiles.filter((profile) => profile.status === 'published' || profile.id === model.value));
const optionLabel = (profile: ExerciseProfileOption): string =>
    `${profile.display_name}${profile.is_default ? ' (Default)' : ''}${profile.status === 'archived' ? ' (Archived)' : ''}${
        props.outdated && profile.id === model.value ? ' (Update available)' : ''
    }`;
</script>

<template>
    <div class="space-y-2">
        <label class="flex flex-col gap-1 text-sm text-muted-foreground">
            {{ props.label }}
            <select
                v-model="model"
                :required="props.required"
                :disabled="props.disabled"
                class="rounded-xl border border-border bg-card px-3 py-2 text-foreground outline-none focus:border-primary"
                :class="props.variant === 'compact' ? 'w-full text-sm' : 'w-full text-base'"
            >
                <option v-if="!props.required" :value="null">Custom settings</option>
                <option v-for="profile in visibleProfiles" :key="profile.id" :value="profile.id" :disabled="profile.status === 'archived'">
                    {{ optionLabel(profile) }}
                </option>
            </select>
        </label>

        <div v-if="selected" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <span class="font-semibold text-foreground">
                <template v-if="selected.kind === 'preset'"><BrandName class="mr-1 text-xs" />{{ selected.name }}</template>
                <template v-else>{{ selected.display_name }}</template>
            </span>
            <span>Target {{ selected.target_reps }}</span>
            <span>Floor {{ selected.floor }}</span>
            <details>
                <summary class="cursor-pointer text-primary hover:underline">Profile Details</summary>
                <div class="mt-2 space-y-1">
                    <p>Working rest: {{ formatRest(selected.working_rest_seconds) }}</p>
                    <p>
                        Warm-up:
                        {{
                            selected.warm_up_steps.length ? selected.warm_up_steps.map((step) => `${step.percent}%×${step.reps}`).join(', ') : 'None'
                        }}
                    </p>
                </div>
            </details>
        </div>
    </div>
</template>
