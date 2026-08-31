<script setup lang="ts">
import BlockSetupOptions from '@/routines/components/BlockSetupOptions.vue';
import DeloadAlternateFields from '@/routines/components/DeloadAlternateFields.vue';
import DeloadSettings from '@/routines/components/DeloadSettings.vue';
import DropsetEditor from '@/routines/components/DropsetEditor.vue';
import ExercisePicker from '@/routines/components/ExercisePicker.vue';
import ExerciseProfilePicker from '@/routines/components/ExerciseProfilePicker.vue';
import SaveExerciseProfileDialog from '@/routines/components/SaveExerciseProfileDialog.vue';
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import type { Block, ExerciseProfileOption } from '@/routines/types';
import { ref } from 'vue';

const {
    form,
    active,
    activeExerciseIndex,
    activeBlock,
    selectBlockExercise,
    warmUpText,
    setWarmUpText,
    clearWarmUp,
    removeBlock,
    addBlock,
    trimDropsetsToSetCount,
    dropsetSummary,
    profileOptions,
    applyProfile,
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
    <div class="hidden min-h-0 flex-1 flex-col md:flex">
        <div class="overflow-x-auto px-2 py-3">
            <table class="w-full min-w-[60rem] border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-border font-mono text-xs text-muted-foreground uppercase">
                        <th class="px-2 py-2">#</th>
                        <th class="px-2 py-2">Exercise</th>
                        <th class="px-2 py-2">kg</th>
                        <th class="px-2 py-2">Profile</th>
                        <th class="px-2 py-2">Sets</th>
                        <th class="px-2 py-2">Rest</th>
                        <th class="px-2 py-2">Warm-up %×reps</th>
                        <th class="px-2 py-2">WU rest</th>
                        <th class="px-2 py-2">Options</th>
                        <th class="px-2 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(block, bi) in form.blocks" :key="bi">
                        <tr
                            v-for="(ex, ei) in block.exercises"
                            :key="`${bi}-${ei}`"
                            class="border-b border-border"
                            :class="bi === active && ei === activeExerciseIndex ? 'bg-primary/5' : ''"
                            @click="selectBlockExercise(bi, ei)"
                        >
                            <td class="px-2 py-2 font-mono text-muted-foreground">
                                {{ ei === 0 ? bi + 1 : '' }}
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex min-w-0 flex-col gap-1" @click.stop>
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span v-if="block.is_superset" class="font-mono text-xs text-primary">{{ ei === 0 ? 'A' : 'B' }}</span>
                                        <ExercisePicker
                                            v-model="ex.exercise_id"
                                            variant="desktop"
                                            :active="bi === active && ei === activeExerciseIndex"
                                            @open="selectBlockExercise(bi, ei)"
                                        />
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
                            <td class="px-2 py-2">
                                <input
                                    v-model.number="ex.working_weight_kg"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputmode="decimal"
                                    class="w-20 rounded border border-border bg-card px-2 py-1 font-mono tabular-nums"
                                />
                            </td>
                            <td class="px-2 py-2">
                                <ExerciseProfilePicker
                                    :model-value="ex.exercise_profile_id ?? null"
                                    :profiles="profileOptions"
                                    variant="compact"
                                    :required="false"
                                    :outdated="exerciseProfileIsOutdated(block, ei)"
                                    @update:model-value="applyProfile(block, $event, ei)"
                                />
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                                        Edit target &amp; floor
                                    </summary>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                            Target
                                            <input
                                                :value="ex.prescribed_reps"
                                                type="number"
                                                min="1"
                                                max="100"
                                                class="w-20 rounded border border-border bg-card px-2 py-1 font-mono"
                                                @input="setExerciseTarget(ex, ($event.target as HTMLInputElement).value)"
                                            />
                                        </label>
                                        <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                            Floor
                                            <input
                                                :value="ex.achievement_floor ?? ''"
                                                type="number"
                                                min="1"
                                                max="100"
                                                :placeholder="exerciseFloorPlaceholder(block, ei)"
                                                class="w-20 rounded border border-border bg-card px-2 py-1 font-mono"
                                                @input="setExerciseFloor(ex, ($event.target as HTMLInputElement).value)"
                                            />
                                        </label>
                                    </div>
                                </details>
                                <button
                                    v-if="ex.exercise_profile_id == null"
                                    type="button"
                                    class="mt-2 text-xs text-primary underline-offset-2 hover:underline"
                                    @click.stop="openSaveProfile(block, ei)"
                                >
                                    Save as profile
                                </button>
                            </td>
                            <td class="px-2 py-2">
                                <input
                                    v-if="ei === 0"
                                    v-model.number="block.working.set_count"
                                    type="number"
                                    min="1"
                                    class="w-14 rounded border border-border bg-card px-2 py-1 font-mono"
                                    @change="trimDropsetsToSetCount(block)"
                                />
                            </td>
                            <td class="px-2 py-2">
                                <details v-if="ei === 0">
                                    <summary class="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                                        Edit ({{ formatRest(block.working.rest_seconds) }})
                                        <span v-if="sharedProfileIsOutdated(block)" class="text-amber-400">· Update available</span>
                                    </summary>
                                    <input
                                        v-model.number="block.working.rest_seconds"
                                        type="number"
                                        min="0"
                                        max="3600"
                                        step="15"
                                        class="mt-1 w-20 rounded border border-border bg-card px-2 py-1 font-mono"
                                        @input="markSharedCustom(block)"
                                    />
                                </details>
                            </td>
                            <td class="px-2 py-2">
                                <div v-if="ei === 0" class="flex flex-col gap-1">
                                    <details>
                                        <summary class="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                                            {{ block.warm_up.steps.length ? warmUpText(block) : 'Edit warm-up' }}
                                        </summary>
                                        <div class="mt-1 flex items-center gap-1">
                                            <input
                                                :value="warmUpText(block)"
                                                class="w-32 rounded border border-border bg-card px-2 py-1 font-mono text-primary/90"
                                                placeholder="40%×5, 60%×3, 80%×1"
                                                @input="
                                                    markSharedCustom(block);
                                                    setWarmUpText(block, ($event.target as HTMLInputElement).value);
                                                "
                                            />
                                            <button
                                                v-if="block.warm_up.steps.length"
                                                type="button"
                                                class="shrink-0 text-xs text-muted-foreground hover:text-destructive"
                                                title="Clear warm-up"
                                                @click="
                                                    markSharedCustom(block);
                                                    clearWarmUp(block);
                                                "
                                            >
                                                Clear
                                            </button>
                                        </div>
                                    </details>
                                    <div v-if="block.warm_up.steps.length > 1" class="flex flex-wrap gap-1">
                                        <label
                                            v-for="(step, si) in block.warm_up.steps.slice(0, -1)"
                                            :key="si"
                                            class="flex items-center gap-0.5 text-[10px] text-muted-foreground"
                                            :title="`Setup after warm-up ${si + 1}`"
                                        >
                                            <input v-model="step.has_setup_after" type="checkbox" />
                                            S{{ si + 1 }}
                                        </label>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-2">
                                <details v-if="ei === 0">
                                    <summary class="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                                        Edit ({{ formatRest(block.warm_up.rest_seconds) }})
                                    </summary>
                                    <input
                                        v-model.number="block.warm_up.rest_seconds"
                                        type="number"
                                        min="0"
                                        max="3600"
                                        step="15"
                                        class="mt-1 w-20 rounded border border-border bg-card px-2 py-1 font-mono"
                                    />
                                </details>
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
                    </template>
                </tbody>
            </table>
            <p v-if="!form.blocks.length" class="px-4 py-8 text-center text-muted-foreground">No exercises yet. Add one below.</p>

            <!-- Keep Deload inside the same horizontal scroll region as the table, so the scrollbar sits below it. -->
            <DeloadSettings variant="desktop" />

            <div v-if="activeBlock && !activeBlock.is_superset" class="min-w-0 border-t border-border bg-card/40 px-4 py-3">
                <div class="mb-2 flex min-w-0 items-baseline justify-between gap-2">
                    <h3 class="text-sm font-medium">Dropsets · Exercise {{ active + 1 }}</h3>
                    <p v-if="dropsetSummary(activeBlock)" class="truncate font-mono text-xs text-muted-foreground">
                        {{ dropsetSummary(activeBlock) }}
                    </p>
                </div>
                <div class="grid min-w-0 grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-3">
                    <DropsetEditor :block="activeBlock" variant="desktop" />
                </div>
            </div>

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
