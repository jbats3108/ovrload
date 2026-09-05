import { emptyBlock, normalizeBlock, swapSupersetExercises, syncSetupAfterBlockFlags, toggleSuperset } from '@/routines/lib/blocks';
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
import {
    captureExerciseCustomiseSnapshot,
    captureSharedCustomiseSnapshot,
    restoreExerciseCustomiseSnapshot,
    restoreSharedCustomiseSnapshot,
    type ExerciseCustomiseSnapshot,
    type SharedCustomiseSnapshot,
} from '@/routines/lib/editorCustomiseSnapshot';
import {
    achievementFloorForSave,
    applyProfileToBlock,
    applyProfileToExercise,
    applyProfileToSupersetExercise,
    applySharedProfileToBlock,
    coerceProfileId,
    editorFloorPlaceholder,
    exerciseAssignmentFingerprint,
    markExerciseProfileCustom,
    markSharedProfileCustom,
    profileMatchesExerciseAssignment,
    profileMatchesSharedAssignment,
} from '@/routines/lib/exerciseProfiles';
import { formatRest, normalizeRestSeconds } from '@/routines/lib/formatRest';
import { optionalRepsPlaceholder } from '@/routines/lib/optionalReps';
import { deleteRoutine as deleteRoutineMutation, duplicateRoutine as duplicateRoutineMutation } from '@/routines/lib/routineMutations';
import { addWarmUpStep, clearWarmUp, removeWarmUpStep, sanitizeWarmUpStepsForSave, setWarmUpText, warmUpText } from '@/routines/lib/warmUp';
import type { Block, BlockExercise, EquipmentOption, ExerciseOption, MuscleGroupOption, RoutinePayload, WarmUpStep } from '@/routines/types';
import type { ExerciseProfileOption, WarmUpDefaultsScope } from '@/settings/types';
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
    exercise_profiles?: ExerciseProfileOption[];
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
            mode: s.mode ?? 'percent',
            percent: s.mode === 'bar' ? undefined : s.percent,
            reps: s.reps,
            has_setup_after: false,
        }));

    const firstCatalogId = () => catalog.value[0]?.id ?? null;
    const defaultTargetReps = () =>
        typeof props.progression_target_default === 'number' && props.progression_target_default >= 1 ? props.progression_target_default : 6;

    const form = useForm({
        name: props.routine.name,
        deload_weight_factor: props.routine.deload_weight_factor,
        deload_reps_factor: props.routine.deload_reps_factor,
        deload_every_n: props.routine.deload_every_n,
        default_exercise_profile_id: coerceProfileId(props.routine.default_exercise_profile_id),
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
    const deloadExpanded = ref(false);
    const mutating = ref(false);

    const toggleWarmUpExpanded = () => {
        warmUpExpanded.value = !warmUpExpanded.value;
    };

    const toggleDropsetsExpanded = () => {
        dropsetsExpanded.value = !dropsetsExpanded.value;
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
        activeExerciseIndex.value = 0;
    });

    const activeBlock = computed(() => form.blocks[active.value] ?? null);
    const profileOptions = ref<ExerciseProfileOption[]>([...(props.exercise_profiles ?? [])]);
    const profileById = (profileId: number | string | null | undefined): ExerciseProfileOption | null => {
        const id = coerceProfileId(profileId);
        if (id === null) {
            return null;
        }

        return profileOptions.value.find((profile) => profile.id === id) ?? null;
    };

    const registerProfile = (profile: ExerciseProfileOption): void => {
        if (profileOptions.value.some((item) => item.id === profile.id)) {
            return;
        }

        profileOptions.value.push(profile);
    };

    const selectBlockExercise = (blockIndex: number, exerciseIndex = 0) => {
        active.value = blockIndex;
        activeExerciseIndex.value = exerciseIndex;
    };

    const exerciseName = (id: number | null) => catalog.value.find((e) => e.id === id)?.name ?? 'Exercise';

    const addBlock = (superset = false) => {
        const seedWarmUp = (props.warm_up_defaults_scope ?? 'all_blocks') === 'all_blocks' || form.blocks.length === 0;
        const profile = profileById(form.default_exercise_profile_id ?? null);
        const block = emptyBlock({
            superset,
            seedWarmUp: profile === null ? seedWarmUp : false,
            warmUpDefaults: defaultWarmUpSteps(),
            firstCatalogId: firstCatalogId(),
            prescribedReps: defaultTargetReps(),
        });
        if (profile !== null) {
            applyProfileToBlock(block, profile, seedWarmUp);
        }
        form.blocks.push(block);
        active.value = form.blocks.length - 1;
    };

    const removeBlock = (index: number) => {
        form.blocks.splice(index, 1);
    };

    const onToggleSuperset = (block: Block) => {
        const wasSuperset = block.is_superset;
        const source = { ...block.exercises[0] };
        toggleSuperset(block, firstCatalogId(), defaultTargetReps());

        if (!wasSuperset && block.is_superset) {
            const profile = profileById(source.exercise_profile_id ?? null);
            const fingerprint =
                profile === null
                    ? source.exercise_profile_fingerprint
                    : exerciseAssignmentFingerprint(profile, true, block.shared_profile_id === profile.id);

            if (block.exercises[0] !== undefined && profile !== null) {
                block.exercises[0].exercise_profile_fingerprint = fingerprint;
            }

            if (block.exercises[1]) {
                block.exercises[1].prescribed_reps = source.prescribed_reps;
                block.exercises[1].achievement_floor = source.achievement_floor;
                block.exercises[1].floor_is_derived = source.floor_is_derived;
                block.exercises[1].exercise_profile_id = source.exercise_profile_id;
                block.exercises[1].exercise_profile_fingerprint = fingerprint;
            }
        }

        if (wasSuperset && !block.is_superset) {
            const exercise = block.exercises[0];
            const profile = profileById(exercise?.exercise_profile_id ?? null);
            if (exercise !== undefined && profile !== null) {
                exercise.exercise_profile_fingerprint = exerciseAssignmentFingerprint(profile, false, block.shared_profile_id === profile.id);
            }
        }
    };

    const onSwapSupersetExercises = (block: Block) => {
        if (!swapSupersetExercises(block)) {
            return;
        }

        if (activeBlock.value === block && activeExerciseIndex.value <= 1) {
            activeExerciseIndex.value = activeExerciseIndex.value === 0 ? 1 : 0;
        }
    };

    const exerciseCustomiseSnapshots = new WeakMap<BlockExercise, ExerciseCustomiseSnapshot>();
    const sharedCustomiseSnapshots = new WeakMap<Block, SharedCustomiseSnapshot>();

    const clearExerciseCustomiseSnapshot = (exercise: BlockExercise | undefined): void => {
        if (exercise) {
            exerciseCustomiseSnapshots.delete(exercise);
        }
    };

    const clearSharedCustomiseSnapshot = (block: Block): void => {
        sharedCustomiseSnapshots.delete(block);
    };

    const applyProfile = (block: Block, profileId: number | null, exerciseIndex = 0): void => {
        const profile = profileById(profileId);
        if (profile === null) {
            if (block.is_superset) {
                const exercise = block.exercises[exerciseIndex];
                if (exercise) {
                    markExerciseProfileCustom(exercise);
                }
            } else {
                const exercise = block.exercises[0];
                if (exercise) {
                    markExerciseProfileCustom(exercise);
                }
                markSharedProfileCustom(block);
            }
            return;
        }

        if (block.is_superset) {
            clearExerciseCustomiseSnapshot(block.exercises[exerciseIndex]);
            applyProfileToSupersetExercise(block, exerciseIndex, profile);
            return;
        }

        clearExerciseCustomiseSnapshot(block.exercises[0]);
        clearSharedCustomiseSnapshot(block);
        applyProfileToBlock(block, profile);
    };

    const customiseExercise = (block: Block, exerciseIndex = 0): void => {
        const exercise = block.exercises[exerciseIndex];
        if (!exercise) {
            return;
        }

        if (exercise.exercise_profile_id != null) {
            exerciseCustomiseSnapshots.set(exercise, captureExerciseCustomiseSnapshot(exercise));
        }

        if (!block.is_superset && block.shared_profile_id != null) {
            sharedCustomiseSnapshots.set(block, captureSharedCustomiseSnapshot(block));
        }

        applyProfile(block, null, exerciseIndex);
    };

    const cancelExerciseCustomise = (block: Block, exerciseIndex = 0): void => {
        const exercise = block.exercises[exerciseIndex];
        if (!exercise) {
            return;
        }

        const snapshot = exerciseCustomiseSnapshots.get(exercise);
        if (!snapshot) {
            return;
        }

        restoreExerciseCustomiseSnapshot(exercise, snapshot);
        exerciseCustomiseSnapshots.delete(exercise);

        if (!block.is_superset) {
            const sharedSnapshot = sharedCustomiseSnapshots.get(block);
            if (sharedSnapshot) {
                restoreSharedCustomiseSnapshot(block, sharedSnapshot);
                sharedCustomiseSnapshots.delete(block);
            }
        }
    };

    const hasExerciseCustomiseSnapshot = (block: Block, exerciseIndex = 0): boolean => {
        const exercise = block.exercises[exerciseIndex];

        return exercise !== undefined && exerciseCustomiseSnapshots.has(exercise);
    };

    const setRoutineProfile = async (profileId: number | string | null): Promise<void> => {
        const previousProfileId = coerceProfileId(form.default_exercise_profile_id);
        const nextProfileId = coerceProfileId(profileId);
        form.default_exercise_profile_id = nextProfileId;

        if (nextProfileId === null || previousProfileId === null || previousProfileId === nextProfileId) {
            return;
        }

        const profile = profileById(nextProfileId);
        if (profile === null || form.blocks.length === 0) {
            return;
        }

        const confirmed = await confirmDialog({
            title: 'Apply new routine profile?',
            description: 'This updates blocks still using the previous routine profile. Blocks you have changed manually stay as they are.',
            confirmLabel: 'Update blocks',
        });
        if (!confirmed) {
            return;
        }

        form.blocks.forEach((block) => {
            const sharedAssigned = block.shared_profile_id === previousProfileId;
            const singleBlockUsesPrevious =
                !block.is_superset && block.exercises.some((exercise) => exercise.exercise_profile_id === previousProfileId);

            if (sharedAssigned && singleBlockUsesPrevious) {
                applyProfileToBlock(block, profile);
                return;
            }

            if (sharedAssigned) {
                applySharedProfileToBlock(block, profile);
            }

            block.exercises.forEach((exercise, index) => {
                if (exercise.exercise_profile_id !== previousProfileId) {
                    return;
                }

                if (block.is_superset) {
                    applyProfileToSupersetExercise(block, index, profile);
                    return;
                }

                applyProfileToExercise(exercise, profile, true);
            });
        });
    };

    const detachSharedWhenExerciseBecomesCustom = (exercise: Block['exercises'][number]): void => {
        const block = form.blocks.find((candidate) => candidate.exercises.includes(exercise));
        if (block === undefined || block.is_superset) {
            return;
        }

        markSharedProfileCustom(block);
    };

    const setExerciseTarget = (exercise: Block['exercises'][number], raw: string): void => {
        const wasDerived = exercise.floor_is_derived === true || (exercise.exercise_profile_id != null && exercise.achievement_floor === null);
        exercise.prescribed_reps = Number(raw);
        if (wasDerived) {
            exercise.achievement_floor = null;
            exercise.floor_is_derived = true;
        }
        markExerciseProfileCustom(exercise);
        detachSharedWhenExerciseBecomesCustom(exercise);
    };

    const setExerciseFloor = (exercise: Block['exercises'][number], raw: string): void => {
        exercise.achievement_floor = raw === '' ? null : Number(raw);
        exercise.floor_is_derived = raw === '' ? true : false;
        markExerciseProfileCustom(exercise);
        detachSharedWhenExerciseBecomesCustom(exercise);
    };

    const markSharedCustom = (block: Block): void => {
        markSharedProfileCustom(block);

        if (block.is_superset) {
            return;
        }

        const exercise = block.exercises[0];
        const profile = profileById(exercise?.exercise_profile_id ?? null);
        if (exercise && profile && exercise.prescribed_reps === profile.target_reps && exercise.achievement_floor === profile.floor_override) {
            exercise.exercise_profile_fingerprint = profile.exercise_fingerprint;
        }
    };

    const customiseSharedRecipe = (block: Block): void => {
        if (block.shared_profile_id != null) {
            sharedCustomiseSnapshots.set(block, captureSharedCustomiseSnapshot(block));
        }

        markSharedCustom(block);
    };

    const cancelSharedCustomise = (block: Block): void => {
        const snapshot = sharedCustomiseSnapshots.get(block);
        if (!snapshot) {
            return;
        }

        restoreSharedCustomiseSnapshot(block, snapshot);
        sharedCustomiseSnapshots.delete(block);
    };

    const hasSharedCustomiseSnapshot = (block: Block): boolean => sharedCustomiseSnapshots.has(block);

    const exerciseProfileIsOutdated = (block: Block, exerciseIndex: number): boolean => {
        const exercise = block.exercises[exerciseIndex];
        const profile = profileById(exercise?.exercise_profile_id ?? null);

        return (
            exercise !== undefined &&
            profile !== null &&
            !profileMatchesExerciseAssignment(exercise, profile, block.is_superset, block.shared_profile_id === profile.id)
        );
    };

    const sharedProfileIsOutdated = (block: Block): boolean => {
        const profile = profileById(block.shared_profile_id ?? null);

        return profile !== null && !profileMatchesSharedAssignment(block, profile);
    };

    const exerciseFloorPlaceholder = (block: Block, exerciseIndex: number): string => {
        const exercise = block.exercises[exerciseIndex];
        if (exercise === undefined) {
            return optionalRepsPlaceholder(props.achievement_floor_default);
        }

        const profile = profileById(exercise.exercise_profile_id ?? null);
        const assignmentCurrent =
            profile !== null && profileMatchesExerciseAssignment(exercise, profile, block.is_superset, block.shared_profile_id === profile.id);

        return editorFloorPlaceholder(exercise, profile, assignmentCurrent, props.achievement_floor_default);
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
            default_exercise_profile_id: coerceProfileId(data.default_exercise_profile_id),
            blocks: data.blocks.map((block) => {
                const warmUpSteps = sanitizeWarmUpStepsForSave(block.warm_up.steps);

                return {
                    ...block,
                    has_setup_after_warm_up: warmUpSteps.length === 0 ? false : block.has_setup_after_warm_up,
                    shared_profile_id: block.shared_profile_id,
                    shared_profile_fingerprint: block.shared_profile_fingerprint,
                    exercises: block.exercises.map((exercise) => ({
                        ...exercise,
                        exercise_profile_id: exercise.exercise_profile_id ?? null,
                        exercise_profile_fingerprint: exercise.exercise_profile_fingerprint ?? null,
                        floor_is_derived: exercise.floor_is_derived ?? null,
                        achievement_floor: achievementFloorForSave(exercise),
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
            preserveState: false,
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
        deloadExpanded,
        toggleDeloadExpanded,
        achievementFloorDefault: computed(() => props.achievement_floor_default ?? null),
        progressionTargetDefault: computed(() => defaultTargetReps()),
        profileOptions,
        profileById,
        registerProfile,
        applyProfile,
        customiseExercise,
        cancelExerciseCustomise,
        hasExerciseCustomiseSnapshot,
        customiseSharedRecipe,
        cancelSharedCustomise,
        hasSharedCustomiseSnapshot,
        setRoutineProfile,
        setExerciseTarget,
        setExerciseFloor,
        exerciseFloorPlaceholder,
        markSharedCustom,
        exerciseProfileIsOutdated,
        sharedProfileIsOutdated,
        activeBlock,
        selectBlockExercise,
        exerciseName,
        addBlock,
        removeBlock,
        toggleSuperset: onToggleSuperset,
        swapSupersetExercises: onSwapSupersetExercises,
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
