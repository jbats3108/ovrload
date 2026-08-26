<script setup lang="ts">
import ExercisePicker from '@/routines/components/ExercisePicker.vue';
import { useWorkoutPlayer } from '@/workouts/composables/useWorkoutPlayer';
import { ref } from 'vue';

const {
    workout,
    progressLabel,
    finishWorkout,
    abandonWorkout,
    leaveWorkout,
    mutating,
    exerciseCatalog,
    muscleGroups,
    equipmentOptions,
    addAdHocExercise,
} = useWorkoutPlayer();

const adHocExerciseId = ref<number | null>(null);

const selectAdHocExercise = (exerciseId: number | null): void => {
    adHocExerciseId.value = null;
    addAdHocExercise(exerciseId);
};
</script>

<template>
    <header class="flex items-center justify-between border-b border-border px-4 py-3">
        <div class="min-w-0">
            <p class="text-xs tracking-wide text-muted-foreground uppercase">{{ workout.mode }}</p>
            <h1 class="truncate text-lg font-semibold">{{ workout.routine_name }}</h1>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <div class="font-mono text-sm text-muted-foreground">{{ progressLabel }}</div>
            <ExercisePicker
                v-if="workout.status === 'in_progress'"
                :model-value="adHocExerciseId"
                :catalog="exerciseCatalog"
                :muscle-groups="muscleGroups"
                :equipment-options="equipmentOptions"
                variant="compact"
                trigger-label="+ Exercise"
                :disabled="mutating"
                @update:model-value="selectAdHocExercise"
            />
            <button
                v-if="workout.status === 'in_progress'"
                type="button"
                class="rounded-md border border-border px-3 py-1.5 text-sm text-foreground hover:bg-secondary disabled:opacity-50"
                :disabled="mutating"
                @click="finishWorkout"
            >
                Finish
            </button>
            <button
                v-if="workout.status === 'in_progress'"
                type="button"
                class="rounded-md border border-destructive/40 px-3 py-1.5 text-sm text-destructive disabled:opacity-50"
                :disabled="mutating"
                @click="abandonWorkout"
            >
                Abandon
            </button>
            <button
                type="button"
                class="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground hover:text-foreground"
                @click="leaveWorkout"
            >
                Leave
            </button>
        </div>
    </header>
</template>
