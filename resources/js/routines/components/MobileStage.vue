<script setup lang="ts">
import BlockSetupOptions from '@/routines/components/BlockSetupOptions.vue';
import DeloadAlternateFields from '@/routines/components/DeloadAlternateFields.vue';
import DeloadSettings from '@/routines/components/DeloadSettings.vue';
import DropsetEditor from '@/routines/components/DropsetEditor.vue';
import EditorDisclosure from '@/routines/components/EditorDisclosure.vue';
import ExercisePicker from '@/routines/components/ExercisePicker.vue';
import ExerciseProfilePicker from '@/routines/components/ExerciseProfilePicker.vue';
import RoutineEditorErrors from '@/routines/components/RoutineEditorErrors.vue';
import SaveExerciseProfileDialog from '@/routines/components/SaveExerciseProfileDialog.vue';
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import type { Block, ExerciseProfileOption } from '@/routines/types';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const {
    form,
    active,
    activeBlock,
    activeExerciseIndex,
    warmUpExpanded,
    toggleWarmUpExpanded,
    dropsetsExpanded,
    toggleDropsetsExpanded,
    selectBlockExercise,
    exerciseName,
    removeBlock,
    addBlock,
    trimDropsetsToSetCount,
    formatRest,
    warmUpText,
    setWarmUpText,
    addWarmUpStep,
    removeWarmUpStep,
    clearWarmUp,
    dropsetSummary,
    profileOptions,
    applyProfile,
    setExerciseTarget,
    setExerciseFloor,
    exerciseFloorPlaceholder,
    markSharedCustom,
    exerciseProfileIsOutdated,
    sharedProfileIsOutdated,
    registerProfile,
    save,
    deleteRoutine,
    mutating,
} = useRoutineEditor();

const saveDialogOpen = ref(false);
const saveDialogBlock = ref<Block | null>(null);
const saveDialogExerciseIndex = ref(0);

const openSaveProfile = (block: Block, exerciseIndex: number): void => {
    saveDialogBlock.value = block;
    saveDialogExerciseIndex.value = exerciseIndex;
    saveDialogOpen.value = true;
};

const saveProfile = (profile: ExerciseProfileOption): void => {
    if (saveDialogBlock.value === null) {
        return;
    }

    registerProfile(profile);
    applyProfile(saveDialogBlock.value, profile.id, saveDialogExerciseIndex.value);
};
</script>

<template>
    <div class="flex flex-col md:hidden">
        <div class="px-4 pt-3 pb-4">
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="(b, i) in form.blocks"
                    :key="i"
                    type="button"
                    class="shrink-0 rounded-lg border px-3 py-2 text-left text-sm"
                    :class="i === active ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground'"
                    @click="selectBlockExercise(i, 0)"
                >
                    <div class="font-mono text-xs">{{ i + 1 }}{{ b.is_superset ? ' SS' : '' }}</div>
                    <div class="max-w-28 truncate">{{ exerciseName(b.exercises[0]?.exercise_id) }}</div>
                </button>
            </div>
        </div>

        <div class="mx-auto w-full max-w-lg px-4 pb-4">
            <div class="rounded-2xl border border-border bg-card p-4">
                <DeloadSettings variant="mobile" flush />
            </div>
        </div>

        <main v-if="activeBlock" class="mx-auto flex w-full max-w-lg flex-col gap-4 px-4 pb-4">
            <div class="rounded-2xl border border-border bg-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold">
                        Exercise {{ active + 1 }}
                        <span v-if="activeBlock.is_superset" class="ml-2 text-sm font-normal text-primary">Superset</span>
                    </h2>
                    <button type="button" class="text-xs text-destructive" @click="removeBlock(active)">Remove</button>
                </div>

                <div v-for="(ex, ei) in activeBlock.exercises" :key="ei" class="mb-4 last:mb-0">
                    <p v-if="activeBlock.is_superset" class="mb-1 font-mono text-xs text-muted-foreground">
                        {{ ei === 0 ? 'A' : 'B' }}
                    </p>
                    <ExercisePicker
                        v-model="ex.exercise_id"
                        variant="mobile"
                        :active="ei === activeExerciseIndex"
                        @open="selectBlockExercise(active, ei)"
                    />
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label class="block">
                            <span class="text-xs text-muted-foreground">Working kg</span>
                            <input
                                v-model.number="ex.working_weight_kg"
                                type="number"
                                step="0.01"
                                min="0"
                                inputmode="decimal"
                                class="mt-1 w-full rounded-xl border border-border bg-background px-3 py-2 text-center text-2xl font-semibold tabular-nums outline-none focus:border-primary"
                            />
                        </label>
                        <ExerciseProfilePicker
                            :model-value="ex.exercise_profile_id ?? null"
                            :profiles="profileOptions"
                            variant="compact"
                            :required="false"
                            label="Profile"
                            :outdated="exerciseProfileIsOutdated(activeBlock, ei)"
                            @update:model-value="applyProfile(activeBlock, $event, ei)"
                        />
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label class="block">
                            <span class="text-xs text-muted-foreground">Target reps</span>
                            <input
                                :value="ex.prescribed_reps"
                                type="number"
                                min="1"
                                max="100"
                                data-exercise-target
                                class="mt-1 w-full rounded-xl border border-border bg-background px-3 py-2 font-mono text-lg"
                                @input="setExerciseTarget(ex, ($event.target as HTMLInputElement).value)"
                            />
                        </label>
                        <label class="block">
                            <span class="text-xs text-muted-foreground">Floor</span>
                            <input
                                :value="ex.achievement_floor ?? ''"
                                type="number"
                                min="1"
                                max="100"
                                data-exercise-floor
                                :placeholder="exerciseFloorPlaceholder(activeBlock, ei)"
                                class="mt-1 w-full rounded-xl border border-border bg-background px-3 py-2 font-mono text-lg"
                                @input="setExerciseFloor(ex, ($event.target as HTMLInputElement).value)"
                            />
                        </label>
                    </div>
                    <button
                        v-if="ex.exercise_profile_id == null"
                        type="button"
                        class="mt-2 text-left text-xs text-primary underline-offset-2 hover:underline"
                        @click="openSaveProfile(activeBlock, ei)"
                    >
                        Save as profile
                    </button>
                    <DeloadAlternateFields
                        :deload-exercise-id="ex.deload_exercise_id"
                        :deload-working-weight-kg="ex.deload_working_weight_kg"
                        :working-weight-kg="ex.working_weight_kg"
                        variant="mobile"
                        @update:deload-exercise-id="ex.deload_exercise_id = $event"
                        @update:deload-working-weight-kg="ex.deload_working_weight_kg = $event"
                    />
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Blank Floor uses that exercise's profile. Custom exercises fall back to Preferences. Weight bumps follow the exercise Target reps.
                </p>

                <div class="grid grid-cols-2 gap-2 border-t border-border pt-3">
                    <label>
                        <span class="text-xs text-muted-foreground">Working sets</span>
                        <input
                            v-model.number="activeBlock.working.set_count"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded-xl border border-border bg-background px-3 py-2 font-mono text-lg"
                            @change="trimDropsetsToSetCount(activeBlock)"
                        />
                    </label>
                    <label>
                        <span class="text-xs text-muted-foreground">Rest</span>
                        <details class="mt-1">
                            <summary class="cursor-pointer rounded-xl border border-border bg-background px-3 py-2 font-mono text-lg">
                                {{ formatRest(activeBlock.working.rest_seconds) }}
                                <span v-if="sharedProfileIsOutdated(activeBlock)" class="text-sm text-amber-400">· Update available</span>
                            </summary>
                            <input
                                v-model.number="activeBlock.working.rest_seconds"
                                type="number"
                                min="0"
                                max="3600"
                                step="15"
                                class="mt-1 w-full rounded-xl border border-border bg-background px-3 py-2 font-mono text-lg"
                                @input="markSharedCustom(activeBlock)"
                            />
                        </details>
                    </label>
                </div>

                <EditorDisclosure
                    v-if="!activeBlock.is_superset"
                    data-dropset-editor
                    :expanded="dropsetsExpanded"
                    label="Dropsets"
                    :summary="dropsetSummary(activeBlock) || 'None'"
                    @toggle="toggleDropsetsExpanded"
                >
                    <DropsetEditor :block="activeBlock" variant="mobile" />
                </EditorDisclosure>

                <EditorDisclosure
                    :expanded="warmUpExpanded"
                    label="Warm-up"
                    :summary="activeBlock.warm_up.steps.length ? warmUpText(activeBlock) : 'None'"
                    @toggle="toggleWarmUpExpanded"
                >
                    <div class="space-y-2">
                        <label class="block">
                            <span class="text-xs text-muted-foreground">Compact (40%×5, 60%×3)</span>
                            <input
                                :value="warmUpText(activeBlock)"
                                class="mt-1 w-full rounded-xl border border-border bg-background px-3 py-2 font-mono text-sm text-primary/90"
                                @change="
                                    markSharedCustom(activeBlock);
                                    setWarmUpText(activeBlock, ($event.target as HTMLInputElement).value);
                                "
                            />
                        </label>
                        <label class="block">
                            <span class="text-xs text-muted-foreground">Warm-up rest ({{ formatRest(activeBlock.warm_up.rest_seconds) }})</span>
                            <input
                                v-model.number="activeBlock.warm_up.rest_seconds"
                                type="number"
                                min="0"
                                step="15"
                                class="mt-1 w-full rounded-xl border border-border bg-background px-3 py-2 font-mono text-lg"
                                @input="markSharedCustom(activeBlock)"
                            />
                        </label>
                        <div v-for="(step, si) in activeBlock.warm_up.steps" :key="si" class="flex items-center gap-1.5">
                            <select
                                v-model="step.mode"
                                class="rounded-lg border border-border bg-background px-2 py-1.5 text-xs"
                                aria-label="Warm-up mode"
                                @change="
                                    if (step.mode === 'bar') step.percent = undefined;
                                    else if (step.percent == null) step.percent = 50;
                                    markSharedCustom(activeBlock);
                                "
                            >
                                <option value="percent">%</option>
                                <option value="bar">Bar</option>
                            </select>
                            <input
                                v-if="(step.mode ?? 'percent') === 'percent'"
                                v-model.number="step.percent"
                                type="number"
                                min="1"
                                max="100"
                                class="w-16 rounded-lg border border-border bg-background px-2 py-1.5 font-mono text-sm"
                                aria-label="Warm-up percent"
                                @input="markSharedCustom(activeBlock)"
                            />
                            <span class="text-xs text-muted-foreground">×</span>
                            <input
                                v-model.number="step.reps"
                                type="number"
                                min="1"
                                max="100"
                                class="w-14 rounded-lg border border-border bg-background px-2 py-1.5 font-mono text-sm"
                                aria-label="Warm-up reps"
                                @input="markSharedCustom(activeBlock)"
                            />
                            <label
                                v-if="si < activeBlock.warm_up.steps.length - 1"
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                title="Setup after this warm-up"
                            >
                                <input v-model="step.has_setup_after" type="checkbox" />
                                Setup
                            </label>
                            <button
                                type="button"
                                class="ml-auto text-xs text-muted-foreground hover:text-destructive"
                                @click="
                                    markSharedCustom(activeBlock);
                                    removeWarmUpStep(activeBlock, si);
                                "
                            >
                                −
                            </button>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="text-xs text-primary"
                                @click="
                                    markSharedCustom(activeBlock);
                                    addWarmUpStep(activeBlock);
                                "
                            >
                                + Step
                            </button>
                            <button
                                v-if="activeBlock.warm_up.steps.length"
                                type="button"
                                class="text-xs text-muted-foreground hover:text-destructive"
                                @click="
                                    markSharedCustom(activeBlock);
                                    clearWarmUp(activeBlock);
                                "
                            >
                                Clear warm-up
                            </button>
                        </div>
                    </div>
                </EditorDisclosure>

                <div class="mt-3 border-t border-border pt-3">
                    <BlockSetupOptions :block-index="active" variant="mobile" />
                </div>
            </div>

            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-dashed border-border px-4 py-3 text-sm text-muted-foreground hover:border-primary hover:text-primary"
                    @click="addBlock(false)"
                >
                    Add exercise
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-dashed border-border px-4 py-3 text-sm text-muted-foreground hover:border-primary hover:text-primary"
                    @click="addBlock(true)"
                >
                    Add superset
                </button>
            </div>
        </main>

        <div v-else class="px-4 pb-4">
            <p class="py-8 text-center text-muted-foreground">No exercises yet.</p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-dashed border-border px-4 py-3 text-sm text-muted-foreground hover:border-primary hover:text-primary"
                    @click="addBlock(false)"
                >
                    Add exercise
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-dashed border-border px-4 py-3 text-sm text-muted-foreground hover:border-primary hover:text-primary"
                    @click="addBlock(true)"
                >
                    Add superset
                </button>
            </div>
        </div>

        <div class="mx-auto flex w-full max-w-lg flex-col gap-3 px-4 pb-4">
            <RoutineEditorErrors />
            <div class="flex justify-center gap-2">
                <Link :href="route('dashboard')" class="rounded-full border border-border bg-background px-4 py-3 text-sm text-muted-foreground">
                    Cancel
                </Link>
                <button
                    type="button"
                    class="rounded-full border border-destructive/50 bg-background px-4 py-3 text-sm text-destructive disabled:opacity-50"
                    :disabled="mutating || form.processing"
                    @click="deleteRoutine"
                >
                    Delete
                </button>
                <button
                    type="button"
                    class="rounded-full bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                    :disabled="form.processing || mutating"
                    @click="save"
                >
                    Save
                </button>
            </div>
        </div>
        <SaveExerciseProfileDialog
            v-if="saveDialogBlock"
            v-model:open="saveDialogOpen"
            :block="saveDialogBlock"
            :exercise-index="saveDialogExerciseIndex"
            @saved="saveProfile"
        />
    </div>
</template>
