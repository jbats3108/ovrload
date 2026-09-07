<script setup lang="ts">
import LogSetSheet from '@/workouts/components/LogSetSheet.vue';
import PlateGuideCard from '@/workouts/components/PlateGuideCard.vue';
import { useWorkoutPlayer } from '@/workouts/composables/useWorkoutPlayer';
import { skipGroupLabel } from '@/workouts/lib/park';
import { plannedSetCount, workingRoundsInBlock } from '@/workouts/lib/sets';
import { computed } from 'vue';

const {
    stageWeightKg,
    stageDropsetWeights,
    canPromoteToDropset,
    canDemoteFromDropset,
    canAddWorkingSet,
    canRemoveWorkingSet,
    canRemoveAdHocBlock,
    canSkipRestOfBlock,
    canParkForLater,
    openLogSheet,
    cancelLogSheet,
    completeSet,
    addDropSegment,
    removeDropSegment,
    promoteToDropset,
    demoteFromDropset,
    addWorkingSet,
    removeWorkingSet,
    removeAdHocBlock,
    skipRestOfBlock,
    parkForLater,
    applyNearestLoad,
    applyStageNearestLoad,
    changeLogPlate,
    changeStagePlate,
    groupLabel,
    handleLogWeightInput,
    formatPlateStack,
    plateProfile,
    stageFormatPlateStack,
    plateLoad,
    stagePlateLoad,
    workout,
    current,
    setForm,
    mutating,
    draftSegments,
    logSheetOpen,
    logProgressionHints,
    supersetNext,
    circuitNext,
    isCircuitBlock,
    isTimedSet,
    canSkipExercise,
    canSkipRound,
    timedSecondsLeft,
    timedIsRunning,
    startTimedCountdown,
    pauseTimedCountdown,
    resetTimedCountdown,
    finishTimedEarly,
    skipExercise,
    skipCurrentRound,
} = useWorkoutPlayer();

const skipLabel = computed(() => (current.value ? skipGroupLabel(current.value.block) : 'Skip group'));

const totalRounds = computed(() => (current.value ? workingRoundsInBlock(current.value.block) : 0));

const circuitExerciseIndex = computed(() => {
    if (!current.value || current.value.block.type !== 'circuit') {
        return null;
    }
    const round = current.value.block.sets
        .filter((s) => s.group_type === current.value!.set.group_type && s.set_index === current.value!.set.set_index)
        .sort((a, b) => a.workout_block_exercise_id - b.workout_block_exercise_id);
    const pos = round.findIndex((s) => s.id === current.value!.set.id);
    return {
        number: pos + 1,
        total: round.length,
    };
});

const formatDuration = (seconds: number): string => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
};

const unlockInput = (event: PointerEvent) => {
    const input = event.currentTarget;
    if (!(input instanceof HTMLInputElement) || !input.readOnly) {
        return;
    }

    input.readOnly = false;
    input.focus();
};
</script>

<template>
    <div v-if="current" class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-4 py-6 text-center">
        <div class="mx-auto flex min-h-full w-full max-w-lg flex-col">
            <div class="flex flex-1 flex-col items-center justify-center gap-8">
                <div class="space-y-2">
                    <h2 class="text-3xl leading-tight font-semibold">{{ current.set.exercise_name }}</h2>
                    <p v-if="isCircuitBlock" class="text-2xl font-bold tracking-tight text-primary">
                        Round {{ current.set.set_index + 1 }} of {{ totalRounds }}
                        <span v-if="circuitExerciseIndex" class="block text-base font-normal text-muted-foreground sm:inline sm:text-lg">
                            · Exercise {{ circuitExerciseIndex.number }} of {{ circuitExerciseIndex.total }}
                        </span>
                    </p>
                    <p v-else class="text-2xl font-bold tracking-tight text-primary">
                        {{ groupLabel(current.set.group_type) }}
                        {{ current.set.set_index + 1 }} of {{ plannedSetCount(current.block, current.set) }}
                    </p>
                </div>

                <p class="font-mono text-3xl font-semibold tracking-tight text-foreground">
                    Target
                    <template v-if="current.set.is_dropset">
                        {{ stageDropsetWeights.join(' → ') }}{{ workout.weight_unit }}
                        <span v-if="current.set.target_reps != null"> × {{ current.set.target_reps }}</span>
                    </template>
                    <template v-else-if="isTimedSet">
                        <span v-if="stageWeightKg != null && stageWeightKg > 0">{{ stageWeightKg }}{{ workout.weight_unit }} × </span>
                        <span>{{ current.set.target_duration_seconds }}s</span>
                    </template>
                    <template v-else>
                        <span v-if="stageWeightKg != null">{{ stageWeightKg }}{{ workout.weight_unit }}</span>
                        <span v-if="current.set.target_reps != null"> × {{ current.set.target_reps }}</span>
                    </template>
                </p>

                <!-- Timed Exercise Interactive Countdown -->
                <div v-if="isTimedSet" class="flex w-full max-w-sm flex-col items-center gap-4 rounded-2xl border border-border bg-card/60 p-6">
                    <p class="font-mono text-5xl font-bold tracking-tight text-foreground">
                        {{ formatDuration(timedSecondsLeft) }}
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <button
                            v-if="!timedIsRunning"
                            type="button"
                            class="rounded-full bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground transition-transform hover:scale-105 active:scale-95"
                            @click="startTimedCountdown"
                        >
                            Start timer
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-full border border-border px-5 py-2.5 text-sm font-semibold text-foreground hover:bg-secondary"
                            @click="pauseTimedCountdown"
                        >
                            Pause
                        </button>
                        <button
                            v-if="timedIsRunning || timedSecondsLeft < (current.set.target_duration_seconds ?? 30)"
                            type="button"
                            class="rounded-full border border-border px-4 py-2.5 text-xs text-muted-foreground hover:bg-secondary hover:text-foreground"
                            @click="resetTimedCountdown"
                        >
                            Reset
                        </button>
                        <button
                            v-if="timedIsRunning || timedSecondsLeft < (current.set.target_duration_seconds ?? 30)"
                            type="button"
                            class="rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500"
                            @click="finishTimedEarly"
                        >
                            Done ({{ Math.max(0, (current.set.target_duration_seconds ?? 30) - timedSecondsLeft) }}s)
                        </button>
                    </div>
                </div>

                <PlateGuideCard
                    v-if="stagePlateLoad && stageFormatPlateStack"
                    class="w-full"
                    :plate-load="stagePlateLoad"
                    :format-plate-stack="stageFormatPlateStack"
                    :weight-unit="workout.weight_unit"
                    :plate-profile="plateProfile"
                    @change-plate="changeStagePlate"
                    @apply-nearest="applyStageNearestLoad"
                />

                <div class="space-y-2">
                    <p
                        v-if="current.set.is_dropset || current.block.is_superset || isCircuitBlock"
                        class="text-sm font-semibold tracking-wide text-foreground"
                    >
                        <template v-if="current.set.is_dropset">Dropset</template>
                        <template v-if="current.set.is_dropset && current.block.is_superset"> · </template>
                        <template v-if="current.block.is_superset">Superset</template>
                        <template v-if="isCircuitBlock">Circuit</template>
                    </p>
                    <p v-if="supersetNext" class="text-base text-muted-foreground">{{ supersetNext.label }}</p>
                    <p v-if="circuitNext" class="text-base text-muted-foreground">{{ circuitNext.label }}</p>
                </div>
            </div>

            <div class="mt-6 flex w-full shrink-0 flex-col gap-3 pb-4">
                <div class="flex w-full flex-col gap-3">
                    <div
                        v-if="canPromoteToDropset || canDemoteFromDropset || canAddWorkingSet || canRemoveWorkingSet"
                        class="flex flex-wrap items-center justify-center gap-3"
                    >
                        <button
                            v-if="canPromoteToDropset"
                            type="button"
                            class="rounded-full border border-border px-3.5 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="promoteToDropset"
                        >
                            Make dropset
                        </button>
                        <button
                            v-if="canDemoteFromDropset"
                            type="button"
                            class="rounded-full border border-border px-3.5 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="demoteFromDropset"
                        >
                            Single weight
                        </button>
                        <button
                            v-if="canAddWorkingSet"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border border-primary/40 bg-primary/10 px-3.5 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-primary/20 disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="addWorkingSet"
                        >
                            <span class="text-xl leading-none font-semibold">+</span>
                            {{ isCircuitBlock ? 'Round' : 'Set' }}
                        </button>
                        <button
                            v-if="canRemoveWorkingSet"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border border-destructive/40 bg-destructive/10 px-3.5 py-2.5 text-sm font-medium text-destructive transition-colors hover:bg-destructive/20 disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="removeWorkingSet"
                        >
                            <span class="text-xl leading-none font-semibold">−</span>
                            {{ isCircuitBlock ? 'Round' : 'Set' }}
                        </button>
                    </div>

                    <div v-if="canSkipExercise || canSkipRound" class="flex flex-wrap items-center justify-center gap-3">
                        <button
                            v-if="canSkipExercise"
                            type="button"
                            class="rounded-full border border-border px-3.5 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="skipExercise"
                        >
                            Skip exercise
                        </button>
                        <button
                            v-if="canSkipRound"
                            type="button"
                            class="rounded-full border border-border px-3.5 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="skipCurrentRound"
                        >
                            Skip round
                        </button>
                    </div>

                    <div v-if="canParkForLater || canSkipRestOfBlock || canRemoveAdHocBlock" class="flex flex-wrap items-center justify-center gap-3">
                        <button
                            v-if="canParkForLater"
                            type="button"
                            class="rounded-full border border-border px-3.5 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="parkForLater"
                        >
                            Later
                        </button>
                        <button
                            v-if="canSkipRestOfBlock"
                            type="button"
                            class="rounded-full border border-border px-3.5 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="skipRestOfBlock"
                        >
                            {{ skipLabel }}
                        </button>
                        <button
                            v-if="canRemoveAdHocBlock"
                            type="button"
                            class="rounded-full border border-destructive/40 bg-destructive/10 px-3.5 py-2.5 text-sm font-medium text-destructive transition-colors hover:bg-destructive/20 disabled:opacity-50"
                            :disabled="mutating || setForm.processing"
                            @click="removeAdHocBlock"
                        >
                            Remove exercise
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-full bg-primary px-6 py-4 text-base font-semibold text-primary-foreground disabled:opacity-50"
                    :disabled="workout.status !== 'in_progress'"
                    @click="openLogSheet"
                >
                    Done
                </button>
            </div>
        </div>

        <LogSetSheet v-model:open="logSheetOpen">
            <form class="flex min-h-0 flex-1 flex-col gap-4 md:gap-5 md:text-left" @submit.prevent="completeSet">
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto md:flex-none">
                    <div>
                        <p class="text-xs tracking-widest text-muted-foreground uppercase">Log set</p>
                        <h3 class="mt-1 text-xl font-semibold md:text-lg">{{ current.set.exercise_name }}</h3>
                        <p v-if="isCircuitBlock" class="mt-1 text-base font-semibold text-primary">
                            Round {{ current.set.set_index + 1 }} of {{ totalRounds }}
                            <span v-if="circuitExerciseIndex" class="text-sm font-normal text-muted-foreground">
                                · Exercise {{ circuitExerciseIndex.number }} of {{ circuitExerciseIndex.total }}
                            </span>
                        </p>
                        <p v-else class="mt-1 text-base font-semibold text-primary">
                            {{ groupLabel(current.set.group_type) }}
                            {{ current.set.set_index + 1 }} of {{ plannedSetCount(current.block, current.set) }}
                        </p>
                    </div>

                    <div class="space-y-4 pt-10 md:pt-0">
                        <template v-if="current.set.is_dropset">
                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Reps (shared)
                                <input
                                    v-model.number="setForm.reps"
                                    type="number"
                                    min="0"
                                    max="100"
                                    readonly
                                    class="rounded-xl border border-border bg-card px-4 py-3 text-lg text-foreground md:rounded-md md:py-2 md:text-base"
                                    required
                                    @pointerdown="unlockInput"
                                />
                            </label>
                            <div class="space-y-2">
                                <p class="text-xs tracking-wide text-muted-foreground uppercase">Segments</p>
                                <div v-for="(seg, si) in draftSegments" :key="si" class="flex items-center gap-2">
                                    <span class="w-6 font-mono text-xs text-muted-foreground">{{ si + 1 }}</span>
                                    <input
                                        v-model.number="seg.weight_kg"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        readonly
                                        class="flex-1 rounded-xl border border-border bg-card px-4 py-3 text-lg text-foreground md:rounded-md md:py-2 md:text-base"
                                        required
                                        @pointerdown="unlockInput"
                                    />
                                    <span class="text-sm text-muted-foreground">{{ workout.weight_unit }}</span>
                                    <button
                                        type="button"
                                        class="text-sm text-muted-foreground hover:text-destructive disabled:opacity-30"
                                        :disabled="draftSegments.length <= 2"
                                        @click="removeDropSegment(si)"
                                    >
                                        −
                                    </button>
                                </div>
                                <button type="button" class="text-sm text-primary" @click="addDropSegment">+ Drop</button>
                            </div>
                        </template>
                        <template v-else-if="isTimedSet">
                            <div class="flex gap-3">
                                <label class="flex min-w-0 flex-1 flex-col gap-1 text-sm text-muted-foreground">
                                    Weight ({{ workout.weight_unit }})
                                    <input
                                        v-model.number="setForm.weight_kg"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        readonly
                                        class="rounded-xl border border-border bg-card px-4 py-3 text-lg text-foreground md:rounded-md md:py-2 md:text-base"
                                        required
                                        @pointerdown="unlockInput"
                                        @input="handleLogWeightInput"
                                    />
                                </label>
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                        Duration (seconds)
                                        <input
                                            v-model.number="setForm.duration_seconds"
                                            type="number"
                                            min="0"
                                            max="3600"
                                            readonly
                                            class="rounded-xl border border-border bg-card px-4 py-3 text-lg text-foreground md:rounded-md md:py-2 md:text-base"
                                            required
                                            @pointerdown="unlockInput"
                                        />
                                    </label>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <PlateGuideCard
                                v-if="plateLoad && formatPlateStack"
                                :plate-load="plateLoad"
                                :format-plate-stack="formatPlateStack"
                                :weight-unit="workout.weight_unit"
                                :plate-profile="plateProfile"
                                @change-plate="changeLogPlate"
                                @apply-nearest="applyNearestLoad"
                            />
                            <div class="flex gap-3">
                                <label class="flex min-w-0 flex-1 flex-col gap-1 text-sm text-muted-foreground">
                                    Weight ({{ workout.weight_unit }})
                                    <input
                                        v-model.number="setForm.weight_kg"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        readonly
                                        class="rounded-xl border border-border bg-card px-4 py-3 text-lg text-foreground md:rounded-md md:py-2 md:text-base"
                                        required
                                        @pointerdown="unlockInput"
                                        @input="handleLogWeightInput"
                                    />
                                </label>
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                        Reps
                                        <input
                                            v-model.number="setForm.reps"
                                            type="number"
                                            min="0"
                                            max="100"
                                            readonly
                                            class="rounded-xl border border-border bg-card px-4 py-3 text-lg text-foreground md:rounded-md md:py-2 md:text-base"
                                            required
                                            @pointerdown="unlockInput"
                                        />
                                    </label>
                                    <p v-if="logProgressionHints" class="font-mono text-sm text-foreground/70">
                                        {{ logProgressionHints }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex shrink-0 flex-col gap-2 md:flex-row-reverse md:items-center md:justify-start md:gap-3">
                    <button
                        type="submit"
                        class="rounded-full bg-primary px-6 py-4 text-base font-semibold text-primary-foreground disabled:opacity-50 md:rounded-md md:px-4 md:py-2 md:text-sm"
                        :disabled="setForm.processing || workout.status !== 'in_progress'"
                    >
                        Log set
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-border px-6 py-3 text-sm md:rounded-md md:border-transparent md:px-3 md:py-2 md:text-muted-foreground md:hover:bg-secondary md:hover:text-foreground"
                        :disabled="setForm.processing"
                        @click="cancelLogSheet"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </LogSetSheet>
    </div>
</template>
