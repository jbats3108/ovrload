<script setup lang="ts">
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import { canSetupAfterBlock } from '@/routines/lib/blocks';
import { computed } from 'vue';

const { blockIndex, variant = 'desktop' } = defineProps<{
    blockIndex: number;
    variant?: 'desktop' | 'mobile';
}>();

const { form, toggleSuperset, swapSupersetExercises } = useRoutineEditor();

const block = computed(() => form.blocks[blockIndex]);
const canSetupAfter = computed(() => canSetupAfterBlock(blockIndex, form.blocks.length));
const setupAfterLabel = computed(() => (variant === 'desktop' ? 'Setup before next exercise' : 'Setup after exercise'));
</script>

<template>
    <div :class="variant === 'desktop' ? 'flex max-w-full flex-col gap-1.5 text-xs' : 'flex flex-wrap gap-4 text-sm'">
        <label :class="variant === 'desktop' ? 'flex items-start gap-1.5' : 'flex items-center gap-1.5'">
            <input type="checkbox" class="mt-0.5 shrink-0" :checked="block.is_superset" @change="toggleSuperset(block)" />
            <span class="min-w-0 leading-snug">Superset</span>
        </label>
        <button
            v-if="block.is_superset"
            type="button"
            class="text-left text-primary underline-offset-2 hover:underline"
            :class="variant === 'desktop' ? 'pl-[1.375rem] leading-snug' : ''"
            data-swap-superset
            @click="swapSupersetExercises(block)"
        >
            Swap A↔B
        </button>
        <label
            :class="[
                variant === 'desktop' ? 'flex items-start gap-1.5' : 'flex items-center gap-1.5',
                block.warm_up.steps.length ? '' : 'opacity-40',
            ]"
            :title="block.warm_up.steps.length ? undefined : 'Add warm-up steps first'"
        >
            <input
                v-model="form.blocks[blockIndex].has_setup_after_warm_up"
                type="checkbox"
                class="mt-0.5 shrink-0"
                :disabled="!block.warm_up.steps.length"
            />
            <span class="min-w-0 leading-snug">Setup before working</span>
        </label>
        <label
            :class="[variant === 'desktop' ? 'flex items-start gap-1.5' : 'flex items-center gap-1.5', canSetupAfter ? '' : 'opacity-40']"
            :title="canSetupAfter ? undefined : 'Not on the final exercise'"
        >
            <input v-model="form.blocks[blockIndex].has_setup_after" type="checkbox" class="mt-0.5 shrink-0" :disabled="!canSetupAfter" />
            <span class="min-w-0 leading-snug">{{ setupAfterLabel }}</span>
        </label>
    </div>
</template>
