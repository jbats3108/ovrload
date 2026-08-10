import { emptyBlock, normalizeBlock, syncSetupAfterBlockFlags, toggleSuperset } from '@/routines/lib/blocks';
import {
    addDropsetSegment,
    applyRunTheRack,
    dropsetForIndex,
    dropsetSummary,
    isDropsetSlot,
    removeDropsetSegment,
    setSlotKind,
    trimDropsetsToSetCount,
} from '@/routines/lib/dropsets';
import { formatRest, normalizeRestSeconds } from '@/routines/lib/formatRest';
import { deleteRoutine as deleteRoutineMutation, duplicateRoutine as duplicateRoutineMutation } from '@/routines/lib/routineMutations';
import { addWarmUpStep, clearWarmUp, removeWarmUpStep, sanitizeWarmUpStepsForSave, setWarmUpText, warmUpText } from '@/routines/lib/warmUp';
import type { Block, EquipmentOption, ExerciseOption, MuscleGroupOption, RoutinePayload, WarmUpStep } from '@/routines/types';
import type { WarmUpDefaultsScope } from '@/settings/types';
import { confirmDialog } from '@/shared/lib/confirmDialog';
import { useForm } from '@inertiajs/vue3';
import { computed, inject, ref, watch, type InjectionKey } from 'vue';

export type EditRoutineProps = {
    routine: RoutinePayload;
    exercises?: ExerciseOption[];
    weight_unit: string;
    warm_up_defaults: WarmUpStep[];
    warm_up_defaults_scope?: WarmUpDefaultsScope;
    achievement_floor_default?: number | null;
    progression_target_default?: number | null;
    muscle_groups?: MuscleGroupOption[];
    equipment_options?: EquipmentOption[];
};

export type RoutineEditor = ReturnType<typeof createRoutineEditor>;

export const routineEditorKey: InjectionKey<RoutineEditor> = Symbol('routineEditor');

export function createRoutineEditor(props: EditRoutineProps) {
    const catalogExtras = ref<ExerciseOption[]>([]);
    const catalog = computed(() => {
        const base = props.exercises ?? [];
        const seen = new Set(base.map((exercise) => exercise.id));
        return [...base, ...catalogExtras.value.filter((exercise) => !seen.has(exercise.id))];
    });
    const muscleGroups = computed(() => props.muscle_groups ?? []);
    const equipmentOptions = computed(() => props.equipment_options ?? []);

    const addToCatalog = (exercise: ExerciseOption) => {
        if (catalog.value.some((item) => item.id === exercise.id)) {
            return;
        }
        catalogExtras.value = [...catalogExtras.value, exercise];
    };

    const defaultWarmUpSteps = (): WarmUpStep[] =>
        (props.warm_up_defaults?.length ? props.warm_up_defaults : []).map((s) => ({
            percent: s.percent,
            reps: s.reps,
        }));

    const firstCatalogId = () => catalog.value[0]?.id ?? null;

    const form = useForm(`EditRoutine:${props.routine.id}`, {
        name: props.routine.name,
        deload_weight_factor: props.routine.deload_weight_factor,
        deload_reps_factor: props.routine.deload_reps_factor,
        deload_every_n: props.routine.deload_every_n,
        expected_updated_at: props.routine.updated_at,
        // Inertia props are nested reactive proxies — structuredClone cannot clone them
        blocks: props.routine.blocks.length
            ? (() => {
                  const blocks = (JSON.parse(JSON.stringify(props.routine.blocks)) as Block[]).map(normalizeBlock);
                  syncSetupAfterBlockFlags(blocks);

                  return blocks;
              })()
            : ([] as Block[]),
    });

    const active = ref(0);
    const activeExerciseIndex = ref(0);
    const warmUpExpanded = ref(false);
    const dropsetsExpanded = ref(false);
    const progressionExpanded = ref(false);
    const deloadExpanded = ref(false);
    const mutating = ref(false);

    const toggleWarmUpExpanded = () => {
        warmUpExpanded.value = !warmUpExpanded.value;
    };

    const toggleDropsetsExpanded = () => {
        dropsetsExpanded.value = !dropsetsExpanded.value;
    };

    const toggleProgressionExpanded = () => {
        progressionExpanded.value = !progressionExpanded.value;
    };

    const toggleDeloadExpanded = () => {
        deloadExpanded.value = !deloadExpanded.value;
    };

    watch(
        () => form.blocks.length,
        (len) => {
            syncSetupAfterBlockFlags(form.blocks);
            if (active.value >= len) {
                active.value = Math.max(0, len - 1);
            }
            activeExerciseIndex.value = 0;
        },
    );
    watch(active, () => {
        warmUpExpanded.value = false;
        dropsetsExpanded.value = false;
        progressionExpanded.value = false;
        activeExerciseIndex.value = 0;
    });

    const activeBlock = computed(() => form.blocks[active.value] ?? null);

    const selectBlockExercise = (blockIndex: number, exerciseIndex = 0) => {
        active.value = blockIndex;
        activeExerciseIndex.value = exerciseIndex;
    };

    const exerciseName = (id: number | null) => catalog.value.find((e) => e.id === id)?.name ?? 'Exercise';

    const addBlock = (superset = false) => {
        const seedWarmUp = (props.warm_up_defaults_scope ?? 'all_blocks') === 'all_blocks' || form.blocks.length === 0;
        form.blocks.push(
            emptyBlock({
                superset,
                seedWarmUp,
                warmUpDefaults: defaultWarmUpSteps(),
                firstCatalogId: firstCatalogId(),
            }),
        );
        active.value = form.blocks.length - 1;
    };

    const removeBlock = (index: number) => {
        form.blocks.splice(index, 1);
    };

    const onToggleSuperset = (block: Block) => {
        toggleSuperset(block, firstCatalogId());
    };

    const rackStart = ref(20);
    const rackEnd = ref(10);
    const rackStep = ref(2.5);

    const onApplyRunTheRack = (block: Block, setIndex: number) => {
        applyRunTheRack(block, setIndex, {
            start: rackStart.value,
            end: rackEnd.value,
            step: rackStep.value,
        });
    };

    watch(
        () => form.blocks.map((b) => b.working.set_count),
        () => {
            form.blocks.forEach(trimDropsetsToSetCount);
        },
    );

    const normalizeOptionalReps = (value: number | null | undefined): number | null =>
        typeof value === 'number' && Number.isFinite(value) && value >= 1 ? value : null;

    const revealSaveErrors = (): void => {
        requestAnimationFrame(() => {
            document.querySelector<HTMLElement>('[data-routine-save-errors]')?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        });
    };

    const save = () => {
        syncSetupAfterBlockFlags(form.blocks);
        form.transform((data) => ({
            ...data,
            blocks: data.blocks.map((block) => {
                const warmUpSteps = sanitizeWarmUpStepsForSave(block.warm_up.steps);

                return {
                    ...block,
                    has_setup_after_warm_up: warmUpSteps.length === 0 ? false : block.has_setup_after_warm_up,
                    exercises: block.exercises.map((exercise) => ({
                        ...exercise,
                        achievement_floor: normalizeOptionalReps(exercise.achievement_floor),
                        progression_target: null,
                        deload_exercise_id: exercise.deload_exercise_id,
                        deload_working_weight_kg: exercise.deload_exercise_id != null ? exercise.deload_working_weight_kg : null,
                    })),
                    warm_up: {
                        set_count: warmUpSteps.length,
                        rest_seconds: normalizeRestSeconds(block.warm_up.rest_seconds),
                        steps: warmUpSteps,
                    },
                    working: {
                        set_count: block.working.set_count,
                        rest_seconds: normalizeRestSeconds(block.working.rest_seconds),
                        dropsets: block.is_superset
                            ? []
                            : block.working.dropsets
                                  .filter((d) => d.set_index < block.working.set_count && d.segments.length >= 2)
                                  .map((d) => ({
                                      set_index: d.set_index,
                                      segments: d.segments.map((s) => ({ weight_kg: s.weight_kg })),
                                  })),
                    },
                };
            }),
        })).put(route('routines.update', props.routine.slug), {
            onError: revealSaveErrors,
        });
    };

    const duplicateRoutine = async () => {
        if (form.processing) {
            return;
        }
        await duplicateRoutineMutation(props.routine.slug, {
            mutating,
            confirm: async () => {
                if (!form.isDirty) {
                    return true;
                }
                return confirmDialog({
                    title: 'Duplicate the last saved version?',
                    description: 'Unsaved edits will not be included.',
                    confirmLabel: 'Duplicate',
                });
            },
        });
    };

    const deleteRoutine = async () => {
        if (form.processing) {
            return;
        }
        await deleteRoutineMutation(props.routine.slug, form.name || 'this routine', mutating);
    };

    const errorList = computed(() => Object.values(form.errors));

    return {
        form,
        catalog,
        muscleGroups,
        equipmentOptions,
        addToCatalog,
        active,
        activeExerciseIndex,
        warmUpExpanded,
        toggleWarmUpExpanded,
        dropsetsExpanded,
        toggleDropsetsExpanded,
        progressionExpanded,
        toggleProgressionExpanded,
        deloadExpanded,
        toggleDeloadExpanded,
        achievementFloorDefault: computed(() => props.achievement_floor_default ?? null),
        progressionTargetDefault: computed(() => null),
        activeBlock,
        selectBlockExercise,
        exerciseName,
        addBlock,
        removeBlock,
        toggleSuperset: onToggleSuperset,
        warmUpText,
        setWarmUpText,
        addWarmUpStep,
        removeWarmUpStep,
        clearWarmUp,
        formatRest,
        dropsetForIndex,
        isDropsetSlot,
        setSlotKind,
        addDropsetSegment,
        removeDropsetSegment,
        trimDropsetsToSetCount,
        dropsetSummary,
        rackStart,
        rackEnd,
        rackStep,
        applyRunTheRack: onApplyRunTheRack,
        save,
        duplicateRoutine,
        deleteRoutine,
        mutating,
        errorList,
        weightUnit: props.weight_unit,
    };
}

export function useRoutineEditor(): RoutineEditor {
    const editor = inject(routineEditorKey);
    if (!editor) {
        throw new Error('RoutineEditor not provided');
    }
    return editor;
}
