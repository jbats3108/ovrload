<script setup lang="ts">
import BlockSetupOptions from '@/routines/components/BlockSetupOptions.vue';
import DeloadSettings from '@/routines/components/DeloadSettings.vue';
import DropsetEditor from '@/routines/components/DropsetEditor.vue';
import ExercisePicker from '@/routines/components/ExercisePicker.vue';
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import { clearDeloadAlternate, setDeloadAlternateExercise } from '@/routines/lib/blocks';
import { optionalRepsPlaceholder, parseOptionalReps } from '@/routines/lib/optionalReps';
import type { BlockExercise } from '@/routines/types';

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
    achievementFloorDefault,
} = useRoutineEditor();

const onDeloadExercise = (exercise: BlockExercise, id: number | null) => {
    setDeloadAlternateExercise(exercise, id);
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
                        <th class="px-2 py-2">Reps</th>
                        <th class="px-2 py-2">Floor</th>
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
                                    <div class="flex min-w-0 items-center gap-1 pl-0" :class="block.is_superset ? 'pl-5' : ''">
                                        <span class="shrink-0 font-mono text-[10px] text-muted-foreground uppercase">Deload</span>
                                        <ExercisePicker
                                            :model-value="ex.deload_exercise_id"
                                            variant="desktop"
                                            @update:model-value="onDeloadExercise(ex, $event)"
                                        />
                                        <input
                                            :value="ex.deload_working_weight_kg ?? ''"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            inputmode="decimal"
                                            placeholder="kg"
                                            title="Deload alternate working weight"
                                            class="w-16 rounded border border-border bg-card px-1.5 py-1 font-mono text-xs tabular-nums"
                                            :disabled="ex.deload_exercise_id === null"
                                            @input="
                                                ex.deload_working_weight_kg = ($event.target as HTMLInputElement).value
                                                    ? Number(($event.target as HTMLInputElement).value)
                                                    : null
                                            "
                                        />
                                        <button
                                            v-if="ex.deload_exercise_id !== null"
                                            type="button"
                                            class="shrink-0 text-[10px] text-muted-foreground hover:text-foreground"
                                            title="Clear deload alternate"
                                            @click="clearDeloadAlternate(ex)"
                                        >
                                            Clear
                                        </button>
                                    </div>
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
                                <input
                                    v-model.number="ex.prescribed_reps"
                                    type="number"
                                    min="1"
                                    class="w-16 rounded border border-border bg-card px-2 py-1 font-mono"
                                />
                            </td>
                            <td class="px-2 py-2">
                                <input
                                    :value="ex.achievement_floor ?? ''"
                                    type="number"
                                    min="1"
                                    max="100"
                                    :placeholder="optionalRepsPlaceholder(achievementFloorDefault)"
                                    title="Achievement floor override (empty = user default)"
                                    class="w-16 rounded border border-border bg-card px-2 py-1 font-mono"
                                    @input="ex.achievement_floor = parseOptionalReps(($event.target as HTMLInputElement).value)"
                                />
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
                                <input
                                    v-if="ei === 0"
                                    v-model.number="block.working.rest_seconds"
                                    type="number"
                                    min="0"
                                    step="15"
                                    class="w-20 rounded border border-border bg-card px-2 py-1 font-mono"
                                />
                            </td>
                            <td class="px-2 py-2">
                                <div v-if="ei === 0" class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1">
                                        <input
                                            :value="warmUpText(block)"
                                            class="w-32 rounded border border-border bg-card px-2 py-1 font-mono text-primary/90"
                                            placeholder="40x5, 60x3, 80x1"
                                            @input="setWarmUpText(block, ($event.target as HTMLInputElement).value)"
                                        />
                                        <button
                                            v-if="block.warm_up.steps.length"
                                            type="button"
                                            class="shrink-0 text-xs text-muted-foreground hover:text-destructive"
                                            title="Clear warm-up"
                                            @click="clearWarmUp(block)"
                                        >
                                            Clear
                                        </button>
                                    </div>
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
                                <input
                                    v-if="ei === 0"
                                    v-model.number="block.warm_up.rest_seconds"
                                    type="number"
                                    min="0"
                                    step="15"
                                    class="w-20 rounded border border-border bg-card px-2 py-1 font-mono"
                                />
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
    </div>
</template>
