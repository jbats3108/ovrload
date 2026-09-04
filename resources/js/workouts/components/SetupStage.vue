<script setup lang="ts">
import PlateGuideCard from '@/workouts/components/PlateGuideCard.vue';
import { useWorkoutPlayer } from '@/workouts/composables/useWorkoutPlayer';

const { setupHint, setupSteps, workout, plateProfile, acknowledgeSetup, changeSetupPlate, applySetupNearestLoad } = useWorkoutPlayer();
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-6 py-4 text-center">
        <div class="mx-auto flex min-h-full w-full max-w-md flex-col">
            <div class="flex flex-1 flex-col items-center justify-center gap-6">
                <div class="space-y-2">
                    <p class="text-sm tracking-widest text-muted-foreground uppercase">Setup</p>
                    <p class="text-2xl font-semibold">Change equipment, then continue</p>
                    <p class="text-lg font-medium text-foreground">{{ setupHint }}</p>
                </div>

                <div class="flex w-full flex-col gap-6">
                    <div v-for="step in setupSteps" :key="step.setId" class="space-y-3 text-center">
                        <p v-if="step.letter" class="font-mono text-sm font-semibold tracking-wide text-primary uppercase">
                            {{ step.letter }}
                        </p>
                        <p class="text-xl font-semibold">{{ step.exerciseName }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ step.groupLabel }} · Set {{ step.setNumber }}/{{ step.setCount }}
                            <span v-if="step.isDropset"> · Dropset</span>
                            <span v-if="setupSteps.length > 1"> · Superset</span>
                        </p>
                        <p
                            v-if="step.weightLabel != null || step.reps != null"
                            class="font-mono text-2xl font-semibold tracking-tight text-foreground"
                        >
                            <span v-if="step.weightLabel != null">{{ step.weightLabel }}{{ workout.weight_unit }}</span>
                            <span v-if="step.reps != null"> × {{ step.reps }}</span>
                        </p>
                        <PlateGuideCard
                            v-if="step.plateLoad && step.formatPlateStack"
                            :plate-load="step.plateLoad"
                            :format-plate-stack="step.formatPlateStack"
                            :weight-unit="workout.weight_unit"
                            :plate-profile="plateProfile"
                            @change-plate="(denominationG, change) => changeSetupPlate(step.setId, denominationG, change)"
                            @apply-nearest="applySetupNearestLoad(step.setId)"
                        />
                    </div>
                </div>
            </div>

            <button
                type="button"
                class="mt-6 w-full shrink-0 rounded-full bg-primary px-8 py-3 text-sm font-semibold text-primary-foreground"
                @click="acknowledgeSetup"
            >
                Setup done
            </button>
        </div>
    </div>
</template>
