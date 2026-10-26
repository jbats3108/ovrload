<script setup lang="ts">
import { computed } from 'vue';

export type UpcomingPreview = {
    exerciseName: string;
    groupLabel: string;
    setNumber: number;
    setCount: number;
    blockPosition: number;
    weightLabel: string | null;
    reps: number | null;
    isDropset: boolean;
    plateStack: string | null;
    letter?: string | null;
};

const props = defineProps<{
    upcoming: UpcomingPreview;
    weightUnit: string;
    /** When set (upcoming incomplete superset round), show both exercises instead of a single next set. */
    pair?: UpcomingPreview[] | null;
}>();

const showPair = computed(() => (props.pair?.length ?? 0) >= 2);
</script>

<template>
    <div class="w-full max-w-md rounded-xl border border-border bg-card/60 px-5 py-4 text-center">
        <p class="text-base font-medium text-muted-foreground">Up Next:</p>

        <template v-if="showPair && pair">
            <div class="mt-3 space-y-4">
                <div v-for="item in pair" :key="`${item.letter}-${item.exerciseName}`" class="space-y-1">
                    <p class="text-xl font-semibold">
                        <span v-if="item.letter" class="font-mono text-primary">{{ item.letter }} · </span>
                        {{ item.exerciseName }}
                    </p>
                    <p v-if="item.weightLabel != null || item.reps != null" class="font-mono text-lg font-semibold text-foreground">
                        <span v-if="item.weightLabel != null">{{ item.weightLabel }}{{ weightUnit }}</span>
                        <span v-if="item.reps != null"> × {{ item.reps }}</span>
                    </p>
                    <p v-if="item.plateStack" class="font-mono text-sm text-muted-foreground">
                        {{ item.plateStack }}
                    </p>
                </div>
            </div>
            <p class="mt-4 text-base text-muted-foreground">
                {{ upcoming.groupLabel }} · Set {{ upcoming.setNumber }}/{{ upcoming.setCount }} · Superset
            </p>
        </template>

        <template v-else>
            <p class="mt-2 text-xl font-semibold">{{ upcoming.exerciseName }}</p>
            <p class="mt-2 text-base text-muted-foreground">
                {{ upcoming.groupLabel }} · Set {{ upcoming.setNumber }}/{{ upcoming.setCount }}
                <span v-if="upcoming.isDropset"> · Dropset</span>
            </p>
            <p v-if="upcoming.weightLabel != null || upcoming.reps != null" class="mt-3 font-mono text-2xl font-semibold text-foreground">
                <span v-if="upcoming.weightLabel != null">{{ upcoming.weightLabel }}{{ weightUnit }}</span>
                <span v-if="upcoming.reps != null"> × {{ upcoming.reps }}</span>
            </p>
            <p v-if="upcoming.plateStack" class="mt-3 font-mono text-base text-muted-foreground">
                {{ upcoming.plateStack }}
            </p>
        </template>
    </div>
</template>
