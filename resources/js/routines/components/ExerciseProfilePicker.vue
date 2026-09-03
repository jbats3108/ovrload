<script setup lang="ts">
import BrandName from '@/components/BrandName.vue';
import { coerceProfileId } from '@/routines/lib/exerciseProfiles';
import { formatRest } from '@/routines/lib/formatRest';
import type { ExerciseProfileOption } from '@/settings/types';
import { formatProfileWarmUpSteps } from '@/shared/warmUpStep';
import { computed } from 'vue';

const model = defineModel<number | null>({ required: true });

const onSelectChange = (event: Event): void => {
    const raw = (event.target as HTMLSelectElement).value;
    model.value = coerceProfileId(raw);
};

const props = withDefaults(
    defineProps<{
        profiles: ExerciseProfileOption[];
        required?: boolean;
        disabled?: boolean;
        outdated?: boolean;
        /** Empty string hides the visible label (e.g. Profile column header already names it). */
        label?: string;
        variant?: 'routine' | 'compact';
        /** When false, hide Target/Floor chips and Profile Details under the select. */
        showMeta?: boolean;
    }>(),
    {
        required: true,
        disabled: false,
        outdated: false,
        label: 'Profile',
        variant: 'routine',
        showMeta: true,
    },
);

const selected = computed(() => props.profiles.find((profile) => profile.id === model.value) ?? null);
const visibleProfiles = computed(() => props.profiles.filter((profile) => profile.status === 'published' || profile.id === model.value));
const showLabel = computed(() => props.label.trim() !== '');
const optionLabel = (profile: ExerciseProfileOption): string =>
    `${profile.display_name}${profile.is_default ? ' (Default)' : ''}${profile.status === 'archived' ? ' (Archived)' : ''}${
        props.outdated && profile.id === model.value ? ' (Update available)' : ''
    }`;
</script>

<template>
    <div :class="showLabel || (showMeta && selected) ? 'space-y-2' : ''">
        <label class="flex flex-col gap-1 text-sm text-muted-foreground">
            <span v-if="showLabel">{{ props.label }}</span>
            <select
                :value="model ?? ''"
                :required="props.required"
                :disabled="props.disabled"
                :aria-label="showLabel ? undefined : props.label || 'Profile'"
                class="border border-border bg-card text-foreground outline-none focus:border-primary"
                :class="props.variant === 'compact' ? 'h-8 w-full rounded px-2 py-1 text-sm' : 'w-full rounded-xl px-3 py-2 text-base'"
                @change="onSelectChange"
            >
                <option v-if="!props.required" :value="null">Custom settings</option>
                <option v-for="profile in visibleProfiles" :key="profile.id" :value="profile.id" :disabled="profile.status === 'archived'">
                    {{ optionLabel(profile) }}
                </option>
            </select>
        </label>

        <div v-if="showMeta && selected" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
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
                        {{ selected.warm_up_steps.length ? formatProfileWarmUpSteps(selected.warm_up_steps) : 'None' }}
                    </p>
                </div>
            </details>
        </div>
        <p v-else-if="props.outdated" class="text-[11px] text-amber-400">Update available</p>
    </div>
</template>
