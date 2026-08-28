<script setup lang="ts">
import ExercisePicker from '@/routines/components/ExercisePicker.vue';
import UpcomingCard from '@/workouts/components/UpcomingCard.vue';
import { useWorkoutPlayer } from '@/workouts/composables/useWorkoutPlayer';
import { computed, ref } from 'vue';

const {
    restLabel,
    upcoming,
    workout,
    skipRest,
    canSkipRestOfBlock,
    skipRestOfBlock,
    mutating,
    exerciseCatalog,
    muscleGroups,
    equipmentOptions,
    addAdHocExercise,
    pendingMidBlockBump,
    acceptMidBlockBump,
    declineMidBlockBump,
} = useWorkoutPlayer();

const confirmingSkip = ref(false);
const adHocExerciseId = ref<number | null>(null);

const midBlockBumpDescription = computed(() => {
    if (!pendingMidBlockBump.value) {
        return '';
    }

    return `Load ${pendingMidBlockBump.value.suggestedWeightKg}${workout.value.weight_unit} on the next working set?`;
});

const selectAdHocExercise = (exerciseId: number | null): void => {
    adHocExerciseId.value = null;
    addAdHocExercise(exerciseId);
};

function requestSkip() {
    confirmingSkip.value = true;
}

function cancelSkip() {
    confirmingSkip.value = false;
}

function confirmSkip() {
    confirmingSkip.value = false;
    skipRest();
}
</script>

<template>
    <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
        <p class="text-sm tracking-widest text-muted-foreground uppercase">Rest</p>
        <p class="font-mono text-6xl font-semibold text-primary">{{ restLabel }}</p>
        <UpcomingCard v-if="upcoming" class="mt-2" :upcoming="upcoming" :weight-unit="workout.weight_unit" />
        <div v-if="pendingMidBlockBump" class="w-full max-w-md rounded-2xl border border-primary/30 bg-primary/5 px-4 py-3 text-left">
            <p class="text-sm font-medium text-foreground">Bump next set?</p>
            <p class="mt-1 text-sm text-muted-foreground">{{ midBlockBumpDescription }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground"
                    @click="acceptMidBlockBump"
                >
                    Bump
                </button>
                <button type="button" class="rounded-full border border-border px-4 py-1.5 text-sm" @click="declineMidBlockBump">Keep weight</button>
            </div>
        </div>
        <div v-if="confirmingSkip" class="flex items-center gap-3">
            <span class="text-sm text-muted-foreground">Skip rest?</span>
            <button type="button" class="rounded-full border border-border px-4 py-1.5 text-sm" @click="cancelSkip">Cancel</button>
            <button type="button" class="rounded-full bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground" @click="confirmSkip">
                Skip
            </button>
        </div>
        <div v-else class="flex flex-wrap items-center justify-center gap-3">
            <button type="button" class="rounded-full border border-border px-5 py-2 text-sm" @click="requestSkip">Skip</button>
            <ExercisePicker
                v-if="workout.status === 'in_progress'"
                :model-value="adHocExerciseId"
                :catalog="exerciseCatalog"
                :muscle-groups="muscleGroups"
                :equipment-options="equipmentOptions"
                variant="action"
                trigger-label="Add exercise to workout"
                :disabled="mutating"
                @update:model-value="selectAdHocExercise"
            />
            <button
                v-if="canSkipRestOfBlock"
                type="button"
                class="rounded-full border border-border px-5 py-2 text-sm text-muted-foreground hover:text-foreground disabled:opacity-50"
                :disabled="mutating"
                @click="skipRestOfBlock"
            >
                Skip rest of group
            </button>
        </div>
    </div>
</template>
