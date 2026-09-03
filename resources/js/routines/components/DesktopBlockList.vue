<script setup lang="ts">
import BlockSetupOptions from '@/routines/components/BlockSetupOptions.vue';
import DeloadAlternateFields from '@/routines/components/DeloadAlternateFields.vue';
import DesktopRoutineSettings from '@/routines/components/DesktopRoutineSettings.vue';
import DropsetEditor from '@/routines/components/DropsetEditor.vue';
import EditorDisclosure from '@/routines/components/EditorDisclosure.vue';
import ExercisePicker from '@/routines/components/ExercisePicker.vue';
import ExerciseProfilePicker from '@/routines/components/ExerciseProfilePicker.vue';
import SaveExerciseProfileDialog from '@/routines/components/SaveExerciseProfileDialog.vue';
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import {
    blockSharedRecipeIsCustom,
    exerciseRecipeIsCustom,
    formatBlockRestSummary,
    formatBlockWarmUpSummary,
    formatExerciseTargetFloorSummary,
} from '@/routines/lib/editorRecipeSummary';
import type { Block, ExerciseProfileOption } from '@/routines/types';
import { reactive, ref } from 'vue';

const {
    form,
    active,
    activeExerciseIndex,
    selectBlockExercise,
    warmUpText,
    setWarmUpText,
    clearWarmUp,
    addWarmUpStep,
    removeWarmUpStep,
    removeBlock,
    addBlock,
    trimDropsetsToSetCount,
    dropsetSummary,
    profileOptions,
    applyProfile,
    customiseExercise,
    cancelExerciseCustomise,
    hasExerciseCustomiseSnapshot,
    customiseSharedRecipe,
    cancelSharedCustomise,
    hasSharedCustomiseSnapshot,
    setExerciseTarget,
    setExerciseFloor,
    exerciseFloorPlaceholder,
    markSharedCustom,
    formatRest,
    exerciseProfileIsOutdated,
    sharedProfileIsOutdated,
    registerProfile,
} = useRoutineEditor();

const saveDialogOpen = ref(false);
const saveDialogBlock = ref<Block | null>(null);
const saveDialogExerciseIndex = ref(0);
const dropsetsOpen = reactive<Record<number, boolean>>({});

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

const toggleDropsets = (blockIndex: number): void => {
    dropsetsOpen[blockIndex] = !dropsetsOpen[blockIndex];
};
</script>

<template>
    <div class="hidden min-h-0 flex-1 flex-col md:flex">
        <DesktopRoutineSettings />
        <div class="overflow-x-auto px-2 py-3">
            <table class="w-full min-w-[64rem] table-fixed border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-border font-mono text-xs text-muted-foreground uppercase">
                        <th class="w-10 px-2 py-2">#</th>
                        <th class="w-[28%] min-w-[14rem] px-2 py-2">Exercise</th>
                        <th class="w-24 px-2 py-2">kg</th>
                        <th class="w-[16%] px-2 py-2">Profile</th>
                        <th class="w-16 px-2 py-2">Sets</th>
                        <th class="w-28 px-2 py-2">Rest</th>
                        <th class="w-[16%] px-2 py-2">Warm-up</th>
                        <th class="w-40 px-2 py-2">Options</th>
                        <th class="w-20 px-2 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(block, bi) in form.blocks" :key="bi">
                        <tr
                            v-for="(ex, ei) in block.exercises"
                            :key="`${bi}-${ei}`"
                            class="border-b border-border align-top"
                            :class="bi === active && ei === activeExerciseIndex ? 'bg-primary/5' : ''"
                            @click="selectBlockExercise(bi, ei)"
                        >
                            <td class="px-2 py-2 font-mono text-muted-foreground">
                                {{ ei === 0 ? bi + 1 : '' }}
                            </td>
                            <td class="px-2 py-2 align-top">
                                <div class="flex w-full min-w-0 flex-col gap-1.5" @click.stop>
                                    <div class="flex w-full min-w-0 items-center gap-2">
                                        <span v-if="block.is_superset" class="shrink-0 font-mono text-xs text-primary">{{
                                            ei === 0 ? 'A' : 'B'
                                        }}</span>
                                        <div class="min-w-0 flex-1">
                                            <ExercisePicker
                                                v-model="ex.exercise_id"
                                                variant="desktop"
                                                :active="bi === active && ei === activeExerciseIndex"
                                                @open="selectBlockExercise(bi, ei)"
                                            />
                                        </div>
                                    </div>
                                    <DeloadAlternateFields
                                        :deload-exercise-id="ex.deload_exercise_id"
                                        :deload-working-weight-kg="ex.deload_working_weight_kg"
                                        :working-weight-kg="ex.working_weight_kg"
                                        variant="desktop"
                                        :class="block.is_superset ? 'pl-5' : ''"
                                        @update:deload-exercise-id="ex.deload_exercise_id = $event"
                                        @update:deload-working-weight-kg="ex.deload_working_weight_kg = $event"
                                    />
                                </div>
                            </td>
                            <td class="px-2 py-2 align-top">
                                <input
                                    v-model.number="ex.working_weight_kg"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputmode="decimal"
                                    class="h-8 w-20 rounded border border-border bg-card px-2 font-mono text-sm tabular-nums"
                                />
                            </td>
                            <td class="px-2 py-2 align-top">
                                <div class="flex min-w-0 flex-col gap-1.5" @click.stop>
                                    <ExerciseProfilePicker
                                        :model-value="ex.exercise_profile_id ?? null"
                                        :profiles="profileOptions"
                                        variant="compact"
                                        label=""
                                        :show-meta="false"
                                        :required="false"
                                        :outdated="exerciseProfileIsOutdated(block, ei)"
                                        @update:model-value="applyProfile(block, $event, ei)"
                                    />
                                    <div v-if="exerciseRecipeIsCustom(ex)" class="rounded border border-border/70 bg-card/50 px-2 py-1.5">
                                        <div class="flex flex-wrap gap-2">
                                            <label class="flex flex-col gap-0.5 text-[11px] text-muted-foreground">
                                                Target
                                                <input
                                                    :value="ex.prescribed_reps"
                                                    type="number"
                                                    min="1"
                                                    max="100"
                                                    data-exercise-target
                                                    class="h-8 w-16 rounded border border-border bg-card px-1.5 font-mono text-sm"
                                                    @input="setExerciseTarget(ex, ($event.target as HTMLInputElement).value)"
                                                />
                                            </label>
                                            <label class="flex flex-col gap-0.5 text-[11px] text-muted-foreground">
                                                Floor
                                                <input
                                                    :value="ex.achievement_floor ?? ''"
                                                    type="number"
                                                    min="1"
                                                    max="100"
                                                    data-exercise-floor
                                                    :placeholder="exerciseFloorPlaceholder(block, ei)"
                                                    class="h-8 w-16 rounded border border-border bg-card px-1.5 font-mono text-sm"
                                                    @input="setExerciseFloor(ex, ($event.target as HTMLInputElement).value)"
                                                />
                                            </label>
                                        </div>
                                        <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                                            <button
                                                type="button"
                                                class="text-xs text-primary underline-offset-2 hover:underline"
                                                @click="openSaveProfile(block, ei)"
                                            >
                                                Save as profile
                                            </button>
                                            <button
                                                v-if="hasExerciseCustomiseSnapshot(block, ei)"
                                                type="button"
                                                class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                                                data-cancel-customise-exercise
                                                @click="cancelExerciseCustomise(block, ei)"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else>
                                        <p class="font-mono text-xs text-foreground">
                                            {{ formatExerciseTargetFloorSummary(ex, exerciseFloorPlaceholder(block, ei)) }}
                                        </p>
                                        <button
                                            type="button"
                                            class="mt-1 text-xs text-primary underline-offset-2 hover:underline"
                                            data-customise-exercise
                                            @click="customiseExercise(block, ei)"
                                        >
                                            Customise
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-2 align-top">
                                <input
                                    v-if="ei === 0"
                                    v-model.number="block.working.set_count"
                                    type="number"
                                    min="1"
                                    class="h-8 w-14 rounded border border-border bg-card px-2 font-mono text-sm"
                                    @change="trimDropsetsToSetCount(block)"
                                />
                            </td>
                            <td class="px-2 py-2 align-top">
                                <div v-if="ei === 0" class="min-w-[7rem]" @click.stop>
                                    <div v-if="blockSharedRecipeIsCustom(block)" class="rounded border border-border/70 bg-card/50 px-2 py-1.5">
                                        <label class="flex flex-col gap-0.5 text-[11px] text-muted-foreground">
                                            Working rest (s)
                                            <span v-if="sharedProfileIsOutdated(block)" class="text-amber-400">Update available</span>
                                            <input
                                                v-model.number="block.working.rest_seconds"
                                                type="number"
                                                min="0"
                                                max="3600"
                                                step="15"
                                                class="h-8 w-full rounded border border-border bg-card px-1.5 font-mono text-sm"
                                                @input="markSharedCustom(block)"
                                            />
                                            <span class="font-mono text-xs text-foreground">{{ formatRest(block.working.rest_seconds) }}</span>
                                        </label>
                                        <button
                                            v-if="hasSharedCustomiseSnapshot(block)"
                                            type="button"
                                            class="mt-1.5 text-xs text-muted-foreground underline-offset-2 hover:underline"
                                            data-cancel-customise-shared
                                            @click="cancelSharedCustomise(block)"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                    <div v-else data-shared-rest-summary>
                                        <p class="font-mono text-sm text-foreground">{{ formatBlockRestSummary(block) }}</p>
                                        <p v-if="sharedProfileIsOutdated(block)" class="mt-0.5 text-[11px] text-amber-400">Update available</p>
                                        <button
                                            type="button"
                                            class="mt-1 text-xs text-primary underline-offset-2 hover:underline"
                                            data-customise-shared
                                            @click="customiseSharedRecipe(block)"
                                        >
                                            Customise
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="h-px min-w-0 px-2 py-2">
                                <div v-if="ei === 0" class="flex h-full w-full min-w-0 flex-col" @click.stop>
                                    <template v-if="blockSharedRecipeIsCustom(block)">
                                        <div v-if="!block.warm_up.steps.length" data-warmup-disabled>
                                            <p class="text-xs text-muted-foreground">No warm-up</p>
                                            <button
                                                type="button"
                                                class="mt-1 text-xs text-primary underline-offset-2 hover:underline"
                                                data-enable-warmup
                                                @click="
                                                    markSharedCustom(block);
                                                    addWarmUpStep(block);
                                                "
                                            >
                                                Enable warm-up
                                            </button>
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-full w-full min-w-0 flex-col gap-2 rounded border border-border/70 bg-card/50 px-2 py-2"
                                            data-warmup-editor
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <label class="flex flex-col gap-0.5 text-[11px] text-muted-foreground">
                                                    Warm-up rest (s)
                                                    <input
                                                        v-model.number="block.warm_up.rest_seconds"
                                                        type="number"
                                                        min="0"
                                                        max="3600"
                                                        step="15"
                                                        class="h-8 w-24 rounded border border-border bg-card px-1.5 font-mono text-sm"
                                                        @input="markSharedCustom(block)"
                                                    />
                                                    <span class="font-mono text-[10px] text-muted-foreground">{{
                                                        formatRest(block.warm_up.rest_seconds)
                                                    }}</span>
                                                </label>
                                                <button
                                                    type="button"
                                                    class="self-start text-[11px] text-muted-foreground hover:text-destructive"
                                                    data-disable-warmup
                                                    @click="
                                                        markSharedCustom(block);
                                                        clearWarmUp(block);
                                                    "
                                                >
                                                    Disable
                                                </button>
                                            </div>
                                            <input
                                                :value="warmUpText(block)"
                                                class="w-full rounded border border-border bg-card px-1.5 py-0.5 font-mono text-xs text-primary/90"
                                                placeholder="40%×5, 60%×3, 80%×1"
                                                aria-label="Compact warm-up"
                                                @input="
                                                    markSharedCustom(block);
                                                    setWarmUpText(block, ($event.target as HTMLInputElement).value);
                                                "
                                            />
                                            <div class="min-h-0 flex-1 divide-y divide-border/50 overflow-auto rounded border border-border/50">
                                                <div
                                                    v-for="(step, si) in block.warm_up.steps"
                                                    :key="si"
                                                    class="flex flex-wrap items-center gap-1.5 px-1.5 py-1.5"
                                                >
                                                    <span class="w-5 shrink-0 font-mono text-[10px] text-muted-foreground">{{ si + 1 }}</span>
                                                    <select
                                                        v-model="step.mode"
                                                        class="rounded border border-border bg-card px-1 py-0.5 text-xs"
                                                        aria-label="Warm-up mode"
                                                        @change="
                                                            if (step.mode === 'bar') step.percent = undefined;
                                                            else if (step.percent == null) step.percent = 50;
                                                            markSharedCustom(block);
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
                                                        class="w-14 rounded border border-border bg-card px-1 py-0.5 font-mono text-xs"
                                                        aria-label="Warm-up percent"
                                                        @input="markSharedCustom(block)"
                                                    />
                                                    <span class="text-[11px] text-muted-foreground">×</span>
                                                    <input
                                                        v-model.number="step.reps"
                                                        type="number"
                                                        min="1"
                                                        max="100"
                                                        class="w-12 rounded border border-border bg-card px-1 py-0.5 font-mono text-xs"
                                                        aria-label="Warm-up reps"
                                                        @input="markSharedCustom(block)"
                                                    />
                                                    <label
                                                        v-if="si < block.warm_up.steps.length - 1"
                                                        class="flex items-center gap-1 text-[11px] text-muted-foreground"
                                                    >
                                                        <input v-model="step.has_setup_after" type="checkbox" />
                                                        Setup after
                                                    </label>
                                                    <button
                                                        type="button"
                                                        class="ml-auto text-[11px] text-muted-foreground hover:text-destructive"
                                                        @click="
                                                            markSharedCustom(block);
                                                            removeWarmUpStep(block, si);
                                                        "
                                                    >
                                                        −
                                                    </button>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                class="text-xs text-primary underline-offset-2 hover:underline"
                                                @click="
                                                    markSharedCustom(block);
                                                    addWarmUpStep(block);
                                                "
                                            >
                                                + Step
                                            </button>
                                        </div>
                                    </template>
                                    <div
                                        v-else
                                        class="w-full min-w-0"
                                        :class="block.warm_up.steps.length > 1 ? 'flex h-full flex-col' : ''"
                                        data-shared-warmup-summary
                                    >
                                        <template v-for="summary in [formatBlockWarmUpSummary(block)]" :key="summary.steps.join('|')">
                                            <ul
                                                class="font-mono text-sm text-foreground"
                                                :class="
                                                    block.warm_up.steps.length > 1 ? 'flex flex-1 flex-col justify-evenly gap-1.5' : 'space-y-0.5'
                                                "
                                            >
                                                <li v-for="(stepLabel, si) in summary.steps" :key="si">{{ stepLabel }}</li>
                                            </ul>
                                            <p v-if="summary.rest" class="mt-3 border-t border-border/50 pt-2.5 text-[11px] text-muted-foreground">
                                                Rest between steps {{ summary.rest }}
                                            </p>
                                        </template>
                                        <button
                                            type="button"
                                            class="mt-2.5 self-start text-xs text-primary underline-offset-2 hover:underline"
                                            data-customise-shared
                                            @click="customiseSharedRecipe(block)"
                                        >
                                            Customise
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-2">
                                <BlockSetupOptions v-if="ei === 0" :block-index="bi" variant="desktop" />
                            </td>
                            <td class="px-2 py-2">
                                <button
                                    v-if="ei === 0"
                                    type="button"
                                    class="text-xs text-muted-foreground hover:text-destructive"
                                    @click="removeBlock(bi)"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>

                        <tr
                            v-if="!block.is_superset"
                            :key="`${bi}-dropsets`"
                            data-dropset-editor
                            class="border-b border-border"
                            :class="bi === active ? 'bg-primary/5' : 'bg-card/30'"
                            @click="selectBlockExercise(bi, 0)"
                        >
                            <td class="px-2 py-1" />
                            <td class="px-2 py-2" colspan="8" @click.stop>
                                <EditorDisclosure
                                    flush
                                    :expanded="Boolean(dropsetsOpen[bi])"
                                    label="Dropsets"
                                    :summary="dropsetSummary(block) || (dropsetsOpen[bi] ? 'None' : 'None — expand to configure')"
                                    @toggle="toggleDropsets(bi)"
                                >
                                    <div class="grid min-w-0 grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-3">
                                        <DropsetEditor :block="block" variant="desktop" />
                                    </div>
                                </EditorDisclosure>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p v-if="!form.blocks.length" class="px-4 py-8 text-center text-muted-foreground">No exercises yet. Add one below.</p>

            <footer class="flex gap-2 border-t border-border px-4 py-3">
                <button type="button" class="rounded border border-border px-3 py-2 text-sm hover:border-primary" @click="addBlock(false)">
                    + Exercise
                </button>
                <button type="button" class="rounded border border-border px-3 py-2 text-sm hover:border-primary" @click="addBlock(true)">
                    + Superset
                </button>
            </footer>
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
